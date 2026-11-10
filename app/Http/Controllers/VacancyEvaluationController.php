<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveVacancyEvaluationRequest;
use App\Models\AuditEvent;
use App\Models\RecruitmentCall;
use App\Models\Vacancy;
use App\Services\ExternalEvaluationRubric;
use App\Services\InternalEvaluationRubric;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VacancyEvaluationController extends Controller
{
    public function edit(Vacancy $vacancy, string $stage): Response
    {
        $call = $vacancy->recruitmentCall;

        return Inertia::render('VacancyEvaluation', [
            'stage' => $stage,
            'call' => $call->only('modality', 'status'),
            'vacancy' => $vacancy->only('id', 'faculty', 'school', 'airhsp', 'category', 'dedication', 'weekly_hours', 'public_documents'),
            'template' => $this->template($call, $vacancy, $stage),
            'configuration' => $vacancy->getAttribute($stage.'_evaluation'),
            'links' => [
                'call' => route('management.calls.show', $call, false),
                'vacancy' => route('management.vacancies.edit', $vacancy, false),
                'save' => route('management.vacancies.'.$stage.'.update', $vacancy, false),
                'other' => route('management.vacancies.'.($stage === 'internal' ? 'external' : 'internal').'.edit', $vacancy, false),
            ],
        ]);
    }

    public function update(SaveVacancyEvaluationRequest $request, Vacancy $vacancy, string $stage): RedirectResponse
    {
        $data = $request->validated();
        $title = $stage === 'internal' ? 'Aspectos internos' : 'Aspectos externos';

        DB::transaction(function () use ($vacancy, $data, $stage): void {
            $call = RecruitmentCall::lockForUpdate()->findOrFail($vacancy->recruitment_call_id);
            abort_unless($call->status === 'BORRADOR', 409);
            $vacancy = Vacancy::lockForUpdate()->findOrFail($vacancy->id);
            $template = $this->template($call, $vacancy, $stage);
            if (! $template || $data['category'] !== $vacancy->category || $data['version'] !== $template['version']) {
                throw ValidationException::withMessages(['category' => 'La categoría o la plantilla cambió. Vuelve a abrir la ficha de evaluación.']);
            }
            $column = $stage.'_evaluation';
            $saved = $vacancy->getAttribute($column);
            if ($data['revision'] !== ($saved['revision'] ?? $saved['configured_at'] ?? null)) {
                throw ValidationException::withMessages(['revision' => 'Se guardó otra versión de esta rúbrica. Vuelve a abrir la ficha antes de editarla.']);
            }

            $rubrics = $this->rubrics($data['rubrics'], $stage === 'internal');
            $maximum = round(array_sum(array_column($rubrics, 'max')), 2);
            $minimum = (float) $data['minimum'];
            if ($minimum > $maximum) {
                throw ValidationException::withMessages(['minimum' => 'El mínimo no puede superar la suma de los topes de los rubros.']);
            }

            $reference = $data['reference'] ?? null;
            $rules = $this->rubricRules($rubrics);
            $defaults = $this->rubricRules($template['rubrics']);
            if (($rules != $defaults || $minimum !== (float) $template['minimum']) && $reference === null) {
                throw ValidationException::withMessages(['reference' => 'Indica la resolución o el documento aprobado que respalda los cambios de la rúbrica.']);
            }

            $previous = $saved ?? $template;
            $previousRules = $this->rubricRules($previous['rubrics']);
            $vacancy->update([$column => [
                ...$template,
                'rubrics' => $rubrics,
                'minimum' => $minimum,
                'maximum' => $maximum,
                'reference' => $reference,
                'revision' => (string) Str::uuid(),
                'configured_at' => now()->toIso8601String(),
            ]]);

            AuditEvent::record($stage === 'internal' ? 'ASPECTOS_INTERNOS_CONFIGURADOS' : 'ASPECTOS_EXTERNOS_CONFIGURADOS', $vacancy, [
                'version' => $template['version'],
                'category' => $vacancy->category,
                'reference' => $reference,
                'minimum' => ['previous' => $previous['minimum'], 'current' => $minimum],
                'rubrics_changed' => $previousRules != $rules,
                'rubrics' => $previousRules != $rules ? ['previous' => $previousRules, 'current' => $rules] : null,
            ]);
        });

        return redirect()->route('management.vacancies.'.$stage.'.edit', $vacancy)
            ->with('success', $title.' guardados en el borrador de esta plaza.');
    }

    private function template(RecruitmentCall $call, Vacancy $vacancy, string $stage): ?array
    {
        return $stage === 'internal'
            ? InternalEvaluationRubric::template($call->modality, $vacancy->category)
            : ExternalEvaluationRubric::template($call->modality, $vacancy->category);
    }

    private function rubrics(array $rubrics, bool $internal): array
    {
        foreach ($rubrics as $index => &$rubric) {
            $rubric['guidance'] ??= '';
            $rubric['note'] ??= null;
            $items = &$rubric['items'];
            if (count(array_unique(array_column($items, 'key'))) !== count($items)) {
                throw ValidationException::withMessages(["rubrics.$index.items" => 'El rubro contiene criterios repetidos.']);
            }
            $maximum = 0;
            foreach ($items as $itemIndex => &$item) {
                $path = "rubrics.$index.items.$itemIndex";
                if ($internal) {
                    foreach ($item['levels'] as &$level) {
                        $level['points'] = (float) $level['points'];
                        $level['description'] ??= null;
                    }
                    unset($level);
                    $points = array_column($item['levels'], 'points');
                    if (count(array_unique($points)) !== count($points)) {
                        throw ValidationException::withMessages([$path.'.levels' => 'Los niveles de un criterio deben tener puntajes distintos.']);
                    }
                    $maximum += max($points);
                } else {
                    $item['points'] = (float) $item['points'];
                    if ($item['points'] > (float) $rubric['max']) {
                        throw ValidationException::withMessages([$path.'.points' => 'El puntaje por unidad no puede superar el máximo del rubro.']);
                    }
                }
            }
            unset($item, $items);
            $rubric['max'] = $internal ? round($maximum, 2) : (float) $rubric['max'];
        }
        unset($rubric);

        return $rubrics;
    }

    private function rubricRules(array $rubrics): array
    {
        return array_map(static fn (array $rubric): array => array_diff_key($rubric, ['note' => true]), $rubrics);
    }
}
