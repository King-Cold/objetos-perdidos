<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUbicacionRecepcionRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     * Solo administradores / bibliotecarios pueden cambiar la ubicación física de resguardo.
     */
    public function authorize(): bool
    {
        return auth()->check() && (bool) auth()->user()->isAdmin();
    }

    /**
     * Prepara los datos antes de la validación permitiendo alias "nueva_ubicacion" o "ubicacion_resguardo".
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('nueva_ubicacion') && ! $this->has('ubicacion_resguardo')) {
            $this->merge([
                'ubicacion_resguardo' => $this->input('nueva_ubicacion'),
            ]);
        }
    }

    /**
     * Reglas de validación para la actualización de ubicación interna.
     */
    public function rules(): array
    {
        return [
            'ubicacion_resguardo' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (trim((string) $value) === '') {
                        $fail('La nueva ubicación no puede estar vacía ni contener únicamente espacios.');
                    }
                },
            ],
            'nueva_ubicacion' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Mensajes descriptivos en español para errores de validación.
     */
    public function messages(): array
    {
        return [
            'ubicacion_resguardo.required' => 'La nueva ubicación de resguardo es obligatoria.',
            'ubicacion_resguardo.string' => 'La ubicación debe ser una cadena de texto válida.',
            'ubicacion_resguardo.max' => 'La ubicación no puede superar los 255 caracteres.',
        ];
    }
}
