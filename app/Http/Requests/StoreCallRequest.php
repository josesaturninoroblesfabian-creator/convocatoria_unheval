<?php

namespace App\Http\Requests;

use App\Services\NombramientoRubric;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'evaluation_mode' => ['nullable', Rule::in(['nombramiento_referencia'])],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'schedule' => ['required', 'array', 'min:1', 'max:20'],
            'schedule.*' => ['array:label,date'],
            'schedule.*.label' => ['required', 'string', 'max:150'],
            'schedule.*.date' => ['required', 'string', 'max:100'],
            'rubric' => $this->route('recruitmentCall')
                ? ['present', 'array', 'max:30']
                : ['required', 'array', 'min:1', 'max:30'],
            'rubric.*' => ['array:key,name,max'],
            'rubric.*.key' => ['required', 'alpha_dash', 'distinct', 'max:30'],
            'rubric.*.name' => ['required', 'string', 'max:150'],
            'rubric.*.max' => ['required', 'numeric', 'min:0.001', 'max:100'],
            'is_demo' => ['required', 'boolean'],
        ];
    }

    public function callData(?array $currentTemplate = null): array
    {
        $data = $this->validated();
        $mode = array_key_exists('evaluation_mode', $data)
            ? $data['evaluation_mode']
            : ($currentTemplate ? 'nombramiento_referencia' : null);
        unset($data['evaluation_mode']);

        $data['evaluation_template'] = null;
        if ($mode === 'nombramiento_referencia') {
            if ($data['modality'] !== 'NOMBRAMIENTO') {
                throw ValidationException::withMessages(['evaluation_mode' => 'La plantilla corresponde a nombramiento docente.']);
            }
            $template = $currentTemplate ?? NombramientoRubric::template();
            $data['evaluation_template'] = $template;
            $data['rubric'] = array_map(
                fn (array $criterion): array => array_intersect_key($criterion, array_flip(['key', 'name', 'max'])),
                [...$template['external'], ...$template['internal']],
            );
        }

        return $data;
    }

    public function messages(): array
    {
        return [
            'closes_at.after' => 'El cierre debe ser posterior al inicio.',
            'code.unique' => 'Ya existe una convocatoria con ese código.',
        ];
    }
}
