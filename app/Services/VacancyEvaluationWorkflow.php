<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Vacancy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VacancyEvaluationWorkflow
{
    public const PHASES = ['aptitude', 'external', 'internal'];

    public static function configured(Vacancy $vacancy): bool
    {
        return $vacancy->external_evaluation !== null && $vacancy->internal_evaluation !== null;
    }

    public static function template(Application $application): ?array
    {
        $vacancy = $application->vacancy;
        if (! self::configured($vacancy)) {
            return $application->recruitmentCall->evaluation_template;
        }

        $external = $vacancy->external_evaluation;
        $internal = $vacancy->internal_evaluation;

        return [
            'byVacancy' => true,
            'external' => array_map(fn (array $rubric): array => array_diff_key($rubric, ['items' => true]), $external['rubrics']),
            'internal' => $internal['rubrics'],
            'minimums' => [$vacancy->category => [
                'external' => $external['minimum'],
                'internal' => $internal['minimum'],
                'total' => round($external['minimum'] + $internal['minimum'], 2),
            ]],
        ];
    }

    public static function windows(Vacancy $vacancy): array
    {
        return collect($vacancy->schedule ?? [])->filter(fn (array $event): bool => ! empty($event['stage']))->keyBy('stage')->all();
    }

    public static function phaseOpen(Vacancy $vacancy, string $phase): bool
    {
        if (! self::configured($vacancy)) {
            return true;
        }

        $window = self::windows($vacancy)[$phase] ?? null;
        $prerequisite = match ($phase) {
            'external' => 'aptitude',
            'internal' => 'external',
            default => null,
        };

        return $window && ($prerequisite === null || isset($vacancy->stage_publications[$prerequisite])) && now()->betweenIncluded(
            CarbonImmutable::parse($window['starts_at']),
            CarbonImmutable::parse($window['ends_at']),
        );
    }

    public static function phaseEnded(Vacancy $vacancy, string $phase): bool
    {
        $endsAt = self::windows($vacancy)[$phase]['ends_at'] ?? null;

        return $endsAt !== null && now()->gt(CarbonImmutable::parse($endsAt));
    }

    public static function externalPassed(Application $application): bool
    {
        $sheet = $application->external_evaluation;

        return $sheet && count($sheet['confirmations']) === ($application->vacancy->jurors_count ?? $application->vacancy->jurors()->count())
            && $sheet['total'] >= $application->vacancy->external_evaluation['minimum'];
    }

    public static function validCalendar(Vacancy $vacancy, CarbonInterface $closesAt): bool
    {
        $windows = self::windows($vacancy);
        foreach (self::PHASES as $phase) {
            $event = $windows[$phase] ?? [];
            if (empty($event['ends_at']) || CarbonImmutable::parse($event['starts_at'])->lte($closesAt)) {
                return false;
            }
            $closesAt = CarbonImmutable::parse($event['ends_at']);
        }

        return true;
    }

    public static function evidence(Application $application): array
    {
        return $application->form_data['merits'] ?? [];
    }

    public static function applicantView(Application $application): Application
    {
        if (! $application->submitted_at || ! self::configured($application->vacancy)) {
            return $application;
        }
        $publications = $application->vacancy->stage_publications ?? [];
        if (! isset($publications['aptitude'])) {
            $application->status = 'RECIBIDA';
            $application->makeHidden('review_notes');
            if ($application->relationLoaded('documents')) {
                $application->documents->makeHidden(['review_status', 'review_notes']);
            }
        } elseif ($application->status === 'EVALUADA' && ! $application->recruitmentCall->results_published_at
            && (! isset($publications['external']) || ($application->evaluation_outcome['stage'] ?? null) !== 'NO_SUPERA_EXTERNA')) {
            $application->status = 'APTO';
        }

        return $application;
    }

    public static function criteria(Vacancy $vacancy): array
    {
        $criteria = [];
        foreach ($vacancy->external_evaluation['rubrics'] ?? [] as $rubric) {
            foreach ($rubric['items'] as $item) {
                $criteria[$rubric['key'].':'.$item['key']] = [
                    ...$item,
                    'rubric' => $rubric['key'],
                    'rubric_name' => $rubric['name'],
                    'rubric_max' => $rubric['max'],
                ];
            }
        }

        return $criteria;
    }

    public static function defaultMeritsFromFormData(Vacancy $vacancy, array $formData): array
    {
        if (! self::configured($vacancy)) {
            return [];
        }

        $criteria = self::criteria($vacancy);
        $mapping = [
            'doctor' => 'formacion:doctor',
            'maestro' => 'formacion:maestro',
            'bachiller' => 'formacion:bachiller',
            'titulo' => 'formacion:titulo',
            'segunda_especialidad' => 'formacion_servicio:especialidad',
        ];

        $merits = [];
        foreach ($formData['credentials'] ?? [] as $credential) {
            $criterionKey = $mapping[$credential['kind'] ?? ''] ?? null;
            if (! $criterionKey || ! isset($criteria[$criterionKey]) || empty($credential['id'])) {
                continue;
            }
            $merits[] = [
                'id' => (string) Str::uuid(),
                'criterion' => $criterionKey,
                'quantity' => 1,
                'description' => trim(($credential['name'] ?? '').' · '.($credential['institution'] ?? ''), ' ·'),
                'reuse' => 'acad_'.$credential['id'],
            ];
        }

        return $merits;
    }

    public static function scores(Application $application, ?array $validated = null): array
    {
        $vacancy = $application->vacancy;
        $criteria = self::criteria($vacancy);
        $entries = self::evidence($application);
        if ($validated !== null && count($validated) !== count($entries)) {
            throw ValidationException::withMessages(['merits' => 'Indica la cantidad acreditada de cada mérito. Usa cero para un sustento rechazado.']);
        }

        $scores = [];
        foreach ($vacancy->external_evaluation['rubrics'] as $rubric) {
            $scores[$rubric['key']] = 0;
        }
        $exclusive = [];
        foreach ($entries as $entry) {
            $criterion = $criteria[$entry['criterion']];
            $quantity = $validated === null ? $entry['quantity'] : ($validated[$entry['id']] ?? null);
            if (filter_var($quantity, FILTER_VALIDATE_INT) === false || $quantity < 0 || $quantity > $entry['quantity']) {
                throw ValidationException::withMessages(['merits' => 'La cantidad acreditada debe estar entre cero y la cantidad declarada.']);
            }
            $rubric = $criterion['rubric'];
            if (in_array($criterion['unit'], ['Grado más alto', 'Un solo título', 'Categoría más alta'], true)) {
                $unit = $criterion['unit'];
                $exclusive[$rubric][$unit] = max($exclusive[$rubric][$unit] ?? 0, $quantity > 0 ? $criterion['points'] : 0);
            } else {
                $scores[$rubric] += $quantity * $criterion['points'];
            }
        }
        foreach ($vacancy->external_evaluation['rubrics'] as $rubric) {
            $key = $rubric['key'];
            $scores[$key] = min($rubric['max'], round($scores[$key] + array_sum($exclusive[$key] ?? []), 2));
        }

        return $scores;
    }
}
