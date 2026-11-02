<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConfigureScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    public function rules(): array
    {
        return self::configurationRules();
    }

    public function after(): array
    {
        return self::validateFields($this);
    }

    public static function validateFields(FormRequest $request): array
    {
        return [function (Validator $validator) use ($request): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach ($request->input('screening_rules.fields', []) as $index => $field) {
                if ($field['type'] === 'select') {
                    if (! $field['options']) {
                        $validator->errors()->add("screening_rules.fields.$index.options", 'Añade al menos una opción para la pregunta de selección.');
                    }
                    if (($field['expected_value'] ?? '') !== '' && ! in_array($field['expected_value'], $field['options'], true)) {
                        $validator->errors()->add("screening_rules.fields.$index.expected_value", 'La respuesta exigida debe pertenecer a las opciones disponibles.');
                    }
                }
            }
        }];
    }

    public static function configurationRules(): array
    {
        $required = 'required_if:screening_rules.enabled,true';

        return [
            'screening_rules' => ['nullable', 'array:enabled,minimum_degree,minimum_experience_months,experience_type,count_from_bachelor,minimum_training_hours,degree_points,experience_points_per_year,experience_cap,training_hours_per_point,training_cap,fields'],
            'screening_rules.enabled' => ['required_with:screening_rules', 'boolean'],
            'screening_rules.minimum_degree' => [$required, Rule::in(['titulo', 'maestro', 'doctor'])],
            'screening_rules.minimum_experience_months' => [$required, 'integer', 'between:0,600'],
            'screening_rules.experience_type' => [$required, Rule::in(['toda', 'profesional', 'docente'])],
            'screening_rules.count_from_bachelor' => [$required, 'boolean'],
            'screening_rules.minimum_training_hours' => [$required, 'integer', 'between:0,10000'],
            'screening_rules.degree_points' => [$required, 'array:titulo,maestro,doctor'],
            'screening_rules.degree_points.titulo' => [$required, 'numeric', 'between:0,100'],
            'screening_rules.degree_points.maestro' => [$required, 'numeric', 'between:0,100'],
            'screening_rules.degree_points.doctor' => [$required, 'numeric', 'between:0,100'],
            'screening_rules.experience_points_per_year' => [$required, 'numeric', 'between:0,100'],
            'screening_rules.experience_cap' => [$required, 'numeric', 'between:0,100'],
            'screening_rules.training_hours_per_point' => [$required, 'integer', 'between:1,5000'],
            'screening_rules.training_cap' => [$required, 'numeric', 'between:0,100'],
            'screening_rules.fields' => ['sometimes', 'array', 'max:30'],
            'screening_rules.fields.*' => ['array:key,label,type,required,options,expected_value,requires_document'],
            'screening_rules.fields.*.key' => ['required', 'alpha_dash', 'distinct', 'max:30'],
            'screening_rules.fields.*.label' => ['required', 'string', 'max:200'],
            'screening_rules.fields.*.type' => ['required', Rule::in(['text', 'select', 'number', 'date'])],
            'screening_rules.fields.*.required' => ['required', 'boolean'],
            'screening_rules.fields.*.requires_document' => ['required', 'boolean'],
            'screening_rules.fields.*.options' => ['present', 'array', 'max:30'],
            'screening_rules.fields.*.options.*' => ['required', 'string', 'max:100'],
            'screening_rules.fields.*.expected_value' => ['nullable', 'string', 'max:100'],
        ];
    }
}
