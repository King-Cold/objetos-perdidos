<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Devolucion extends Model
{
    use HasFactory;

    public const ESTADO_PENDIENTE = 'Pendiente de verificación';
    public const ESTADO_VERIFICADA = 'Verificada';
    public const ESTADO_APROBADA = 'Aprobada';
    public const ESTADO_REQUIERE_CORRECCION = 'Requiere corrección';

    protected $table = 'devoluciones';

    protected $fillable = [
        'publicacion_id',
        'user_id',
        'admin_id',
        'receptor_nombre',
        'receptor_identificador',
        'fecha_entrega',
        'hora_entrega',
        'fecha_captura',
        'fecha_envio_revision',
        'fecha_resolucion',
        'explicacion_propiedad',
        'motivo_correccion',
        'fotografia_ruta',
        'fotografia_nombre_original',
        'fotografia_mime_type',
        'fotografia_tamano_bytes',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_entrega' => 'date:Y-m-d',
            'fecha_captura' => 'datetime',
            'fecha_envio_revision' => 'datetime',
            'fecha_resolucion' => 'datetime',
            'fotografia_tamano_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Devolucion $devolucion) {
            if (empty($devolucion->fecha_envio_revision)) {
                $devolucion->fecha_envio_revision = $devolucion->fecha_captura ?? Carbon::now(Publicacion::TIMEZONE);
            }
        });
    }

    public function publicacion(): BelongsTo
    {
        return $this->belongsTo(Publicacion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function incidencias(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Incidencia::class, 'devolucion_id')
            ->orderBy('id', 'desc');
    }

    public function incidenciaActiva(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Incidencia::class, 'devolucion_id')
            ->whereNotIn('estado', [
                Incidencia::ESTADO_CERRADA,
                Incidencia::ESTADO_SIN_RESOLVER_CANALIZADA,
            ])
            ->latestOfMany();
    }

    public function isPendiente(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    public function isAprobada(): bool
    {
        return in_array($this->estado, [self::ESTADO_VERIFICADA, self::ESTADO_APROBADA], true);
    }

    public function isRequiereCorreccion(): bool
    {
        return $this->estado === self::ESTADO_REQUIERE_CORRECCION;
    }

    /**
     * Comprueba si un usuario dado está autorizado para consultar la devolución y su evidencia (CA-09, CA-13).
     * Autorizado únicamente si es el autor original del reporte o administrador/bibliotecario.
     * Ser receptor del objeto NO concede permisos.
     */
    public function puedeSerConsultadaPor(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $autorId = $this->publicacion ? $this->publicacion->user_id : $this->user_id;

        if ($user->id === $autorId) {
            return true;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Comprueba si la fotografía de evidencia vigente se encuentra disponible y legible en almacenamiento local (HU-13).
     */
    public function hasEvidenciaDisponible(): bool
    {
        if (empty($this->fotografia_ruta)) {
            return false;
        }

        return \Illuminate\Support\Facades\Storage::disk('local')->exists($this->fotografia_ruta);
    }

    public function getFechaEntregaTextoAttribute(): string
    {
        if (! $this->fecha_entrega) {
            return '';
        }

        $fechaStr = $this->fecha_entrega instanceof Carbon
            ? $this->fecha_entrega->format('d/m/Y')
            : Carbon::parse($this->fecha_entrega)->format('d/m/Y');

        if (! empty($this->hora_entrega)) {
            try {
                $horaRaw = Carbon::parse($this->hora_entrega)->format('h:i a');
                $horaStr = str_replace(['am', 'pm'], ['a. m.', 'p. m.'], $horaRaw);
                return "{$fechaStr} • {$horaStr}";
            } catch (\Throwable $e) {
                return "{$fechaStr} • {$this->hora_entrega}";
            }
        }

        return $fechaStr;
    }

    public function getFechaCapturaTextoAttribute(): string
    {
        if (! $this->fecha_captura) {
            return '';
        }

        $raw = $this->getRawOriginal('fecha_captura');
        if ($raw) {
            $formatted = Carbon::parse($raw)->format('d/m/Y • h:i a');
            return str_replace(['am', 'pm'], ['a. m.', 'p. m.'], $formatted);
        }

        $dt = $this->fecha_captura instanceof Carbon
            ? $this->fecha_captura
            : Carbon::parse($this->fecha_captura);

        $formatted = $dt->format('d/m/Y • h:i a');
        return str_replace(['am', 'pm'], ['a. m.', 'p. m.'], $formatted);
    }

    public function getFechaRevisionTextoAttribute(): string
    {
        $raw = $this->getRawOriginal('fecha_envio_revision') ?? $this->getRawOriginal('fecha_captura');
        if ($raw) {
            $formatted = Carbon::parse($raw)->format('d/m/Y • h:i a');
            return str_replace(['am', 'pm'], ['a. m.', 'p. m.'], $formatted);
        }

        $dt = $this->fecha_envio_revision ?? $this->fecha_captura;
        if (! $dt) {
            return '';
        }

        $dtObj = $dt instanceof Carbon ? $dt : Carbon::parse($dt);
        $formatted = $dtObj->format('d/m/Y • h:i a');
        return str_replace(['am', 'pm'], ['a. m.', 'p. m.'], $formatted);
    }

    public function setFechaReenvioAttribute($value): void
    {
        $this->attributes['fecha_envio_revision'] = $value;
    }

    public function getFechaReenvioAttribute()
    {
        return $this->fecha_envio_revision;
    }

    /**
     * Scope para devoluciones que requieren verificación administrativa en la cola (HU-12 Tarea 1).
     * - Estado: 'Pendiente de verificación'
     * - Excluye devoluciones aprobadas o en corrección
     * - Excluye devoluciones clasificadas como cierre directo desde biblioteca
     */
    public function scopePendientesVerificacion($query)
    {
        return $query->where('devoluciones.estado', self::ESTADO_PENDIENTE)
            ->whereHas('publicacion', function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNull('situacion_resguardo')
                        ->orWhere('situacion_resguardo', '!=', Publicacion::RESGUARDO_CONFIRMADO);
                });
            });
    }
}
