<?php

namespace App\Http\Requests;

use App\Models\Vacancy;
use App\Services\VacancyEvaluationWorkflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVacancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    public function after(): array
    {
        return $this->isFirstVacancy() ? [] : ConfigureScreeningRequest::validateFields($this);
    }

    protected function prepareForValidation(): void
    {
        if ($this->isContract() && is_string($this->input('category'))) {
            $hours = Vacancy::CONTRACT_HOURS[$this->input('category')] ?? null;
            if ($hours !== null) {
                $this->merge(['weekly_hours' => $hours, 'dedication' => 'CONTRATO']);
            }
        }
    }

    public function rules(): array
    {
        $vacancy = $this->route('vacancy');
        $call = $this->route('recruitmentCall') ?? $vacancy?->recruitmentCall;
        $contract = $this->isContract();
        $first = $this->isFirstVacancy();

        return [
            ...($first ? [
                'modality' => ['required', Rule::in(['NOMBRAMIENTO', 'CONTRATO'])],
                'opens_at' => ['required', 'date_format:Y-m-d\TH:i'],
                'closes_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:opens_at'],
                'is_demo' => ['required', 'boolean'],
            ] : [...ConfigureScreeningRequest::configurationRules(), ...self::requirementRules()]),
            'faculty' => ['required', 'string', 'max:180'],
            'school' => ['required', 'string', 'max:180'],
            'airhsp' => ['required', 'string', 'max:30', Rule::unique('vacancies')->where('recruitment_call_id', $call?->id)->ignore($vacancy)],
            'cap' => ['required', 'string', 'max:30'],
            'category' => ['required', Rule::in($contract ? array_keys(Vacancy::CONTRACT_HOURS) : ['AUXILIAR', 'ASOCIADO', 'PRINCIPAL'])],
            'dedication' => ['required', Rule::in($contract ? ['CONTRATO'] : ['TIEMPO COMPLETO', 'DEDICACIÓN EXCLUSIVA', 'TIEMPO PARCIAL'])],
            'weekly_hours' => ['required', 'integer', 'between:1,40'],
            'description' => ['nullable', 'string', 'max:4000'],
            'professional_profile' => [...($first ? [] : ['sometimes']), 'required', 'string', 'max:6000'],
            ...self::scheduleRules(),
            'courses' => $first ? ['required', 'array', 'list', 'min:1', 'max:30'] : ['present', 'array', 'list', 'max:30'],
            'courses.*' => ['array:code,name,ht,hp'],
            'courses.*.code' => ['required', 'string', 'max:30'],
            'courses.*.name' => ['required', 'string', 'max:180'],
            'courses.*.ht' => ['required', 'integer', 'between:0,40'],
            'courses.*.hp' => ['required', 'integer', 'between:0,40'],
        ];
    }

    public static function scheduleRules(): array
    {
        return [
            'schedule' => ['sometimes', 'array', 'list', 'max:20'],
            'schedule.*' => ['array:label,starts_at,ends_at,detail,stage'],
            'schedule.*.stage' => ['nullable', 'distinct', Rule::in(VacancyEvaluationWorkflow::PHASES)],
            'schedule.*.label' => ['required', 'string', 'max:150'],
            'schedule.*.starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'schedule.*.ends_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after_or_equal:schedule.*.starts_at'],
            'schedule.*.detail' => ['nullable', 'string', 'max:300'],
        ];
    }

    private function isFirstVacancy(): bool
    {
        return $this->routeIs('management.calls.first-vacancy');
    }

    private function isContract(): bool
    {
        if ($this->isFirstVacancy()) {
            return $this->input('modality') === 'CONTRATO';
        }

        $call = $this->route('recruitmentCall') ?? $this->route('vacancy')?->recruitmentCall;

        return $call?->modality === 'CONTRATO';
    }

    public static function requirementRules(): array
    {
        return [
            'requirements' => ['sometimes', 'array', 'list', 'max:30'],
            'requirements.*' => ['array:key,label,required'],
            'requirements.*.key' => ['required', 'alpha_dash', 'distinct', 'max:40'],
            'requirements.*.label' => ['required', 'string', 'max:1000'],
            'requirements.*.required' => ['required', 'boolean'],
        ];
    }
}
