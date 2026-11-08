<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'recepcion_id',
        'user_id',
        'responsable_revision_id',
        'cerrada_por_id',
        'motivo',
        'estado',
        'resultado',
        'motivo_resolucion',
        'solicitud_recuperacion',
        'recuperacion_confirmada',
        'fecha_recuperacion',
        'responsable_canalizacion',
        'observaciones_seguimiento',
        'fecha_apertura',
        'fecha_revision',
        'fecha_cierre',
    ];

    protected $casts = [
        'recuperacion_confirmada' => 'boolean',
        'fecha_recuperacion' => 'datetime',
        'fecha_apertura' => 'datetime',
        'fecha_revision' => 'datetime',
        'fecha_cierre' => 'datetime',
    ];

    public function publicacion(): BelongsTo
    {
        return $this->belongsTo(Publicacion::class, 'publicacion_id');
    }

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class, 'devolucion_id');
    }

    public function recepcion(): BelongsTo
    {
        return $this->belongsTo(Recepcion::class, 'recepcion_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function responsableRevision(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_revision_id');
    }

    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por_id');
    }

    public function observaciones(): HasMany
    {
        return $this->hasMany(IncidenciaObservacion::class, 'incidencia_id')
            ->orderBy('fecha_registro', 'asc')
            ->orderBy('id', 'asc');
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

    public function getFechaRevisionTextoAttribute(): ?string
    {
        if (!$this->fecha_revision) {
            return null;
        }

        return $this->formatMeridaDateTime($this->fecha_revision);
    }

    public function getFechaCierreTextoAttribute(): ?string
    {
        if (!$this->fecha_cierre) {
            return null;
        }

        return $this->formatMeridaDateTime($this->fecha_cierre);
    }

    public function getFechaRecuperacionTextoAttribute(): ?string
    {
        if (!$this->fecha_recuperacion) {
            return null;
        }

        return $this->formatMeridaDateTime($this->fecha_recuperacion);
    }

    public function hasRecuperacionFisicaConfirmada(): bool
    {
        return (bool) $this->recuperacion_confirmada && $this->recepcion_id !== null;
    }

    public function getSeguimientoAutorizadoAttribute(): string
    {
        return match ($this->estado) {
            self::ESTADO_ABIERTA => 'Incidencia abierta en revisión administrativa',
            self::ESTADO_ESPERA_CORRECCION => 'En espera de corrección',
            self::ESTADO_ESPERA_RECUPERACION => 'En espera de recuperación',
            self::ESTADO_SIN_RESOLVER_CANALIZADA => 'Sin resolver y canalizada',
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
