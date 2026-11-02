<?php

namespace App\Services;

use App\Models\Application;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class ApplicantScreening
{
    public const DEGREES = ['titulo' => 1, 'maestro' => 2, 'doctor' => 3];

    public function assess(Application $application): array
    {
        if ($application->submitted_at && $application->screening_snapshot) {
            return $application->screening_snapshot;
        }
        $rules = $application->vacancy->screening_rules;
        if (! ($rules['enabled'] ?? false)) {
            return ['enabled' => false, 'eligible' => true, 'issues' => [], 'checks' => [], 'total' => 0];
        }
        $data = $application->form_data ?? [];
        $cutoff = CarbonImmutable::today()->min(CarbonImmutable::parse($application->recruitmentCall->closes_at)->startOfDay());
        $endReference = $cutoff->addDay();
        $checks = [];
        $checks[] = ['label' => 'Título profesional declarado', 'passed' => trim($data['professional_title'] ?? '') !== ''];
        $degree = $data['degree'] ?? '';
        $requiredDegree = $rules['minimum_degree'];
        $checks[] = ['label' => 'Grado académico mínimo: '.$this->degreeLabel($requiredDegree), 'passed' => (self::DEGREES[$degree] ?? 0) >= (self::DEGREES[$requiredDegree] ?? 1)];
        $bachelor = empty($data['bachelor_date']) ? null : CarbonImmutable::parse($data['bachelor_date'])->startOfDay();
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
        $academic = (float) ($rules['degree_points'][$degree] ?? 0);
        $experience = min($years * (float) $rules['experience_points_per_year'], (float) $rules['experience_cap']);
        $training = min(floor($trainingHours / (int) $rules['training_hours_per_point']), (float) $rules['training_cap']);
        $issues = array_values(array_map(fn ($check) => $check['label'], array_filter($checks, fn ($check) => ! $check['passed'])));

        return ['enabled' => true, 'eligible' => ! $issues, 'checks' => $checks, 'issues' => $issues, 'cutoff' => $cutoff->toDateString(), 'experience_days' => $days, 'required_days' => $requiredDays, 'complete_years' => $years, 'training_hours' => $trainingHours, 'academic_points' => $academic, 'experience_points' => $experience, 'training_points' => $training, 'total' => round($academic + $experience + $training, 3), 'rules' => $rules];
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
        if (! ($application->vacancy->screening_rules['enabled'] ?? false)) {
            return $requirements;
        }
        $data = $application->form_data ?? [];
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

        return $requirements;
    }

    public function degreeLabel(string $degree): string
    {
        return ['titulo' => 'Título profesional', 'maestro' => 'Maestro / Magíster', 'doctor' => 'Doctor'][$degree] ?? $degree;
    }
}
