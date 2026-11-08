<?php

namespace App\Http\Requests;

use App\Models\Publicacion;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreDevolucionExtemporaneaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     * Exclusivo para el administrador o bibliotecario autenticado.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && method_exists($user, 'isAdmin') && $user->isAdmin();
    }

    /**
     * Prepara y normaliza los datos antes de la validación eliminando espacios innecesarios.
     */
    protected function prepareForValidation(): void
    {
        $fechaEntrega = is_string($this->fecha_entrega) ? trim($this->fecha_entrega) : $this->fecha_entrega;
        if (is_string($fechaEntrega) && strlen($fechaEntrega) > 10 && preg_match('/^\d{4}-\d{2}-\d{2}/', $fechaEntrega)) {
            $fechaEntrega = substr($fechaEntrega, 0, 10);
        }

        $this->merge([
            'motivo_registro_tardio' => is_string($this->motivo_registro_tardio) ? trim($this->motivo_registro_tardio) : $this->motivo_registro_tardio,
            'receptor_nombre' => is_string($this->receptor_nombre) ? trim($this->receptor_nombre) : $this->receptor_nombre,
            'receptor_identificador' => is_string($this->receptor_identificador) ? trim($this->receptor_identificador) : $this->receptor_identificador,
            'fecha_entrega' => $fechaEntrega,
            'hora_entrega' => is_string($this->hora_entrega) ? trim($this->hora_entrega) : $this->hora_entrega,
            'explicacion_propiedad' => is_string($this->explicacion_propiedad) ? trim($this->explicacion_propiedad) : $this->explicacion_propiedad,
        ]);
    }

    /**
     * Reglas de validación para documentar una devolución reportada fuera de tiempo.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $timezone = Publicacion::TIMEZONE;
        $now = Carbon::now($timezone);
        $maxDate = $now->format('Y-m-d');
        $currentTime = $now->format('H:i');

        $publicacionId = $this->route('id');
        $publicacion = $publicacionId ? Publicacion::find($publicacionId) : null;

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

        $fechaVencimientoOriginal = null;
        if ($publicacion && $publicacion->fecha_vencimiento) {
            $rawVenc = $publicacion->getRawOriginal('fecha_vencimiento');
            $fechaVencimientoOriginal = $rawVenc
                ? Carbon::parse($rawVenc, $timezone)
                : Carbon::parse($publicacion->fecha_vencimiento->format('Y-m-d H:i:s'), $timezone);
        }

        return [
            // Identificación y motivo: Motivo obligatorio del registro tardío, sin aceptar vacíos ni solo espacios
            'motivo_registro_tardio' => [
                'required',
                'string',
                'max:2000',
                function ($attribute, $value, $fail) {
                    if (trim((string) $value) === '') {
                        $fail('El motivo del registro tardío es obligatorio.');
                    }
                },
            ],

            // Datos del receptor: Nombre completo obligatorio
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

            // Identificador institucional de alumno o docente
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

            // Fecha real de entrega física (no futura, no anterior al hallazgo y no posterior al vencimiento original)
            'fecha_entrega' => [
                'required',
                'date_format:Y-m-d',
                function ($attribute, $value, $fail) use ($maxDate, $fechaHallazgo, $fechaVencimientoOriginal) {
                    if ($value > $maxDate) {
                        $fail('La fecha real de entrega no puede ser una fecha futura.');
                        return;
                    }

                    if ($fechaHallazgo && $value < $fechaHallazgo) {
                        $fail('La fecha real de entrega no puede ser anterior a la fecha del hallazgo.');
                        return;
                    }

                    if ($fechaVencimientoOriginal && $value > $fechaVencimientoOriginal->format('Y-m-d')) {
                        $fail('La fecha y hora reales de entrega deben ser anteriores al vencimiento original del reporte.');
                    }
                },
            ],

            // Hora real de entrega física (anterior al vencimiento original, no anterior a la hora del hallazgo ni futura)
            'hora_entrega' => [
                'required',
                'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',
                function ($attribute, $value, $fail) use ($timezone, $maxDate, $currentTime, $fechaHallazgo, $horaHallazgo, $fechaVencimientoOriginal) {
                    $inputTime = substr((string) $value, 0, 5);
                    $fechaEntrega = $this->input('fecha_entrega');

                    if (! is_string($fechaEntrega) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEntrega)) {
                        return;
                    }

                    if ($fechaEntrega === $maxDate && $inputTime > $currentTime) {
                        $fail('La hora real de entrega no puede ser posterior a la hora actual.');
                        return;
                    }

                    if ($fechaHallazgo && $fechaEntrega === $fechaHallazgo && $horaHallazgo) {
                        if ($inputTime < $horaHallazgo) {
                            $fail('La hora real de entrega no puede ser anterior a la hora del hallazgo.');
                            return;
                        }
                    }

                    if ($fechaVencimientoOriginal) {
                        try {
                            $momentoEntrega = Carbon::createFromFormat('Y-m-d H:i', "{$fechaEntrega} {$inputTime}", $timezone);
                            $momentoVencimientoComparacion = $fechaVencimientoOriginal->copy()->startOfMinute();

                            if ($momentoEntrega->greaterThanOrEqualTo($momentoVencimientoComparacion)) {
                                $fail('La fecha y hora reales de entrega deben ser estrictamente anteriores al vencimiento original del reporte. Una entrega realizada al vencer el plazo o después no se acepta mediante este procedimiento.');
                            }
                        } catch (\Throwable $e) {
                            $fail('La fecha y hora de entrega proporcionadas no son válidas.');
                        }
                    }
                },
            ],

            // Explicación de comprobación de propiedad
            'explicacion_propiedad' => [
                'required',
                'string',
                'max:2000',
                function ($attribute, $value, $fail) {
                    if (trim((string) $value) === '') {
                        $fail('La explicación de cómo se comprobó la propiedad es obligatoria.');
                    }
                },
            ],

            // Fotografía JPG, JPEG o PNG de hasta 2 MB
            'fotografia' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png',
                'max:2048',
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
            'motivo_registro_tardio.required' => 'El motivo del registro tardío es obligatorio.',
            'motivo_registro_tardio.string' => 'El motivo del registro tardío debe ser un texto válido.',
            'motivo_registro_tardio.max' => 'El motivo del registro tardío no debe exceder los 2000 caracteres.',

            'receptor_nombre.required' => 'El nombre completo del receptor es obligatorio.',
            'receptor_nombre.string' => 'El nombre completo del receptor debe ser un texto válido.',
            'receptor_nombre.max' => 'El nombre completo del receptor no debe exceder los 150 caracteres.',

            'receptor_identificador.required' => 'El identificador institucional del receptor es obligatorio.',
            'receptor_identificador.string' => 'El identificador institucional debe ser un texto válido.',
            'receptor_identificador.max' => 'El identificador institucional no debe exceder los 50 caracteres.',

            'fecha_entrega.required' => 'La fecha real de entrega es obligatoria.',
            'fecha_entrega.date_format' => 'La fecha real de entrega debe tener un formato válido (AAAA-MM-DD).',

            'hora_entrega.required' => 'La hora real de entrega es obligatoria.',
            'hora_entrega.regex' => 'La hora real de entrega debe tener un formato válido (HH:MM).',

            'explicacion_propiedad.required' => 'La explicación de cómo se comprobó la propiedad es obligatoria.',
            'explicacion_propiedad.string' => 'La explicación debe ser un texto válido.',
            'explicacion_propiedad.max' => 'La explicación no debe exceder los 2000 caracteres.',

            'fotografia.required' => 'Debes adjuntar la fotografía de evidencia de la entrega.',
            'fotografia.file' => 'El archivo adjunto debe ser un archivo válido.',
            'fotografia.image' => 'El archivo adjunto debe ser una imagen válida.',
            'fotografia.mimes' => 'La fotografía de evidencia debe estar en formato JPG, JPEG o PNG.',
            'fotografia.max' => 'La fotografía de evidencia no debe superar los 2 MB.',
        ];
    }
}
