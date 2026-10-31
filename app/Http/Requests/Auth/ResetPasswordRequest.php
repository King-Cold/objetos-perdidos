<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge([
                'email' => strtolower(trim($this->email)),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:12',
                function ($attribute, $value, $fail) {
                    // Debe incluir al menos una letra mayúscula
                    if (!preg_match('/[A-Z]/', $value)) {
                        $fail('La contraseña debe incluir al menos una letra mayúscula.');
                    }

                    // Debe incluir al menos un número
                    if (!preg_match('/[0-9]/', $value)) {
                        $fail('La contraseña debe incluir al menos un número.');
                    }

                    // Debe incluir al menos un carácter especial (los espacios no cuentan como especial)
                    if (!preg_match('/[^A-Za-z0-9\s]/', $value)) {
                        $fail('La contraseña debe incluir al menos un carácter especial.');
                    }
                },
            ],
            'password_confirmation' => [
                'required',
                'string',
                'same:password',
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'token.required' => 'El token de recuperación es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',

            'password.required' => 'La contraseña es obligatoria.',
            'password.string' => 'La contraseña debe ser una cadena de texto.',
            'password.min' => 'La contraseña debe contener al menos 8 caracteres.',
            'password.max' => 'La contraseña no debe exceder los 12 caracteres.',

            'password_confirmation.required' => 'La confirmación de contraseña es obligatoria.',
            'password_confirmation.string' => 'La confirmación de contraseña debe ser una cadena de texto.',
            'password_confirmation.same' => 'La confirmación de contraseña no coincide',
        ];
    }
}

