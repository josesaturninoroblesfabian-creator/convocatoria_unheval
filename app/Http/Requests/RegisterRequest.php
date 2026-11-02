<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'document_number' => ['required', 'digits:8', 'unique:users'],
            'phone' => ['required', 'regex:/^[0-9+ ()-]{7,20}$/'],
            'email' => ['required', 'email', 'max:150', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'consent' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'document_number.digits' => 'Ingresa un DNI de 8 dígitos.',
            'document_number.unique' => 'Este DNI ya está registrado.',
            'email.unique' => 'Este correo ya está registrado.',
            'consent.accepted' => 'Debes aceptar el tratamiento de tus datos.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }
}
