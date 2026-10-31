<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreHallazgoRequest extends FormRequest
{
    /**
     * Zona horaria oficial para las validaciones del campus.
     */
    public const TIMEZONE = 'America/Merida';

    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Prepara y normaliza los datos antes de la validación eliminando espacios
     * innecesarios y normalizando el archivo o arreglo de fotografías.
     */
    protected function prepareForValidation(): void
    {
        $dataToMerge = [
            'nombre' => is_string($this->nombre) ? trim($this->nombre) : $this->nombre,
            'descripcion' => is_string($this->descripcion) ? trim($this->descripcion) : $this->descripcion,
            'fecha_hallazgo' => is_string($this->fecha_hallazgo) ? trim($this->fecha_hallazgo) : $this->fecha_hallazgo,
            'hora_hallazgo' => is_string($this->hora_hallazgo) && trim($this->hora_hallazgo) !== '' ? trim($this->hora_hallazgo) : null,
            'area_zona' => is_string($this->area_zona) ? trim($this->area_zona) : $this->area_zona,
            'ubicacion_especifica' => is_string($this->ubicacion_especifica) ? trim($this->ubicacion_especifica) : $this->ubicacion_especifica,
        ];

        // Normalización del campo de fotografías
        if ($this->hasFile('fotografia') && ! $this->hasFile('fotografias')) {
            $dataToMerge['fotografias'] = [$this->file('fotografia')];
        } elseif ($this->hasFile('fotografias')) {
            $files = $this->file('fotografias');
            $dataToMerge['fotografias'] = is_array($files) ? array_values(array_filter($files)) : [$files];
        } elseif (! $this->hasFile('fotografia') && ! $this->hasFile('fotografias') && ! $this->has('fotografias')) {
            $dataToMerge['fotografias'] = null;
        }

        $this->merge($dataToMerge);
    }

    /**
     * Reglas de validación aplicables a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $timezone = self::TIMEZONE;
        $now = Carbon::now($timezone);
        $maxDate = $now->format('Y-m-d');
        $minDate = $now->copy()->subDays(7)->format('Y-m-d');

        return [
            'nombre' => [
                'required',
                'string',
                'max:150',
                function ($attribute, $value, $fail) {
                    if (trim($value) === '') {
                        $fail('El nombre del objeto encontrado es obligatorio.');
                    }
                },
            ],
            'descripcion' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $trimmed = trim($value);
                    if ($trimmed === '') {
                        $fail('La descripción del objeto es obligatoria.');
                        return;
                    }

                    $length = mb_strlen($trimmed, 'UTF-8');
                    if ($length < 50) {
                        $fail('La descripción debe contener al menos 50 caracteres.');
                    } elseif ($length > 200) {
                        $fail('La descripción no debe superar los 200 caracteres.');
                    }
                },
            ],
            'fecha_hallazgo' => [
                'required',
                'date_format:Y-m-d',
                function ($attribute, $value, $fail) use ($minDate, $maxDate) {
                    if ($value > $maxDate) {
                        $fail('La fecha del hallazgo no puede ser una fecha futura.');
                        return;
                    }

                    if ($value < $minDate) {
                        $fail('Solo se pueden registrar objetos encontrados durante los últimos 7 días.');
                    }
                },
            ],
            'hora_hallazgo' => [
                'nullable',
                'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',
                function ($attribute, $value, $fail) use ($timezone, $maxDate) {
                    if (! $value) {
                        return;
                    }

                    $fecha = $this->input('fecha_hallazgo');
                    if ($fecha === $maxDate) {
                        $currentTime = Carbon::now($timezone)->format('H:i');
                        $inputTime = substr($value, 0, 5);

                        if ($inputTime > $currentTime) {
                            $fail('La hora del hallazgo no puede ser posterior a la hora actual.');
                        }
                    }
                },
            ],
            'area_zona' => [
                'required',
                'string',
                'max:100',
                function ($attribute, $value, $fail) {
                    if (trim($value) === '') {
                        $fail('El área o zona donde se encontró es obligatoria.');
                    }
                },
            ],
            'ubicacion_especifica' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (trim($value) === '') {
                        $fail('La ubicación específica es obligatoria.');
                    }
                },
            ],
            'fotografias' => [
                'required',
                'array',
                'min:1',
                'max:5',
            ],
            'fotografias.*' => [
                'image',
                'mimes:jpg,png',
                'max:2048', // Máx 2MB
            ],
        ];
    }

    /**
     * Mensajes de error en español para las reglas de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del objeto encontrado es obligatorio.',
            'nombre.string' => 'El nombre del objeto encontrado debe ser una cadena de texto.',
            'nombre.max' => 'El nombre del objeto encontrado no debe exceder los 150 caracteres.',

            'descripcion.required' => 'La descripción del objeto es obligatoria.',
            'descripcion.string' => 'La descripción del objeto debe ser una cadena de texto.',

            'fecha_hallazgo.required' => 'La fecha en que fue encontrado es obligatoria.',
            'fecha_hallazgo.date_format' => 'La fecha en que fue encontrado debe tener un formato válido (AAAA-MM-DD).',

            'hora_hallazgo.regex' => 'La hora debe tener un formato válido (HH:MM).',

            'area_zona.required' => 'El área o zona donde se encontró es obligatoria.',
            'area_zona.string' => 'El área o zona debe ser una cadena de texto.',
            'area_zona.max' => 'El área o zona no debe exceder los 100 caracteres.',

            'ubicacion_especifica.required' => 'La ubicación específica es obligatoria.',
            'ubicacion_especifica.string' => 'La ubicación específica debe ser una cadena de texto.',
            'ubicacion_especifica.max' => 'La ubicación específica no debe exceder los 255 caracteres.',

            'fotografias.required' => 'Debes adjuntar al menos una fotografía del objeto.',
            'fotografias.array' => 'El formato de las fotografías es inválido.',
            'fotografias.min' => 'Debes adjuntar al menos una fotografía del objeto.',
            'fotografias.max' => 'No puedes subir más de 5 fotografías.',
            'fotografias.*.image' => 'Cada archivo debe ser una imagen válida.',
            'fotografias.*.mimes' => 'Las fotografías deben estar en formato JPG o PNG.',
            'fotografias.*.max' => 'Cada fotografía no debe exceder los 2 MB.',
        ];
    }
}
