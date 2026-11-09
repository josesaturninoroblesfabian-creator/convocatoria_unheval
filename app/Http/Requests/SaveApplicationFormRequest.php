<?php

namespace App\Http\Requests;

use App\Services\ApplicantScreening;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveApplicationFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless($this->user()?->id === $this->route('application')->user_id, 404);

        return true;
    }

    public function rules(): array
    {
        $screening = $this->route('application')->vacancy->screening_rules;
        $usesCredentials = $screening['academic_credentials'] ?? false;
        $rules = [
            'statement' => ['nullable', 'string', 'max:4000'], 'consent' => ['sometimes', 'boolean'],
            'degree' => ['nullable', Rule::in(array_keys(ApplicantScreening::DEGREES))], 'professional_title' => ['nullable', 'string', 'max:250'], 'bachelor_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'experience' => ['present', 'array', 'max:50'], 'experience.*' => ['array:id,institution,position,type,start_date,end_date'],
            'experience.*.id' => ['required', 'uuid', 'distinct'], 'experience.*.institution' => ['required', 'string', 'max:200'], 'experience.*.position' => ['required', 'string', 'max:200'], 'experience.*.type' => ['required', Rule::in(['profesional', 'docente'])], 'experience.*.start_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'experience.*.end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:experience.*.start_date', 'before_or_equal:today'],
            'training' => ['present', 'array', 'max:50'], 'training.*' => ['array:id,name,institution,date,hours'], 'training.*.id' => ['required', 'uuid', 'distinct'], 'training.*.name' => ['required', 'string', 'max:250'], 'training.*.institution' => ['required', 'string', 'max:200'], 'training.*.date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'training.*.hours' => ['required', 'integer', 'between:1,5000'], 'answers' => ['present', 'array'],
        ];
        if ($usesCredentials) {
            unset($rules['degree'], $rules['professional_title'], $rules['bachelor_date']);
            $rules += self::credentialRules();
        }
        foreach ($screening['fields'] ?? [] as $field) {
            $fieldRules = ['nullable'];
            if ($field['type'] === 'select') {
                $fieldRules[] = Rule::in($field['options']);
            } elseif ($field['type'] === 'number') {
                $fieldRules[] = 'numeric';
                $fieldRules[] = 'min:0';
                $fieldRules[] = 'max:100000';
            } elseif ($field['type'] === 'date') {
                $fieldRules[] = 'date_format:Y-m-d';
                $fieldRules[] = 'before_or_equal:today';
            } else {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:1000';
            }
            $rules['answers.'.$field['key']] = $fieldRules;
        }

        return $rules;
    }

    public static function credentialRules(array $additionalKinds = []): array
    {
        return [
            'credentials' => ['present', 'array', 'list', 'max:30'],
            'credentials.*' => ['array:id,kind,name,institution,awarded_at'],
            'credentials.*.id' => ['required', 'uuid', 'distinct:ignore_case'],
            'credentials.*.kind' => ['required', Rule::in([...array_keys(ApplicantScreening::CREDENTIAL_LEVELS), ...$additionalKinds])],
            'credentials.*.name' => ['required', 'string', 'max:250'],
            'credentials.*.institution' => ['required', 'string', 'max:200'],
            'credentials.*.awarded_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return ['experience.*.end_date.after_or_equal' => 'La fecha de fin no puede ser anterior al inicio del servicio.', '*.before_or_equal' => 'No se admiten fechas futuras.', 'experience.*.start_date.before_or_equal' => 'La experiencia no puede comenzar en una fecha futura.', 'experience.*.end_date.before_or_equal' => 'La experiencia declarada no puede terminar en una fecha futura.'];
    }
}
