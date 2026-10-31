<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetLinkRequest extends FormRequest
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
     * Elimina espacios externos y convierte el correo a minúsculas antes de validar.
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
            'email' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    // Si está vacío o contiene únicamente espacios
                    if (trim((string) $value) === '') {
                        $fail('Ingresa tu correo electrónico institucional');
                        return;
                    }

                    // Formato de correo y exigencia del dominio exacto @merida.tecnm.mx
                    $normalized = strtolower(trim((string) $value));
                    if (!filter_var($normalized, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9._%+-]+@merida\.tecnm\.mx$/i', $normalized)) {
                        $fail('Ingresa un correo institucional válido con dominio @merida.tecnm.mx');
                    }
                },
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Ingresa tu correo electrónico institucional',
            'email.string' => 'Ingresa tu correo electrónico institucional',
        ];
    }

    /**
     * Ensure the password reset request is not rate limited.
     * Protege el endpoint contra abuso mediante limitación de solicitudes sin revelar la existencia de cuentas.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Registra un intento en el limitador de tasa.
     */
    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), 60);
    }

    /**
     * Limpia el registro de limitación de tasa para pruebas o reset intencional.
     */
    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Clave del limitador de tasa basada en IP para mitigar fuerza bruta sin revelar cuentas.
     */
    public function throttleKey(): string
    {
        return Str::transliterate('password_reset|' . $this->ip());
    }
}

