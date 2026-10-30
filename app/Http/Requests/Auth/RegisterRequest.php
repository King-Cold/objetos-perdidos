<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'email' => is_string($this->email) ? strtolower(trim($this->email)) : $this->email,
            'phone' => is_string($this->phone) ? trim($this->phone) : $this->phone,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    // Debe contener al menos una letra del alfabeto
                    if (!preg_match('/[\pL]/u', $value)) {
                        $fail('El nombre completo debe contener al menos una letra.');
                        return;
                    }

                    // Acepta letras (incluyendo acentos y ñ), espacios, guiones y apóstrofos
                    if (!preg_match('/^[\pL\s\'\-]+$/u', $value)) {
                        $fail('El nombre completo solo debe contener letras, espacios, guiones y apóstrofos.');
                    }
                },
            ],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'regex:/^[a-zA-Z0-9._%+-]+@merida\.tecnm\.mx$/i',
                'unique:users,email',
            ],
            'phone' => [
                'required',
                'string',
                'regex:/^[0-9]{10}$/',
            ],
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
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre completo es obligatorio.',
            'name.string' => 'El nombre completo debe ser una cadena de texto.',
            'name.max' => 'El nombre completo no debe exceder los 255 caracteres.',

            'email.required' => 'Ingresa un correo institucional válido con dominio @merida.tecnm.mx',
            'email.string' => 'Ingresa un correo institucional válido con dominio @merida.tecnm.mx',
            'email.email' => 'Ingresa un correo institucional válido con dominio @merida.tecnm.mx',
            'email.regex' => 'Ingresa un correo institucional válido con dominio @merida.tecnm.mx',
            'email.unique' => 'Este correo electrónico ya se encuentra registrado',

            'phone.required' => 'El teléfono debe contener exactamente 10 dígitos, sin espacios ni otros caracteres',
            'phone.string' => 'El teléfono debe contener exactamente 10 dígitos, sin espacios ni otros caracteres',
            'phone.regex' => 'El teléfono debe contener exactamente 10 dígitos, sin espacios ni otros caracteres',

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
