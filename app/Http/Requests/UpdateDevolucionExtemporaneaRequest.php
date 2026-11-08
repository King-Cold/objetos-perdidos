<?php

namespace App\Http\Requests;

use App\Models\Devolucion;
use App\Models\Publicacion;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDevolucionExtemporaneaRequest extends FormRequest
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
     * Reglas de validación para corregir y reenviar una devolución reportada fuera de tiempo.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $timezone = Publicacion::TIMEZONE;
        $now = Carbon::now($timezone);
        $maxDate = $now->format('Y-m-d');
        $currentTime = $now->format('H:i');

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
                            $fail('La hora real de entrega no puede ser anterior a la hora del hallazgo (' . $horaHallazgo . ').');
                            return;
                        }
                    }

                    if ($fechaVencimientoOriginal) {
                        $vencDate = $fechaVencimientoOriginal->format('Y-m-d');
                        $vencTime = $fechaVencimientoOriginal->format('H:i');

                        if ($fechaEntrega === $vencDate && $inputTime >= $vencTime) {
                            $fail('La fecha y hora reales de entrega deben ser estrictamente anteriores al vencimiento original (' . $vencDate . ' ' . $vencTime . ').');
                        }
                    }
                },
            ],

            // Explicación de la comprobación de propiedad
            'explicacion_propiedad' => [
                'required',
                'string',
                'min:10',
                'max:2000',
                function ($attribute, $value, $fail) {
                    if (trim((string) $value) === '') {
                        $fail('La explicación de comprobación de propiedad es obligatoria.');
                    }
                },
            ],

            // Fotografía de evidencia física (opcional al corregir; si se sube una nueva debe cumplir requisitos)
            'fotografia' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png',
                'max:2048',
            ],
        ];
    }

    /**
     * Mensajes descriptivos y comprensibles para las reglas de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo_registro_tardio.required' => 'El motivo del registro tardío es obligatorio.',
            'receptor_nombre.required' => 'El nombre completo del receptor es obligatorio.',
            'receptor_identificador.required' => 'El identificador institucional del receptor es obligatorio.',
            'fecha_entrega.required' => 'La fecha real de entrega física es obligatoria.',
            'fecha_entrega.date_format' => 'El formato de la fecha de entrega debe ser AAAA-MM-DD.',
            'hora_entrega.required' => 'La hora real de entrega física es obligatoria.',
            'hora_entrega.regex' => 'El formato de la hora de entrega debe ser HH:MM.',
            'explicacion_propiedad.required' => 'La explicación de comprobación de propiedad es obligatoria.',
            'explicacion_propiedad.min' => 'La explicación de comprobación de propiedad debe tener al menos 10 caracteres.',
            'fotografia.image' => 'El archivo seleccionado debe ser una imagen válida.',
            'fotografia.mimes' => 'La fotografía de evidencia debe estar en formato JPG, JPEG o PNG.',
            'fotografia.max' => 'La fotografía de evidencia no debe exceder 2 MB de tamaño.',
        ];
    }
}
