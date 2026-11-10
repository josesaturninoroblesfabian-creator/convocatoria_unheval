<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveVacancyEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    public function rules(): array
    {
        $internal = $this->route('stage') === 'internal';
        $points = ['bail', 'required', 'numeric', 'decimal:0,2', 'between:0,100'];

        return [
            'category' => ['required', 'string', 'max:30'],
            'version' => ['required', 'string', 'max:80'],
            'revision' => ['present', 'nullable', 'string', 'max:80'],
            'reference' => ['nullable', 'string', 'max:500'],
            'minimum' => ['bail', 'required', 'numeric', 'decimal:0,2', 'min:0'],
            'rubrics' => ['required', 'array', 'list', 'max:30'],
            'rubrics.*' => [$internal ? 'array:key,name,guidance,note,items' : 'array:key,name,max,guidance,note,items'],
            'rubrics.*.key' => ['required', 'alpha_dash:ascii', 'max:50', 'distinct'],
            'rubrics.*.name' => ['required', 'string', 'max:180'],
            'rubrics.*.guidance' => ['nullable', 'string', 'max:2000'],
            'rubrics.*.note' => ['nullable', 'string', 'max:1000'],
            'rubrics.*.items' => ['required', 'array', 'list', 'max:30'],
            'rubrics.*.items.*' => [$internal ? 'array:key,name,levels' : 'array:key,name,unit,points'],
            'rubrics.*.items.*.key' => ['required', 'alpha_dash:ascii', 'max:50'],
            'rubrics.*.items.*.name' => ['required', 'string', 'max:250'],
            ...($internal ? [
                'rubrics.*.items.*.levels' => ['required', 'array', 'list', 'max:10'],
                'rubrics.*.items.*.levels.*' => ['array:points,description'],
                'rubrics.*.items.*.levels.*.points' => $points,
                'rubrics.*.items.*.levels.*.description' => ['nullable', 'string', 'max:500'],
            ] : [
                'rubrics.*.max' => ['bail', 'required', 'numeric', 'decimal:0,2', 'between:0.01,100'],
                'rubrics.*.items.*.points' => $points,
                'rubrics.*.items.*.unit' => ['required', 'string', 'max:180'],
            ]),
        ];
    }

    public function messages(): array
    {
        return [
            'minimum.required' => 'Indica el mínimo de la etapa.',
            'minimum.decimal' => 'El mínimo admite hasta dos decimales.',
            'rubrics.required' => 'Añade al menos un rubro.',
            'rubrics.*.name.required' => 'Indica el nombre del rubro.',
            'rubrics.*.items.required' => 'Cada rubro debe tener al menos un criterio.',
            'rubrics.*.items.*.name.required' => 'Indica el nombre del criterio.',
            'rubrics.*.items.*.unit.required' => 'Indica cómo se cuenta el criterio.',
            'rubrics.*.items.*.levels.required' => 'Cada criterio debe tener al menos un nivel de puntaje.',
            'rubrics.*.items.*.points.decimal' => 'Los puntajes admiten hasta dos decimales.',
            'rubrics.*.items.*.levels.*.points.decimal' => 'Los puntajes de los niveles admiten hasta dos decimales.',
        ];
    }
}
