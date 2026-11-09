<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\RecruitmentCall;
use App\Services\ApplicantScreening;
use App\Services\ApplicationWorkflow;
use App\Services\NombramientoEvaluation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function show(Request $request, Application $application, ApplicationWorkflow $workflow, ApplicantScreening $screening, NombramientoEvaluation $nombramiento): Response
    {
        $application->load('vacancy', 'recruitmentCall', 'documents');
        abort_unless($application->submitted_at && ($request->user()->isManager() || $workflow->canReview($request->user(), $application)), 404);
        $own = $application->evaluations()->where('user_id', $request->user()->id)->first();

        return Inertia::render('Review', ['application' => $application, 'screening' => $screening->assess($application), 'verifiedScreening' => $application->status !== 'RECIBIDA' ? $screening->verifiedAcademicReport($application) : null, 'documentRequirements' => $screening->documents($application), 'canReview' => $workflow->canReview($request->user(), $application), 'ownEvaluation' => $own, 'evaluationCount' => $application->evaluations()->count(), 'juryCount' => $application->vacancy->jurors()->count(), 'evaluationTemplate' => $application->recruitmentCall->evaluation_template, 'evaluationProgress' => $nombramiento->progress($application, $request->user())]);
    }

    public function eligibility(Request $request, Application $application, ApplicationWorkflow $workflow, ApplicantScreening $screening): RedirectResponse
    {
        abort_unless($workflow->canReview($request->user(), $application), 404);
        $data = $request->validate(['notes' => ['required', 'string', 'min:5', 'max:3000'], 'documents' => ['required', 'array'], 'documents.*' => ['required', Rule::in(['CUMPLE', 'NO_CUMPLE'])], 'academic_profile_confirmed' => ['sometimes', 'boolean']]);
        DB::transaction(function () use ($application, $data, $screening) {
            $call = RecruitmentCall::lockForUpdate()->findOrFail($application->recruitment_call_id);
            $locked = Application::lockForUpdate()->findOrFail($application->id);
            abort_unless($call->status === 'EN_EVALUACION' && $locked->status === 'RECIBIDA', 409);
            $locked->load('vacancy', 'documents');
            $usesCredentials = $locked->vacancy->screening_rules['academic_credentials'] ?? false;
            if ($usesCredentials && ! array_key_exists('academic_profile_confirmed', $data)) {
                throw ValidationException::withMessages(['academic_profile_confirmed' => 'Indica si los sustentos acreditan el perfil académico de la plaza.']);
            }
            $eligible = true;
            foreach ($screening->documents($locked) as $requirement) {
                $requiredForAptitude = $requirement['required_for_aptitude'] ?? $requirement['required'];
                $document = $locked->documents->firstWhere('requirement_key', $requirement['key']);
                if (! $document) {
                    if ($requiredForAptitude) {
                        $eligible = false;
                    }

                    continue;
                }
                $verdict = $data['documents'][$document->id] ?? null;
                if (! $verdict) {
                    throw ValidationException::withMessages(['documents' => 'Revisa todos los documentos adjuntos.']);
                }
                if ($requiredForAptitude && $verdict === 'NO_CUMPLE') {
                    $eligible = false;
                }
                $document->update(['review_status' => $verdict, 'review_notes' => $data['notes']]);
            }
            $verifiedReport = $screening->verifiedAcademicReport($locked);
            $eligible = $eligible && ($verifiedReport['eligible'] ?? true) && (! $usesCredentials || $data['academic_profile_confirmed']);
            $locked->update(['status' => $eligible ? 'APTO' : 'NO_APTO', 'review_notes' => $data['notes']]);
            AuditEvent::record('APTITUD_REVISADA', $locked, ['status' => $locked->status, 'academic_profile_confirmed' => $usesCredentials ? (bool) $data['academic_profile_confirmed'] : null]);
        });

        return back()->with('success', 'Revisión de requisitos registrada.');
    }

    public function evaluate(Request $request, Application $application, ApplicationWorkflow $workflow, NombramientoEvaluation $nombramiento): RedirectResponse
    {
        abort_unless($workflow->canReview($request->user(), $application), 404);
        $data = $request->validate([
            'stage' => ['sometimes', Rule::in(['externa', 'interna'])],
            'action' => ['sometimes', Rule::in(['proponer', 'confirmar'])],
            'revision' => ['sometimes', 'integer', 'min:0'],
            'scores' => ['sometimes', 'array', 'max:30'],
            'item_scores' => ['sometimes', 'array', 'max:3'],
            'notes' => ['required', 'string', 'min:5', 'max:3000'],
        ]);
        $message = DB::transaction(function () use ($request, $application, $data, $nombramiento): string {
            $call = RecruitmentCall::lockForUpdate()->findOrFail($application->recruitment_call_id);
            $locked = Application::lockForUpdate()->findOrFail($application->id);
            abort_unless($call->status === 'EN_EVALUACION' && $locked->status === 'APTO', 409);
            if (! $call->is_demo) {
                throw ValidationException::withMessages(['scores' => 'El motor de evaluación oficial debe validarse antes de calificar procesos reales.']);
            }
            if ($call->evaluation_template) {
                $locked->setRelation('recruitmentCall', $call);

                return $nombramiento->record($locked, $request->user(), $data);
            }
            if ($locked->evaluations()->where('user_id', $request->user()->id)->exists()) {
                throw ValidationException::withMessages(['scores' => 'Tu calificación ya está cerrada.']);
            }
            $scores = [];
            $totalMilli = 0;
            foreach ($call->rubric as $criterion) {
                $value = $data['scores'][$criterion['key']] ?? null;
                if (! is_numeric($value) || $value < 0 || $value > $criterion['max'] || ! preg_match('/^\d+(\.\d{1,3})?$/', (string) $value)) {
                    throw ValidationException::withMessages(['scores' => 'Ingresa puntajes válidos dentro del máximo de cada rubro (hasta 3 decimales).']);
                }
                $scores[$criterion['key']] = (string) $value;
                $totalMilli += (int) round((float) $value * 1000);
            }
            $locked->evaluations()->create(['user_id' => $request->user()->id, 'scores' => $scores, 'total' => $totalMilli / 1000, 'notes' => $data['notes'], 'closed_at' => now()]);
            if ($locked->evaluations()->count() === $locked->vacancy->jurors()->count()) {
                $locked->update(['status' => 'EVALUADA']);
            }
            AuditEvent::record('CALIFICACION_CERRADA', $locked, ['juror_id' => $request->user()->id]);

            return 'Calificación cerrada. Se consolidará cuando todos los jurados terminen.';
        });

        return back()->with('success', $message);
    }
}
