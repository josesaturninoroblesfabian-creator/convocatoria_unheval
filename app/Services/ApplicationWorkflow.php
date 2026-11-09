<?php

namespace App\Services;

use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\RecruitmentCall;
use App\Models\User;
use App\Notifications\ApplicationReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicationWorkflow
{
    public function __construct(private ApplicantScreening $screening) {}

    public function assertOpen(RecruitmentCall $call): void
    {
        if (! $call->accepting_applications) {
            throw ValidationException::withMessages(['application' => 'La inscripción está cerrada o aún no ha comenzado. Se utiliza la hora del servidor de Perú.']);
        }
    }

    public function canReview(User $user, Application $application): bool
    {
        return $user->role === 'jurado' && $application->vacancy->jurors()->where('users.id', $user->id)->exists();
    }

    public function submit(Application $application, User $user, bool $notifyApplicant = true): void
    {
        DB::transaction(function () use ($application, $user, $notifyApplicant) {
            $call = RecruitmentCall::lockForUpdate()->findOrFail($application->recruitment_call_id);
            $locked = Application::lockForUpdate()->findOrFail($application->id);
            abort_unless($locked->user_id === $user->id, 404);
            $this->assertOpen($call);
            if ($locked->status !== 'BORRADOR') {
                throw ValidationException::withMessages(['application' => 'Este expediente ya fue enviado y no se puede modificar.']);
            }
            $locked->load('vacancy', 'documents');
            $screeningReport = $this->screening->assertEligible($locked);
            foreach ($this->screening->documents($locked) as $requirement) {
                if ($requirement['required'] && ! $locked->documents->contains('requirement_key', $requirement['key'])) {
                    throw ValidationException::withMessages(['application' => 'Falta el documento obligatorio: '.$requirement['label']]);
                }
            }
            if (! $locked->consented_at || ! $user->document_number || ! $user->phone) {
                throw ValidationException::withMessages(['application' => 'Completa tus datos y acepta la declaración antes del envío.']);
            }
            $snapshot = $user->only('name', 'email', 'document_number', 'phone');
            $hash = hash('sha256', json_encode(['reference' => $locked->reference, 'vacancy_id' => $locked->vacancy_id, 'profile' => $snapshot, 'form' => $locked->form_data, 'screening' => $screeningReport, 'statement' => $locked->statement, 'documents' => $locked->documents->sortBy('requirement_key')->map->only(['requirement_key', 'sha256'])->values()->all()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $locked->update(['status' => 'RECIBIDA', 'submitted_at' => now(), 'applicant_snapshot' => $snapshot, 'receipt_hash' => $hash, 'screening_snapshot' => $screeningReport['enabled'] ? $screeningReport : null, 'declared_score' => $screeningReport['enabled'] && ! $call->evaluation_template ? $screeningReport['total'] : null]);
            AuditEvent::record('EXPEDIENTE_ENVIADO', $locked, ['hash' => $hash]);
            if ($notifyApplicant) {
                DB::afterCommit(fn () => $user->notify(new ApplicationReceived($locked)));
            }
        });
    }
}
