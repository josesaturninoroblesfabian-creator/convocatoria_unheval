<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveExternalEvaluationRequest;
use App\Models\AuditEvent;
use App\Models\RecruitmentCall;
use App\Models\Vacancy;
use App\Services\ExternalEvaluationRubric;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VacancyExternalEvaluationController extends Controller
{
    public function edit(Vacancy $vacancy): Response
    {
        $call = $vacancy->recruitmentCall;

        return Inertia::render('VacancyExternalEvaluation', [
            'call' => $call->only('modality', 'status'),
            'vacancy' => $vacancy->only('faculty', 'school', 'airhsp', 'category', 'dedication', 'weekly_hours', 'public_documents'),
            'template' => ExternalEvaluationRubric::template($call->modality, $vacancy->category),
            'configuration' => $vacancy->external_evaluation,
            'links' => [
                'call' => route('management.calls.show', $call, false),
                'vacancy' => route('management.vacancies.edit', $vacancy, false),
                'save' => route('management.vacancies.external.update', $vacancy, false),
                'requirements' => route('management.calls.show', ['recruitmentCall' => $call, 'configurar' => $vacancy->id], false),
            ],
        ]);
    }

    public function update(SaveExternalEvaluationRequest $request, Vacancy $vacancy): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($vacancy, $data): void {
            $call = RecruitmentCall::lockForUpdate()->findOrFail($vacancy->recruitment_call_id);
            abort_unless($call->status === 'BORRADOR', 409);
            $vacancy = Vacancy::lockForUpdate()->findOrFail($vacancy->id);
            $template = ExternalEvaluationRubric::template($call->modality, $vacancy->category);
            if (! $template || $data['category'] !== $vacancy->category || $data['version'] !== $template['version']) {
                throw ValidationException::withMessages(['category' => 'La categoría o la plantilla cambió. Vuelve a abrir los aspectos externos antes de guardar.']);
            }

            $changes = [];
            foreach ($template['rubrics'] as &$rubric) {
                $configured = $data['rubrics'][$rubric['key']];
                $rubric['note'] = $configured['note'] ?? null;
                foreach ($rubric['items'] as &$item) {
                    $points = (float) $configured['points'][$item['key']];
                    if ($points !== (float) $item['points']) {
                        $changes[$rubric['key']][$item['key']] = ['reference' => $item['points'], 'configured' => $points];
                    }
                    $item['points'] = $points;
                }
                unset($item);
            }
            unset($rubric);

            $reference = $data['reference'] ?? null;
            if ($changes && ! $reference) {
                throw ValidationException::withMessages(['reference' => 'Indica la resolución o el documento aprobado que respalda los cambios de puntaje.']);
            }

            $vacancy->update(['external_evaluation' => [
                ...$template,
                'reference' => $reference,
                'configured_at' => now()->toIso8601String(),
            ]]);
            AuditEvent::record('ASPECTOS_EXTERNOS_CONFIGURADOS', $vacancy, [
                'version' => $template['version'],
                'category' => $vacancy->category,
                'reference' => $reference,
                'adjustments' => $changes,
            ]);
        });

        return back()->with('success', 'Aspectos externos guardados en el borrador de esta plaza.');
    }
}
