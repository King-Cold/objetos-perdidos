<?php

namespace App\Http\Requests;

use App\Models\Publicacion;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReporteRequest extends FormRequest
{
    /**
     * Zona horaria oficial del campus.
     */
    public const TIMEZONE = 'America/Merida';

    /**
     * Comprueba autorización (CA-10, CT-01, CT-02).
     */
    public function authorize(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $id = $this->route('id');
        $publicacion = Publicacion::find($id);

        if (! $publicacion) {
            return false;
        }

        return $publicacion->user_id === auth()->id() || auth()->user()->isAdmin();
    }

    /**
     * Normaliza los campos de entrada antes de la validación.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => is_string($this->nombre) ? trim($this->nombre) : $this->nombre,
            'categoria' => is_string($this->categoria) ? trim($this->categoria) : $this->categoria,
            'descripcion' => is_string($this->descripcion) ? trim($this->descripcion) : $this->descripcion,
            'fecha_hallazgo' => is_string($this->fecha_hallazgo) ? trim($this->fecha_hallazgo) : $this->fecha_hallazgo,
            'hora_hallazgo' => is_string($this->hora_hallazgo) && trim($this->hora_hallazgo) !== '' ? trim($this->hora_hallazgo) : null,
            'area_zona' => is_string($this->area_zona) ? trim($this->area_zona) : $this->area_zona,
            'ubicacion_especifica' => is_string($this->ubicacion_especifica) ? trim($this->ubicacion_especifica) : $this->ubicacion_especifica,
            'eliminar_fotos' => is_array($this->eliminar_fotos) ? array_values(array_filter(array_map('intval', $this->eliminar_fotos))) : [],
            'foto_principal_id' => $this->filled('foto_principal_id') ? (int) $this->foto_principal_id : null,
        ]);
    }

    /**
     * Reglas de validación para la edición de reportes (CA-12, CA-15, CA-16, CA-17).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('id');
        $publicacion = Publicacion::findOrFail($id);

        // CA-15: La referencia para validar la fecha de hallazgo es la fecha de captura original (fecha_captura)
        $fechaCaptura = $publicacion->fecha_captura ?? $publicacion->created_at ?? Carbon::now(self::TIMEZONE);
        if (! $fechaCaptura instanceof Carbon) {
            $fechaCaptura = Carbon::parse($fechaCaptura, self::TIMEZONE);
        }

        $maxDate = $fechaCaptura->format('Y-m-d');
        $minDate = $fechaCaptura->copy()->subDays(7)->format('Y-m-d');
        $maxTime = $fechaCaptura->format('H:i');

        return [
            'nombre' => [
                'required',
                'string',
                'max:150',
                function ($attribute, $value, $fail) {
                    if (trim($value) === '') {
                        $fail('El nombre del objeto es obligatorio.');
                    }
                },
            ],
            'categoria' => [
                'nullable',
                'string',
                'max:100',
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
                        $fail('La fecha del hallazgo no puede ser posterior a la fecha de captura original.');
                        return;
                    }

                    if ($value < $minDate) {
                        $fail('Solo se pueden registrar objetos encontrados durante los 7 días previos a la fecha de captura.');
                    }
                },
            ],
            'hora_hallazgo' => [
                'nullable',
                'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',
                function ($attribute, $value, $fail) use ($maxDate, $maxTime) {
                    if (! $value) {
                        return;
                    }

                    $fecha = $this->input('fecha_hallazgo');
                    if ($fecha === $maxDate) {
                        $inputTime = substr($value, 0, 5);
                        if ($inputTime > $maxTime) {
                            $fail('La hora del hallazgo no puede ser posterior a la hora en que se capturó el reporte (' . $maxTime . ').');
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
            // CA-18, CA-19, CA-20, CA-21: Reglas de fotografías en edición
            'eliminar_fotos' => [
                'nullable',
                'array',
            ],
            'eliminar_fotos.*' => [
                'integer',
                'exists:fotografias,id',
            ],
            'nuevas_fotografias' => [
                'nullable',
                'array',
                'max:5',
            ],
            'nuevas_fotografias.*' => [
                'file',
                'image',
                'mimes:jpg,png',
                'max:2048', // 2048 KB = 2,097,152 bytes (CA-19)
            ],
            'foto_principal_id' => [
                'nullable',
                'integer',
            ],
        ];
    }

    /**
     * Validador contextual posterior para verificar límites globales y fotografía principal (CA-18, CA-20, CA-21).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $id = $this->route('id');
            $publicacion = Publicacion::with('fotografias')->find($id);
            if (! $publicacion) {
                return;
            }

            $existingIds = $publicacion->fotografias->pluck('id')->all();
            $toDelete = is_array($this->input('eliminar_fotos')) ? array_map('intval', $this->input('eliminar_fotos')) : [];
            $validToDelete = array_intersect($toDelete, $existingIds);
            $conservadas = count($existingIds) - count($validToDelete);

            $nuevas = 0;
            if ($this->hasFile('nuevas_fotografias')) {
                $files = $this->file('nuevas_fotografias');
                $nuevas = is_array($files) ? count(array_filter($files)) : 1;
            }

            $totalFinal = $conservadas + $nuevas;

            // CA-18, CA-21: Mantener entre 1 y 5 fotografías en el resultado final
            if ($totalFinal < 1) {
                $validator->errors()->add('fotografias', 'Debes conservar o incluir al menos una fotografía del objeto.');
            } elseif ($totalFinal > 5) {
                $validator->errors()->add('fotografias', 'El reporte no puede tener más de cinco fotografías en total (resultarían ' . $totalFinal . ').');
            }

            // CA-20: Identificar fotografía principal. Si se retira, exigir que quede seleccionada otra antes de guardar.
            $currentPrincipal = $publicacion->foto_principal ?? $publicacion->fotografias->first();
            $selectedPrincipalId = $this->input('foto_principal_id') ? (int) $this->input('foto_principal_id') : null;

            // Si se seleccionó una foto principal específica, validar que sea válida y no esté eliminada
            if ($selectedPrincipalId) {
                if (! in_array($selectedPrincipalId, $existingIds, true)) {
                    $validator->errors()->add('foto_principal_id', 'La fotografía seleccionada como principal no pertenece a este reporte.');
                } elseif (in_array($selectedPrincipalId, $validToDelete, true)) {
                    $validator->errors()->add('foto_principal_id', 'La fotografía seleccionada como principal está marcada para retirarse.');
                }
            }
        });
    }

    /**
     * Mensajes de error en español para las reglas de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del objeto es obligatorio.',
            'nombre.string' => 'El nombre del objeto debe ser una cadena de texto.',
            'nombre.max' => 'El nombre del objeto no debe exceder los 150 caracteres.',

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

            'nuevas_fotografias.max' => 'No puedes subir más de 5 fotografías.',
            'nuevas_fotografias.*.image' => 'Cada archivo debe ser una imagen válida.',
            'nuevas_fotografias.*.mimes' => 'Las fotografías deben estar en formato JPG o PNG.',
            'nuevas_fotografias.*.max' => 'Cada fotografía no debe exceder los 2 MB (2,097,152 bytes).',
        ];
    }
}

