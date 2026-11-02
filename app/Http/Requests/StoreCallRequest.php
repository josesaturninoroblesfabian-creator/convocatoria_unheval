<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:60', Rule::unique('recruitment_calls')->ignore($this->route('recruitmentCall'))],
            'title' => ['required', 'string', 'max:220'],
            'description' => ['required', 'string', 'max:5000'],
            'resolution' => ['nullable', 'string', 'max:180'],
            'modality' => ['required', Rule::in(['NOMBRAMIENTO', 'CONTRATO'])],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'schedule' => ['required', 'array', 'min:1', 'max:20'],
            'schedule.*' => ['array:label,date'],
            'schedule.*.label' => ['required', 'string', 'max:150'],
            'schedule.*.date' => ['required', 'string', 'max:100'],
            'rubric' => ['required', 'array', 'min:1', 'max:30'],
            'rubric.*' => ['array:key,name,max'],
            'rubric.*.key' => ['required', 'alpha_dash', 'distinct', 'max:30'],
            'rubric.*.name' => ['required', 'string', 'max:150'],
            'rubric.*.max' => ['required', 'numeric', 'min:0.001', 'max:100'],
            'is_demo' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'closes_at.after' => 'El cierre debe ser posterior al inicio.',
            'code.unique' => 'Ya existe una convocatoria con ese código.',
        ];
    }
}
