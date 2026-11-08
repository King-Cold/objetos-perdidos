<?php

namespace App\Http\Requests;

use App\Models\Devolucion;
use App\Models\Publicacion;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDevolucionRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     * Solo el autor original del reporte asociado a la devolución puede realizarla.
     */
    public function authorize(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $devolucionId = $this->route('id');
        if (! $devolucionId) {
            return true;
        }

        $devolucion = Devolucion::with('publicacion')->find($devolucionId);
        if (! $devolucion) {
            return true;
        }

        $autorId = $devolucion->publicacion ? $devolucion->publicacion->user_id : $devolucion->user_id;

        return auth()->id() === $autorId;
    }

    /**
     * Prepara y normaliza los datos antes de la validación eliminando espacios
     * innecesarios de cadenas de texto.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'receptor_nombre' => is_string($this->receptor_nombre) ? trim($this->receptor_nombre) : $this->receptor_nombre,
            'receptor_identificador' => is_string($this->receptor_identificador) ? trim($this->receptor_identificador) : $this->receptor_identificador,
            'fecha_entrega' => is_string($this->fecha_entrega) ? trim($this->fecha_entrega) : $this->fecha_entrega,
            'hora_entrega' => is_string($this->hora_entrega) ? trim($this->hora_entrega) : $this->hora_entrega,
            'explicacion_propiedad' => is_string($this->explicacion_propiedad) ? trim($this->explicacion_propiedad) : $this->explicacion_propiedad,
        ]);
    }

    /**
     * Reglas de validación aplicables a la corrección de devolución (HU-12 / Tarea 3).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $timezone = Publicacion::TIMEZONE;
        $now = Carbon::now($timezone);
        $maxDate = $now->format('Y-m-d');
        $currentTime = $now->format('H:i');

        // Obtener la devolución y publicación asociada desde la ruta
        $devolucionId = $this->route('id');
        $devolucion = $devolucionId ? Devolucion::with('publicacion')->find($devolucionId) : null;
        $publicacion = $devolucion ? $devolucion->publicacion : null;

        $fechaHallazgo = null;
        $horaHallazgo = null;
        if ($publicacion && $publicacion->fecha_hallazgo) {
            $fechaHallazgo = $publicacion->fecha_hallazgo instanceof Carbon
                ? $publicacion->fecha_hallazgo->format('Y-m-d')
                : Carbon::parse($publicacion->fecha_hallazgo)->format('Y-m-d');

            if (! empty($publicacion->hora_hallazgo)) {
                $horaHallazgo = substr((string) $publicacion->hora_hallazgo, 0, 5);
            }
        }

        return [
            // Nombre completo del receptor obligatorio y no compuesto solo por espacios
            'receptor_nombre' => [
                'required',
                'string',
                'max:150',
                function ($attribute, $value, $fail) {
                    if (trim((string) $value) === '') {
                        $fail('El nombre completo del receptor es obligatorio.');
                    }
                },
            ],

            // Identificador institucional del receptor (estudiante o docente)
            'receptor_identificador' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    $val = trim((string) $value);
                    if ($val === '') {
                        $fail('El identificador institucional del receptor es obligatorio.');
                        return;
                    }

                    if (! preg_match('/^[a-zA-Z0-9\-_]{3,30}$/', $val)) {
                        $fail('El identificador institucional debe ser una matrícula o identificador docente válido.');
                    }
                },
            ],

            // Fecha de entrega obligatoria, no futura y no anterior al hallazgo
            'fecha_entrega' => [
                'required',
                'date_format:Y-m-d',
                function ($attribute, $value, $fail) use ($maxDate, $fechaHallazgo) {
                    if ($value > $maxDate) {
                        $fail('La fecha de entrega no puede ser una fecha futura.');
                        return;
                    }

                    if ($fechaHallazgo && $value < $fechaHallazgo) {
                        $fail('La fecha de entrega no puede ser anterior a la fecha del hallazgo.');
                    }
                },
            ],

            // Hora de entrega obligatoria y coherente
            'hora_entrega' => [
                'required',
                'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',
                function ($attribute, $value, $fail) use ($maxDate, $currentTime, $fechaHallazgo, $horaHallazgo) {
                    $inputTime = substr((string) $value, 0, 5);
                    $fechaEntrega = $this->input('fecha_entrega');

                    // Si la entrega es hoy, la hora no puede ser futura
                    if ($fechaEntrega === $maxDate && $inputTime > $currentTime) {
                        $fail('La hora de entrega no puede ser posterior a la hora actual.');
                        return;
                    }

                    // Si la entrega es el mismo día del hallazgo y hay hora registrada
                    if ($fechaHallazgo && $fechaEntrega === $fechaHallazgo && $horaHallazgo) {
                        if ($inputTime < $horaHallazgo) {
                            $fail('La hora de entrega no puede ser anterior a la hora del hallazgo.');
                        }
                    }
                },
            ],

            // Explicación de cómo se comprobó la propiedad
            'explicacion_propiedad' => [
                'required',
                'string',
                'max:2000',
                function ($attribute, $value, $fail) {
                    $trimmed = trim((string) $value);
                    if ($trimmed === '') {
                        $fail('La explicación de cómo se comprobó la propiedad es obligatoria.');
                    }
                },
            ],

            // Fotografía opcional en la corrección; si se proporciona, debe ser JPG o PNG <= 2 MB
            'fotografia' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,png',
                'extensions:jpg,png',
                'max:2048', // 2048 KB = 2 MB
            ],
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receptor_nombre.required' => 'El nombre completo del receptor es obligatorio.',
            'receptor_nombre.string' => 'El nombre completo del receptor debe ser un texto válido.',
            'receptor_nombre.max' => 'El nombre completo del receptor no debe exceder los 150 caracteres.',

            'receptor_identificador.required' => 'El identificador institucional del receptor es obligatorio.',
            'receptor_identificador.string' => 'El identificador institucional debe ser un texto válido.',
            'receptor_identificador.max' => 'El identificador institucional no debe exceder los 50 caracteres.',

            'fecha_entrega.required' => 'La fecha real de entrega es obligatoria.',
            'fecha_entrega.date_format' => 'La fecha de entrega debe tener un formato válido (AAAA-MM-DD).',

            'hora_entrega.required' => 'La hora real de entrega es obligatoria.',
            'hora_entrega.regex' => 'La hora de entrega debe tener un formato válido (HH:MM).',

            'explicacion_propiedad.required' => 'La explicación de cómo se comprobó la propiedad es obligatoria.',
            'explicacion_propiedad.string' => 'La explicación debe ser un texto válido.',
            'explicacion_propiedad.max' => 'La explicación no debe exceder los 2000 caracteres.',

            'fotografia.file' => 'El archivo adjunto debe ser un archivo válido.',
            'fotografia.image' => 'El archivo adjunto debe ser una imagen válida.',
            'fotografia.mimes' => 'La fotografía de evidencia debe estar en formato JPG o PNG.',
            'fotografia.extensions' => 'La fotografía de evidencia debe estar en formato JPG o PNG.',
            'fotografia.max' => 'La fotografía de evidencia no debe superar los 2 MB.',
        ];
    }
}
