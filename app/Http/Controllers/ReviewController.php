<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\RecruitmentCall;
use App\Services\ApplicantScreening;
use App\Services\ApplicationWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function show(Request $request, Application $application, ApplicationWorkflow $workflow, ApplicantScreening $screening): Response
    {
        $application->load('vacancy', 'recruitmentCall', 'documents');
        abort_unless($application->submitted_at && ($request->user()->isManager() || $workflow->canReview($request->user(), $application)), 404);
        $own = $application->evaluations()->where('user_id', $request->user()->id)->first();

        return Inertia::render('Review', ['application' => $application, 'screening' => $screening->assess($application), 'documentRequirements' => $screening->documents($application), 'canReview' => $workflow->canReview($request->user(), $application), 'ownEvaluation' => $own, 'evaluationCount' => $application->evaluations()->count(), 'juryCount' => $application->vacancy->jurors()->count()]);
    }

    public function eligibility(Request $request, Application $application, ApplicationWorkflow $workflow, ApplicantScreening $screening): RedirectResponse
    {
        abort_unless($workflow->canReview($request->user(), $application), 404);
        $data = $request->validate(['notes' => ['required', 'string', 'min:5', 'max:3000'], 'documents' => ['required', 'array'], 'documents.*' => ['required', Rule::in(['CUMPLE', 'NO_CUMPLE'])]]);
        DB::transaction(function () use ($application, $data, $screening) {
            $call = RecruitmentCall::lockForUpdate()->findOrFail($application->recruitment_call_id);
            $locked = Application::lockForUpdate()->findOrFail($application->id);
            abort_unless($call->status === 'EN_EVALUACION' && $locked->status === 'RECIBIDA', 409);
            $locked->load('vacancy', 'documents');
            $eligible = true;
            foreach ($screening->documents($locked) as $requirement) {
                $document = $locked->documents->firstWhere('requirement_key', $requirement['key']);
                if (! $document) {
                    if ($requirement['required']) {
                        $eligible = false;
                    }

                    continue;
                }
                $verdict = $data['documents'][$document->id] ?? null;
                if (! $verdict) {
                    throw ValidationException::withMessages(['documents' => 'Revisa todos los documentos adjuntos.']);
                }
                if ($requirement['required'] && $verdict === 'NO_CUMPLE') {
                    $eligible = false;
                }
                $document->update(['review_status' => $verdict, 'review_notes' => $data['notes']]);
            }
            $locked->update(['status' => $eligible ? 'APTO' : 'NO_APTO', 'review_notes' => $data['notes']]);
            AuditEvent::record('APTITUD_REVISADA', $locked, ['status' => $locked->status]);
        });

        return back()->with('success', 'Revisión de requisitos registrada.');
    }

    public function evaluate(Request $request, Application $application, ApplicationWorkflow $workflow): RedirectResponse
    {
        abort_unless($workflow->canReview($request->user(), $application), 404);
        $data = $request->validate(['scores' => ['required', 'array'], 'notes' => ['required', 'string', 'min:5', 'max:3000']]);
        DB::transaction(function () use ($request, $application, $data) {
            $call = RecruitmentCall::lockForUpdate()->findOrFail($application->recruitment_call_id);
            $locked = Application::lockForUpdate()->findOrFail($application->id);
            abort_unless($call->status === 'EN_EVALUACION' && $locked->status === 'APTO', 409);
            if (! $call->is_demo) {
                throw ValidationException::withMessages(['scores' => 'El motor de evaluación oficial debe validarse antes de calificar procesos reales.']);
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
        });

        return back()->with('success', 'Calificación cerrada. Se consolidará cuando todos los jurados terminen.');
    }
}
