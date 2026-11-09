<?php

namespace App\Http\Requests;

use App\Services\ExternalEvaluationRubric;
use Illuminate\Foundation\Http\FormRequest;

class SaveExternalEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    public function rules(): array
    {
        $vacancy = $this->route('vacancy');
        $template = ExternalEvaluationRubric::template($vacancy->recruitmentCall->modality, $vacancy->category);
        abort_if($template === null, 409, 'Corrige la categoría de la plaza antes de configurar la evaluación.');
        $rubrics = $template['rubrics'];
        $rules = [
            'category' => ['required', 'string', 'max:30'],
            'version' => ['required', 'string', 'max:80'],
            'reference' => ['nullable', 'string', 'max:500'],
            'rubrics' => ['required', 'array:'.implode(',', array_column($rubrics, 'key'))],
        ];

        foreach ($rubrics as $rubric) {
            $prefix = 'rubrics.'.$rubric['key'];
            $rules[$prefix] = ['required', 'array:points,note'];
            $rules[$prefix.'.note'] = ['nullable', 'string', 'max:1000'];
            $rules[$prefix.'.points'] = ['required', 'array:'.implode(',', array_column($rubric['items'], 'key'))];
            foreach ($rubric['items'] as $item) {
                $rules[$prefix.'.points.'.$item['key']] = ['bail', 'required', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.$rubric['max']];
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'rubrics.*.points.*.required' => 'Indica el puntaje de este criterio.',
            'rubrics.*.points.*.numeric' => 'Ingresa un número válido.',
            'rubrics.*.points.*.decimal' => 'Usa hasta dos decimales.',
            'rubrics.*.points.*.gt' => 'El puntaje debe ser mayor que cero.',
            'rubrics.*.points.*.max' => 'El puntaje por unidad no puede superar el máximo del rubro.',
            'rubrics.*.note.max' => 'La nota admite hasta 1000 caracteres.',
        ];
    }
}
