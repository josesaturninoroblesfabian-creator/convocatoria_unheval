<?php

namespace App\Http\Requests;

use App\Models\Vacancy;
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
        return ConfigureScreeningRequest::validateFields($this);
    }

    protected function prepareForValidation(): void
    {
        $call = $this->route('recruitmentCall') ?? $this->route('vacancy')?->recruitmentCall;
        if ($call?->modality === 'CONTRATO' && is_string($this->input('category'))) {
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
        $contract = $call?->modality === 'CONTRATO';

        return [
            ...ConfigureScreeningRequest::configurationRules(),
            'faculty' => ['required', 'string', 'max:180'],
            'school' => ['required', 'string', 'max:180'],
            'airhsp' => ['required', 'string', 'max:30', Rule::unique('vacancies')->where('recruitment_call_id', $call?->id)->ignore($vacancy)],
            'cap' => ['required', 'string', 'max:30'],
            'category' => ['required', Rule::in($contract ? array_keys(Vacancy::CONTRACT_HOURS) : ['AUXILIAR', 'ASOCIADO', 'PRINCIPAL'])],
            'dedication' => ['required', Rule::in($contract ? ['CONTRATO'] : ['TIEMPO COMPLETO', 'DEDICACIÓN EXCLUSIVA', 'TIEMPO PARCIAL'])],
            'weekly_hours' => ['required', 'integer', 'between:1,40'],
            'description' => ['nullable', 'string', 'max:4000'],
            'professional_profile' => ['sometimes', 'required', 'string', 'max:6000'],
            'schedule' => ['sometimes', 'array', 'list', 'max:20'],
            'schedule.*' => ['array:label,starts_at,ends_at,detail'],
            'schedule.*.label' => ['required', 'string', 'max:150'],
            'schedule.*.starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'schedule.*.ends_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after_or_equal:schedule.*.starts_at'],
            'schedule.*.detail' => ['nullable', 'string', 'max:300'],
            ...self::requirementRules(),
            'courses' => ['present', 'array', 'list', 'max:30'],
            'courses.*' => ['array:code,name,ht,hp'],
            'courses.*.code' => ['required', 'string', 'max:30'],
            'courses.*.name' => ['required', 'string', 'max:180'],
            'courses.*.ht' => ['required', 'integer', 'between:0,40'],
            'courses.*.hp' => ['required', 'integer', 'between:0,40'],
        ];
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
