<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     * CA-01 y CA-02: Campos obligatorios y validación de campos vacíos o sólo espacios.
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (trim((string) $value) === '') {
                        $fail('El correo electrónico es obligatorio.');
                    }
                },
            ],
            'password' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (trim((string) $value) === '') {
                        $fail('La contraseña es obligatoria.');
                    }
                },
            ],
        ];
    }

    /**
     * Mensajes específicos para validación de campos.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'El correo electrónico es obligatorio.',
            'password.required' => 'La contraseña es obligatoria.',
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     * CA-03: Normalización de correo (trim + case-insensitive) y contraseña exacta sin recorte.
     * CA-05: Mensaje unificado idéntico ante fallo de credenciales.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $rawEmail = (string) $this->input('email');
        $normalizedEmail = Str::lower(trim($rawEmail));
        $rawPassword = (string) $this->input('password');

        if (! Auth::attempt(['email' => $normalizedEmail, 'password' => $rawPassword], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Correo electrónico o contraseña incorrectos.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        $rawEmail = (string) $this->input('email');
        return Str::transliterate(Str::lower(trim($rawEmail)).'|'.$this->ip());
    }
}
