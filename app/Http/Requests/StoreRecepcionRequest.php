<?php

namespace App\Http\Requests;

use App\Models\Publicacion;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecepcionRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return auth()->check() && (bool) auth()->user()->isAdmin();
    }

    /**
     * Prepara y normaliza los datos antes de la validación.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'condicion_objeto' => is_string($this->condicion_objeto) ? trim($this->condicion_objeto) : $this->condicion_objeto,
            'ubicacion_resguardo' => is_string($this->ubicacion_resguardo) ? trim($this->ubicacion_resguardo) : $this->ubicacion_resguardo,
            'observaciones' => is_string($this->observaciones) ? trim($this->observaciones) : $this->observaciones,
        ]);
    }

    /**
     * Reglas de validación aplicables al registro de recepción física (HU-16 / Tarea 1).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $timezone = Publicacion::TIMEZONE;
        $now = Carbon::now($timezone);
        $maxDate = $now->format('Y-m-d');
        $currentTime = $now->format('H:i');

        // Obtener la publicación asociada desde la ruta
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

        return [
            // Confirmación obligatoria de cotejo físico
            'confirmacion_cotejo' => [
                'required',
                'accepted',
            ],

            // Fecha real de recepción física
            'fecha_recepcion' => [
                'required',
                'date_format:Y-m-d',
                function ($attribute, $value, $fail) use ($maxDate, $fechaHallazgo) {
                    if ($value > $maxDate) {
                        $fail('La fecha de recepción física no puede ser una fecha futura.');
                        return;
                    }

                    if ($fechaHallazgo && $value < $fechaHallazgo) {
                        $fail('La fecha de recepción física no puede ser anterior a la fecha de hallazgo del objeto.');
                    }
                },
            ],

            // Hora real de recepción física
            'hora_recepcion' => [
                'required',
                'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',
                function ($attribute, $value, $fail) use ($maxDate, $currentTime, $fechaHallazgo, $horaHallazgo) {
                    $inputTime = substr((string) $value, 0, 5);
                    $fechaRecepcion = $this->input('fecha_recepcion');

                    // Si la recepción es hoy, la hora no puede ser futura
                    if ($fechaRecepcion === $maxDate && $inputTime > $currentTime) {
                        $fail('La hora de recepción física no puede ser posterior a la hora actual.');
                        return;
                    }

                    // Si la recepción fue el mismo día del hallazgo y hay hora registrada
                    if ($fechaHallazgo && $fechaRecepcion === $fechaHallazgo && $horaHallazgo) {
                        if ($inputTime < $horaHallazgo) {
                            $fail('La hora de recepción no puede ser anterior a la hora del hallazgo.');
                        }
                    }
                },
            ],

            // Condición del objeto al momento de la entrega
            'condicion_objeto' => [
                'required',
                'string',
                'max:1000',
                function ($attribute, $value, $fail) {
                    if (trim((string) $value) === '') {
                        $fail('La condición del objeto al momento de la entrega es obligatoria.');
                    }
                },
            ],

            // Ubicación de resguardo asignada en biblioteca
            'ubicacion_resguardo' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (trim((string) $value) === '') {
                        $fail('La ubicación de resguardo asignada en biblioteca es obligatoria.');
                    }
                },
            ],

            // Observaciones físicas adicionales (opcional)
            'observaciones' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Mensajes personalizados para las validaciones.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmacion_cotejo.required' => 'Debes confirmar que has recibido físicamente el objeto y que corresponde al reporte mostrado.',
            'confirmacion_cotejo.accepted' => 'Debes confirmar que has recibido físicamente el objeto y que corresponde al reporte mostrado.',
            'fecha_recepcion.required' => 'La fecha real de recepción es obligatoria.',
            'fecha_recepcion.date_format' => 'El formato de la fecha de recepción debe ser AAAA-MM-DD.',
            'hora_recepcion.required' => 'La hora real de recepción es obligatoria.',
            'hora_recepcion.regex' => 'El formato de la hora debe ser HH:MM (24 horas).',
            'condicion_objeto.required' => 'La condición del objeto al momento de la entrega es obligatoria.',
            'ubicacion_resguardo.required' => 'La ubicación de resguardo asignada en biblioteca es obligatoria.',
        ];
    }
}

