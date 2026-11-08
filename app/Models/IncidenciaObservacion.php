<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidenciaObservacion extends Model
{
    use HasFactory;

    protected $table = 'incidencia_observaciones';

    public const TIPO_ACLARACION = 'Aclaración';
    public const TIPO_CONTACTO_EXTERNO = 'Contacto por medio externo';
    public const TIPO_RESULTADO = 'Resultado obtenido';

    protected $fillable = [
        'incidencia_id',
        'user_id',
        'tipo',
        'medio_contacto',
        'descripcion',
        'resultado_obtenido',
        'fecha_registro',
    ];

    protected $casts = [
        'fecha_registro' => 'datetime',
    ];

    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class, 'incidencia_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getFechaRegistroTextoAttribute(): string
    {
        $fecha = $this->fecha_registro ?: $this->created_at;
        if (!$fecha) {
            return 'Sin fecha registrada';
        }

        $carbon = $fecha instanceof Carbon ? $fecha->copy() : Carbon::parse($fecha);
        $carbon->setTimezone(Publicacion::TIMEZONE);

        $fechaStr = $carbon->format('d/m/Y');
        $horaStr = $carbon->format('h:i');
        $ampm = $carbon->format('a') === 'am' ? 'a. m.' : 'p. m.';

        return "{$fechaStr} • {$horaStr} {$ampm}";
    }
}

