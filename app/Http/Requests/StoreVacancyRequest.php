<?php

namespace App\Http\Requests;

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

    public function rules(): array
    {
        return [
            ...ConfigureScreeningRequest::configurationRules(),
            'faculty' => ['required', 'string', 'max:180'],
            'school' => ['required', 'string', 'max:180'],
            'airhsp' => ['required', 'string', 'max:30', Rule::unique('vacancies')->where('recruitment_call_id', $this->route('recruitmentCall')?->id)],
            'cap' => ['required', 'string', 'max:30'],
            'category' => ['required', Rule::in(['AUXILIAR', 'ASOCIADO', 'PRINCIPAL', 'DC A', 'DC B'])],
            'dedication' => ['required', Rule::in(['TIEMPO COMPLETO', 'DEDICACIÓN EXCLUSIVA', 'TIEMPO PARCIAL'])],
            'weekly_hours' => ['required', 'integer', 'between:1,40'],
            'requirements' => ['required', 'array', 'min:1', 'max:30'],
            'requirements.*' => ['array:key,label,required'],
            'requirements.*.key' => ['required', 'alpha_dash', 'distinct', 'max:40'],
            'requirements.*.label' => ['required', 'string', 'max:1000'],
            'requirements.*.required' => ['required', 'boolean'],
            'courses' => ['present', 'array', 'max:30'],
            'courses.*' => ['array:code,name,ht,hp'],
            'courses.*.code' => ['required', 'string', 'max:30'],
            'courses.*.name' => ['required', 'string', 'max:180'],
            'courses.*.ht' => ['required', 'integer', 'between:0,40'],
            'courses.*.hp' => ['required', 'integer', 'between:0,40'],
        ];
    }
}
