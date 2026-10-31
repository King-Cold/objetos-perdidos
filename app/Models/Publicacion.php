<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Publicacion extends Model
{
    use HasFactory;

    /**
     * Zona horaria oficial para operaciones de captura y fechas del campus.
     */
    public const TIMEZONE = 'America/Merida';

    /**
     * Estado inicial obligatorio para nuevos reportes de hallazgos.
     */
    public const ESTADO_PENDIENTE = 'Pendiente de aprobación';

    /**
     * Estado cuando el administrador solicita correcciones al autor (HU-07 / Tarea 07.3).
     */
    public const ESTADO_CORRECCION_SOLICITADA = 'Corrección solicitada';

    /**
     * Estado cuando el reporte es aprobado para publicación en el muro (HU-07 / Tarea 07.4).
     */
    public const ESTADO_APROBADA = 'Aprobada';

    /**
     * Estado cuando el reporte es cancelado administrativamente (HU-07 / Tarea 07.5).
     */
    public const ESTADO_CANCELADO = 'Cancelado';

    /**
     * Días naturales de vigencia de publicación a partir de la primera aprobación (HU-07 / Tarea 07.4).
     */
    public const DIAS_VIGENCIA = 14;

    protected $table = 'publicaciones';

    protected $fillable = [
        'user_id',
        'folio',
        'nombre',
        'titulo',
        'descripcion',
        'fecha_hallazgo',
        'hora_hallazgo',
        'area_zona',
        'ubicacion_especifica',
        'fecha_captura',
        'fecha_envio_revision',
        'fecha_primera_aprobacion',
        'fecha_aprobacion',
        'fecha_vencimiento',
        'categoria',
        'lugar_hallazgo',
        'estado',
        'observaciones',
        'folio_referencia_duplicado',
        'version',
        'disponible',
        'devolucion_en_proceso',
        'incidencia_abierta',
    ];

    protected function casts(): array
    {
        return [
            'disponible' => 'boolean',
            'devolucion_en_proceso' => 'boolean',
            'incidencia_abierta' => 'boolean',
            'fecha_hallazgo' => 'date:Y-m-d',
            'fecha_captura' => 'datetime',
            'fecha_envio_revision' => 'datetime',
            'fecha_primera_aprobacion' => 'datetime',
            'fecha_aprobacion' => 'datetime',
            'fecha_vencimiento' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Publicacion $publicacion) {
            if (empty($publicacion->folio)) {
                $publicacion->folio = static::generarFolio();
            }
            if (empty($publicacion->fecha_captura)) {
                $publicacion->fecha_captura = Carbon::now(self::TIMEZONE);
            }
            if (empty($publicacion->fecha_envio_revision)) {
                $publicacion->fecha_envio_revision = $publicacion->fecha_captura ?? Carbon::now(self::TIMEZONE);
            }
            if (empty($publicacion->titulo) && ! empty($publicacion->nombre)) {
                $publicacion->titulo = $publicacion->nombre;
            } elseif (empty($publicacion->nombre) && ! empty($publicacion->titulo)) {
                $publicacion->nombre = $publicacion->titulo;
            }
        });
    }

    /**
     * Genera un folio único irrepetible con estructura:
     * OBJ-YYYYMMDD-XXXX (ej. OBJ-20261031-0001).
     *
     * - OBJ: Identificador de tipo de reporte ("Objeto").
     * - YYYYMMDD: Fecha del servidor en la zona horaria del campus (America/Merida).
     * - XXXX: Consecutivo numérico diario de 4 dígitos con prevención de colisiones.
     */
    public static function generarFolio(): string
    {
        $fechaPrefijo = Carbon::now(self::TIMEZONE)->format('Ymd');
        $prefix = "OBJ-{$fechaPrefijo}-";

        // Buscar el último folio del día para determinar la secuencia siguiente
        $ultimo = static::where('folio', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();

        $secuencia = 1;
        if ($ultimo && preg_match('/-(\d{4})$/', (string) $ultimo->folio, $matches)) {
            $secuencia = ((int) $matches[1]) + 1;
        }

        do {
            $folio = sprintf('%s%04d', $prefix, $secuencia);
            $existe = static::where('folio', $folio)->exists();
            if ($existe) {
                $secuencia++;
            }
        } while ($existe);

        return $folio;
    }

    /**
     * Relación: El reporte pertenece a un usuario (autor del hallazgo).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación: Un reporte tiene asociadas entre 1 y 5 fotografías.
     */
    public function fotografias(): HasMany
    {
        return $this->hasMany(Fotografia::class);
    }

    /**
     * Relación: Historial de decisiones de moderación tomadas sobre este reporte (HU-07 / Tarea 07.3).
     */
    public function decisiones(): HasMany
    {
        return $this->hasMany(DecisionModeracion::class)->orderBy('id', 'desc');
    }

    /**
     * Comprueba si el reporte se encuentra en estado de corrección solicitada.
     */
    public function isCorreccionSolicitada(): bool
    {
        return $this->estado === self::ESTADO_CORRECCION_SOLICITADA;
    }

    /**
     * Comprueba si el reporte se encuentra en estado cancelado (HU-07 / Tarea 07.5).
     */
    public function isCancelado(): bool
    {
        return in_array($this->estado, [self::ESTADO_CANCELADO, 'Cancelada'], true);
    }

    /**
     * Comprueba si el reporte se encuentra en un estado compatible con cancelación administrativa.
     */
    public function isCancelable(): bool
    {
        return ! $this->isCancelado() && $this->estado !== 'Devuelto';
    }

    /**
     * Comprueba si la publicación ya superó su vigencia de 14 días naturales (HU-07 / Tarea 07.4).
     */
    public function isVencida(): bool
    {
        if ($this->fecha_vencimiento === null) {
            return false;
        }

        return Carbon::now(self::TIMEZONE)->greaterThanOrEqualTo($this->fecha_vencimiento);
    }

    /**
     * Comprueba si existen restricciones de negocio que impidan la disponibilidad en el muro (HU-07 / Tarea 07.4).
     */
    public function hasRestriccionPublicacion(): bool
    {
        return $this->isVencida()
            || (bool) $this->devolucion_en_proceso
            || (bool) $this->incidencia_abierta
            || $this->isCancelado()
            || $this->estado === 'Devuelto';
    }

    /**
     * Comprueba disponibilidad real conforme a CA-29, CT-10 y vigencia de HU-07 (14 días).
     */
    public function isDisponible(): bool
    {
        return (bool) $this->disponible
            && $this->estado === self::ESTADO_APROBADA
            && ! $this->hasRestriccionPublicacion();
    }

    /**
     * Scope para consultar reportes actualmente vigentes y disponibles en el muro institucional (HU-07 / Tarea 07.4).
     */
    public function scopeDisponiblesMuro($query)
    {
        $ahora = Carbon::now(self::TIMEZONE);

        return $query->where('disponible', true)
            ->where('estado', self::ESTADO_APROBADA)
            ->where(function ($q) use ($ahora) {
                $q->whereNull('fecha_vencimiento')
                  ->orWhere('fecha_vencimiento', '>', $ahora);
            })
            ->where('devolucion_en_proceso', false)
            ->where('incidencia_abierta', false);
    }

    /**
     * Comprueba si el objeto está bajo resguardo confirmado de biblioteca (CA-20).
     */
    public function isBajoResguardoBiblioteca(): bool
    {
        $lugar = mb_strtolower((string) ($this->lugar_hallazgo ?? ''));
        $area = mb_strtolower((string) ($this->area_zona ?? ''));

        return str_contains($lugar, 'biblioteca') || str_contains($area, 'biblioteca');
    }

    /**
     * Scope para filtrar únicamente los reportes que se encuentran pendientes de revisión administrativa (HU-07 / Tarea 07.1).
     */
    public function scopePendientesRevision($query)
    {
        return $query->whereIn('estado', [
            self::ESTADO_PENDIENTE,
            'Pendiente de revisión',
            'Pendiente',
        ]);
    }

    /**
     * Comprueba si el reporte está disponible para moderación administrativa (HU-07).
     */
    public function isPendienteRevision(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_PENDIENTE,
            'Pendiente de revisión',
            'Pendiente',
        ], true);
    }

    /**
     * Actualiza la fecha del último reenvío a revisión cuando un reporte es corregido (HU-07 / Requerimiento 7).
     */
    public function reenviarARevision(): void
    {
        $this->update([
            'estado' => self::ESTADO_PENDIENTE,
            'fecha_envio_revision' => Carbon::now(self::TIMEZONE),
        ]);
    }
}
