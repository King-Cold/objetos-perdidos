<?php

namespace App\Http\Requests;

use App\Models\Publicacion;
use App\Models\Recepcion;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreDevolucionBibliotecaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     * CA-01: Exclusivo para administrador/bibliotecario autenticado.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && method_exists($user, 'isAdmin') && $user->isAdmin();
    }

    /**
     * Prepara y normaliza los datos antes de la validación.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'receptor_nombre' => is_string($this->receptor_nombre) ? trim($this->receptor_nombre) : $this->receptor_nombre,
            'receptor_identificador' => is_string($this->receptor_identificador) ? trim($this->receptor_identificador) : $this->receptor_identificador,
            'fecha_entrega' => is_string($this->fecha_entrega) ? trim($this->fecha_entrega) : $this->fecha_entrega,
            'hora_entrega' => is_string($this->hora_entrega) ? trim($this->hora_entrega) : $this->hora_entrega,
            'explicacion_propiedad' => is_string($this->explicacion_propiedad) ? trim($this->explicacion_propiedad) : $this->explicacion_propiedad,
            'declaracion_sin_contradicciones' => $this->boolean('declaracion_sin_contradicciones'),
            'existen_inconsistencias' => $this->boolean('existen_inconsistencias'),
        ]);
    }

    /**
     * Reglas de validación aplicables a la devolución desde biblioteca (HU: Devolución desde biblioteca / Tarea 2).
     *
     * CA-03: Comprobación presencial de propiedad y resolución de contradicciones.
     * CA-04: Datos y evidencia obligatorios (sin credenciales legibles ni contraseñas).
     * CA-05: Coherencia de fechas (no futuras ni anteriores a la recepción física).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $timezone = Publicacion::TIMEZONE;
        $now = Carbon::now($timezone);
        $maxDate = $now->format('Y-m-d');
        $currentTime = $now->format('H:i');

        // Obtener la publicación y recepción asociadas desde el parámetro {id} de la ruta
        $publicacionId = $this->route('id');
        $publicacion = $publicacionId ? Publicacion::with('recepcion')->find($publicacionId) : null;
        $recepcion = $publicacion?->recepcion;

        $fechaRecepcion = null;
        $horaRecepcion = null;
        if ($recepcion && $recepcion->fecha_recepcion) {
            $fechaRecepcion = $recepcion->fecha_recepcion instanceof Carbon
                ? $recepcion->fecha_recepcion->format('Y-m-d')
                : Carbon::parse($recepcion->fecha_recepcion)->format('Y-m-d');

            if (! empty($recepcion->hora_recepcion)) {
                $horaRecepcion = substr((string) $recepcion->hora_recepcion, 0, 5);
            }
        }

        return [
            // CA-04: Nombre completo del receptor obligatorio y no vacío
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

            // CA-04: Identificador institucional del receptor (matrícula o clave docente válida)
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

            // CA-05: Fecha real de entrega no futura y no anterior a la recepción física del objeto
            'fecha_entrega' => [
                'required',
                'date_format:Y-m-d',
                function ($attribute, $value, $fail) use ($maxDate, $fechaRecepcion) {
                    if ($value > $maxDate) {
                        $fail('La fecha de entrega no puede ser una fecha futura.');
                        return;
                    }

                    if ($fechaRecepcion && $value < $fechaRecepcion) {
                        $fail('La fecha de entrega no puede ser anterior a la recepción física del objeto en biblioteca.');
                    }
                },
            ],

            // CA-05: Hora real de entrega válida, no futura para el día de hoy y coherente con la recepción
            'hora_entrega' => [
                'required',
                'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',
                function ($attribute, $value, $fail) use ($maxDate, $currentTime, $fechaRecepcion, $horaRecepcion) {
                    $inputTime = substr((string) $value, 0, 5);
                    $fechaEntrega = $this->input('fecha_entrega');

                    if ($fechaEntrega === $maxDate && $inputTime > $currentTime) {
                        $fail('La hora de entrega no puede ser posterior a la hora actual.');
                        return;
                    }

                    if ($fechaRecepcion && $fechaEntrega === $fechaRecepcion && $horaRecepcion) {
                        if ($inputTime < $horaRecepcion) {
                            $fail('La hora de entrega no puede ser anterior a la hora de recepción física en biblioteca.');
                        }
                    }
                },
            ],

            // CA-03: Explicación de la comprobación presencial de propiedad obligatoria y descriptiva
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

            // CA-03: Confirmación de que no existen contradicciones ni inconsistencias pendientes
            'declaracion_sin_contradicciones' => [
                'required',
                'accepted',
            ],

            'existen_inconsistencias' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $fail('Existen contradicciones pendientes sobre la propiedad del objeto. La entrega no puede confirmarse y debe gestionarse mediante el procedimiento de incidencias.');
                    }
                },
            ],

            // CA-04: Fotografía obligatoria en formato JPG o PNG de hasta 2 MB
            'fotografia' => [
                'required',
                'file',
                'image',
                'mimes:jpg,png',
                'extensions:jpg,png',
                'max:2048', // 2048 KB = 2 MB
            ],
        ];
    }

    /**
     * Mensajes de error personalizados en español para las reglas de validación.
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

            'declaracion_sin_contradicciones.required' => 'Debes confirmar que verificaste presencialmente la propiedad y que no existen contradicciones pendientes.',
            'declaracion_sin_contradicciones.accepted' => 'Debes confirmar que verificaste presencialmente la propiedad y que no existen contradicciones pendientes.',

            'fotografia.required' => 'Debes adjuntar la fotografía de evidencia de la entrega.',
            'fotografia.file' => 'El archivo adjunto debe ser un archivo válido.',
            'fotografia.image' => 'El archivo adjunto debe ser una imagen válida.',
            'fotografia.mimes' => 'La fotografía de evidencia debe estar en formato JPG o PNG.',
            'fotografia.extensions' => 'La fotografía de evidencia debe estar en formato JPG o PNG.',
            'fotografia.max' => 'La fotografía de evidencia no debe superar los 2 MB.',
        ];
    }
}

