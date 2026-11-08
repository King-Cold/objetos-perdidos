<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incidencia extends Model
{
    use HasFactory;

    protected $table = 'incidencias';

    public const ESTADO_ABIERTA = 'Abierta';
    public const ESTADO_EN_REVISION = 'En revisión';
    public const ESTADO_ESPERA_CORRECCION = 'En espera de corrección';
    public const ESTADO_ESPERA_RECUPERACION = 'En espera de recuperación';
    public const ESTADO_SIN_RESOLVER_CANALIZADA = 'Sin resolver y canalizada';
    public const ESTADO_CERRADA = 'Cerrada';

    protected $fillable = [
        'publicacion_id',
        'devolucion_id',
        'user_id',
        'motivo',
        'estado',
        'fecha_apertura',
    ];

    protected $casts = [
        'fecha_apertura' => 'datetime',
    ];

    public function publicacion(): BelongsTo
    {
        return $this->belongsTo(Publicacion::class, 'publicacion_id');
    }

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class, 'devolucion_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isActiva(): bool
    {
        return !in_array($this->estado, [self::ESTADO_CERRADA, self::ESTADO_SIN_RESOLVER_CANALIZADA], true);
    }

    public function getFechaAperturaTextoAttribute(): string
    {
        $fecha = $this->fecha_apertura ?: $this->created_at;
        if (!$fecha) {
            return 'Sin fecha registrada';
        }

        return $this->formatMeridaDateTime($fecha);
    }

    public function getSeguimientoAutorizadoAttribute(): string
    {
        return match ($this->estado) {
            self::ESTADO_ABIERTA => 'Incidencia abierta en revisión administrativa',
            self::ESTADO_ESPERA_CORRECCION => 'En espera de corrección de datos de entrega',
            self::ESTADO_ESPERA_RECUPERACION => 'En seguimiento para recuperación física del objeto',
            self::ESTADO_SIN_RESOLVER_CANALIZADA => 'Incidencia canalizada para atención institucional',
            self::ESTADO_CERRADA => 'Duda o incidencia de entrega resuelta',
            default => 'En revisión administrativa por duda de entrega',
        };
    }

    private function formatMeridaDateTime($date): string
    {
        $carbon = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $carbon->setTimezone(Publicacion::TIMEZONE);

        $fecha = $carbon->format('d/m/Y');
        $hora = $carbon->format('h:i');
        $ampm = $carbon->format('a') === 'am' ? 'a. m.' : 'p. m.';

        return "{$fecha} • {$hora} {$ampm}";
    }
}

