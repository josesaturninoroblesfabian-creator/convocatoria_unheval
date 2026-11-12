<?php

namespace App\Services;

use App\Models\Application;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class ApplicantScreening
{
    public const DEGREES = ['titulo' => 1, 'maestro' => 2, 'doctor' => 3];

    public const CREDENTIAL_LEVELS = ['titulo' => 1, 'bachiller' => 1, 'maestro' => 2, 'segunda_especialidad' => 2, 'doctor' => 3];

    public function assess(Application $application): array
    {
        if ($application->submitted_at && $application->screening_snapshot) {
            return $application->screening_snapshot;
        }
        $rules = $application->vacancy->screening_rules;
        if (! ($rules['enabled'] ?? false)) {
            return ['enabled' => false, 'eligible' => true, 'issues' => [], 'checks' => [], 'total' => 0];
        }
        $cutoff = CarbonImmutable::today()->min(CarbonImmutable::parse($application->recruitmentCall->closes_at)->startOfDay());

        return $this->calculate($application->form_data ?? [], $rules, $cutoff);
    }

    public function verifiedAcademicReport(Application $application): ?array
    {
        $report = $this->assess($application);
        if (! ($report['rules']['academic_credentials'] ?? false)) {
            return null;
        }

        $verifiedKeys = $application->documents->where('review_status', 'CUMPLE')->pluck('requirement_key')->all();
        $data = $application->form_data ?? [];
        $data['credentials'] = array_values(array_filter(
            $data['credentials'] ?? [],
            fn (array $credential): bool => in_array('acad_'.$credential['id'], $verifiedKeys, true),
        ));

        return $this->calculate($data, $report['rules'], CarbonImmutable::parse($report['cutoff']));
    }

    private function calculate(array $data, array $rules, CarbonImmutable $cutoff): array
    {
        $endReference = $cutoff->addDay();
        $checks = [];
        $degree = $data['degree'] ?? '';
        $hasTitle = trim($data['professional_title'] ?? '') !== '';
        $degreeLevel = self::DEGREES[$degree] ?? 0;
        $bachelorDate = $data['bachelor_date'] ?? null;
        $academic = (float) ($rules['degree_points'][$degree] ?? 0);
        if ($rules['academic_credentials'] ?? false) {
            $formation = $this->formation($data['credentials'] ?? [], $rules, $cutoff);
            $hasTitle = $formation['has_title'];
            $degreeLevel = $formation['degree_level'];
            $bachelorDate = $formation['bachelor_date'];
            $academic = $formation['points'];
        }

        $checks[] = ['label' => 'Título profesional declarado', 'passed' => $hasTitle];
        $requiredDegree = $rules['minimum_degree'];
        $requiredLevel = self::DEGREES[$requiredDegree] ?? 1;
        $doctorNote = $degreeLevel === self::CREDENTIAL_LEVELS['doctor'] && $degreeLevel > $requiredLevel
            ? ' (acredita Doctor · '.($rules['degree_points']['doctor'] ?? 8).' pts)'
            : '';
        $checks[] = ['label' => 'Grado académico mínimo: '.$this->degreeLabel($requiredDegree).$doctorNote, 'passed' => $degreeLevel >= $requiredLevel];
        $bachelor = $bachelorDate ? CarbonImmutable::parse($bachelorDate)->startOfDay() : null;
        if ($rules['count_from_bachelor']) {
            $checks[] = ['label' => 'Fecha de obtención del bachiller registrada', 'passed' => $bachelor !== null && $bachelor->lte($cutoff)];
        }
        $ranges = [];
        foreach ($data['experience'] ?? [] as $period) {
            if ($rules['experience_type'] !== 'toda' && $period['type'] !== $rules['experience_type']) {
                continue;
            }
            $start = CarbonImmutable::parse($period['start_date'])->startOfDay();
            $end = empty($period['end_date']) ? $cutoff : CarbonImmutable::parse($period['end_date'])->startOfDay()->min($cutoff);
            if ($rules['count_from_bachelor']) {
                if (! $bachelor) {
                    continue;
                } $start = $start->max($bachelor);
            }
            if ($start->lte($end)) {
                $ranges[] = [$start, $end->addDay()];
            }
        }
        usort($ranges, fn ($left, $right) => $left[0] <=> $right[0]);
        $merged = [];
        foreach ($ranges as [$start,$end]) {
            $last = count($merged) - 1;
            if ($last >= 0 && $start->lte($merged[$last][1])) {
                $merged[$last][1] = $merged[$last][1]->max($end);
            } else {
                $merged[] = [$start, $end];
            }
        }
        $days = 0;
        foreach ($merged as [$start,$end]) {
            $days += (int) $start->diffInDays($end);
        }
        $requiredDays = (int) $endReference->subMonthsNoOverflow((int) $rules['minimum_experience_months'])->diffInDays($endReference);
        $checks[] = ['label' => 'Experiencia mínima: '.$rules['minimum_experience_months'].' meses ('.$requiredDays.' días al corte)', 'passed' => $days >= $requiredDays];
        $trainingHours = array_sum(array_column($data['training'] ?? [], 'hours'));
        $checks[] = ['label' => 'Capacitación mínima: '.$rules['minimum_training_hours'].' horas', 'passed' => $trainingHours >= $rules['minimum_training_hours']];
        foreach ($rules['fields'] ?? [] as $field) {
            $value = $data['answers'][$field['key']] ?? null;
            $present = $value !== null && $value !== '';
            $passed = ! $field['required'] || $present;
            if ($present && ($field['expected_value'] ?? '') !== '') {
                $passed = (string) $value === (string) $field['expected_value'];
            }
            $checks[] = ['label' => $field['label'].(($field['expected_value'] ?? '') !== '' ? ' · Requerido: '.$field['expected_value'] : ''), 'passed' => $passed];
        }
        $years = 0;
        while ($years < 100 && (int) $endReference->subYearsNoOverflow($years + 1)->diffInDays($endReference) <= $days) {
            $years++;
        }
        $experience = min($years * (float) $rules['experience_points_per_year'], (float) $rules['experience_cap']);
        $training = min(floor($trainingHours / (int) $rules['training_hours_per_point']), (float) $rules['training_cap']);
        $issues = array_values(array_map(fn ($check) => $check['label'], array_filter($checks, fn ($check) => ! $check['passed'])));

        return ['enabled' => true, 'eligible' => ! $issues, 'checks' => $checks, 'issues' => $issues, 'cutoff' => $cutoff->toDateString(), 'experience_days' => $days, 'required_days' => $requiredDays, 'complete_years' => $years, 'training_hours' => $trainingHours, 'academic_points' => $academic, 'experience_points' => $experience, 'training_points' => $training, 'total' => round($academic + $experience + $training, 3), 'rules' => $rules];
    }

    private function formation(array $credentials, array $rules, CarbonImmutable $cutoff): array
    {
        $hasTitle = false;
        $degreeLevel = 0;
        $bachelorDate = null;
        $degreePoints = 0.0;
        $points = $rules['degree_points'] + ['bachiller' => 1, 'segunda_especialidad' => 6];

        foreach ($credentials as $credential) {
            $kind = $credential['kind'];
            $date = $credential['awarded_at'];
            if ($date > $cutoff->toDateString() || ($kind === 'segunda_especialidad' && ! ($rules['allow_residency'] ?? false))) {
                continue;
            }
            $degreeLevel = max($degreeLevel, self::CREDENTIAL_LEVELS[$kind]);
            if ($kind === 'titulo') {
                $hasTitle = true;

                continue;
            }
            if ($kind === 'bachiller' && ($bachelorDate === null || $date < $bachelorDate)) {
                $bachelorDate = $date;
            }
            $degreePoints = max($degreePoints, (float) $points[$kind]);
        }

        return [
            'has_title' => $hasTitle,
            'degree_level' => $degreeLevel,
            'bachelor_date' => $bachelorDate,
            'points' => min(10.0, $degreePoints + ($hasTitle ? (float) $points['titulo'] : 0)),
        ];
    }

    public function assertEligible(Application $application): array
    {
        $report = $this->assess($application);
        if (! $report['eligible']) {
            throw ValidationException::withMessages(['application' => 'Aún no puedes continuar: '.implode('; ', $report['issues']).'. Corrige o completa lo declarado.']);
        }

        return $report;
    }

    public function documents(Application $application): array
    {
        $requirements = $application->vacancy->requirements;
        $byVacancy = VacancyEvaluationWorkflow::configured($application->vacancy);
        if ($byVacancy) {
            $requirements = array_map(fn (array $requirement): array => $requirement['key'] === 'silabo_interno'
                ? [...$requirement, 'required_for_aptitude' => false] : $requirement, $requirements);
        }
        if ($application->vacancy->screening_rules['enabled'] ?? false) {
            $data = $application->form_data ?? [];
            if ($application->vacancy->screening_rules['academic_credentials'] ?? false) {
                $requirements = array_values(array_filter($requirements, fn ($item) => ! in_array($item['key'], ['titulo', 'grado', 'bachiller'], true)));
                foreach ($data['credentials'] ?? [] as $credential) {
                    $requirements[] = [
                        'key' => 'acad_'.$credential['id'],
                        'label' => $this->degreeLabel($credential['kind']).': '.$credential['name'].' · '.$credential['institution'],
                        'required' => true,
                        'required_for_aptitude' => false,
                    ];
                }
            } else {
                $keys = array_column($requirements, 'key');
                if (! in_array('titulo', $keys, true)) {
                    $requirements[] = ['key' => 'titulo', 'label' => 'Título profesional declarado', 'required' => true];
                }
                if (in_array($data['degree'] ?? '', ['maestro', 'doctor'], true) && ! in_array('grado', $keys, true)) {
                    $requirements[] = ['key' => 'grado', 'label' => 'Sustento del grado académico declarado', 'required' => true];
                }
                if ($application->vacancy->screening_rules['count_from_bachelor'] && ! in_array('bachiller', $keys, true)) {
                    $requirements[] = ['key' => 'bachiller', 'label' => 'Grado de bachiller con fecha de obtención', 'required' => true];
                }
            }
            if (! empty($data['experience'])) {
                $requirements = array_values(array_filter($requirements, fn ($item) => $item['key'] !== 'experiencia'));
            }
            foreach ($data['experience'] ?? [] as $period) {
                $requirements[] = ['key' => 'exp_'.$period['id'], 'label' => 'Sustento de experiencia: '.$period['institution'].' · '.$period['start_date'].' a '.($period['end_date'] ?? 'la actualidad'), 'required' => true];
            }
            foreach ($data['training'] ?? [] as $course) {
                $requirements[] = ['key' => 'cap_'.$course['id'], 'label' => 'Certificado: '.$course['name'].' · '.$course['hours'].' horas', 'required' => true];
            }
            foreach ($application->vacancy->screening_rules['fields'] ?? [] as $field) {
                if (($field['requires_document'] ?? false) && ($data['answers'][$field['key']] ?? '') !== '') {
                    $requirements[] = ['key' => 'campo_'.$field['key'], 'label' => 'Sustento: '.$field['label'], 'required' => true];
                }
            }
        }
        if ($byVacancy) {
            foreach (VacancyEvaluationWorkflow::evidence($application) as $merit) {
                if (empty($merit['reuse'])) {
                    $requirements[] = ['key' => 'merito_'.$merit['id'], 'label' => 'Mérito: '.$merit['description'], 'required' => true, 'required_for_aptitude' => false];
                }
            }
        }

        return $requirements;
    }

    public function degreeLabel(string $degree): string
    {
        return ['titulo' => 'Título profesional', 'bachiller' => 'Bachiller', 'maestro' => 'Maestro / Magíster', 'doctor' => 'Doctor', 'segunda_especialidad' => 'Segunda especialidad por residentado'][$degree] ?? $degree;
    }
}
