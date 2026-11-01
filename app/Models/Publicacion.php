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

    public const TIMEZONE = 'America/Merida';

    public const ESTADO_PENDIENTE = 'Pendiente de aprobación';
    public const ESTADO_APROBADA = 'Aprobada';
    public const ESTADO_REQUIERE_CORRECCION = 'Requiere corrección';
    public const ESTADO_CORRECCION_SOLICITADA = 'Corrección solicitada';
    public const ESTADO_CANCELADO = 'Cancelado';
    public const ESTADO_DEVUELTO = 'Devuelto';

    public const RESGUARDO_AUTOR = 'En posesión del autor';
    public const RESGUARDO_CONFIRMADO = 'Bajo resguardo confirmado de biblioteca';

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
        'motivo_correccion',
        'observaciones_admin',
        'situacion_resguardo',
        'motivo_cancelacion',
        'fecha_cancelacion',
        'estado_devolucion',
        'tiene_incidencia',
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
            'fecha_cancelacion' => 'datetime',
            'tiene_incidencia' => 'boolean',
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
                $publicacion->fecha_envio_revision = $publicacion->fecha_captura
                    ?? Carbon::now(self::TIMEZONE);
            }

            if (empty($publicacion->titulo) && ! empty($publicacion->nombre)) {
                $publicacion->titulo = $publicacion->nombre;
            } elseif (empty($publicacion->nombre) && ! empty($publicacion->titulo)) {
                $publicacion->nombre = $publicacion->titulo;
            }
        });
    }

    /**
     * Genera un folio con formato OBJ-YYYYMMDD-XXXX.
     */
    public static function generarFolio(): string
    {
        $fechaPrefijo = Carbon::now(self::TIMEZONE)->format('Ymd');
        $prefix = "OBJ-{$fechaPrefijo}-";

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fotografias(): HasMany
    {
        return $this->hasMany(Fotografia::class);
    }

    public function decisiones(): HasMany
    {
        return $this->hasMany(DecisionModeracion::class)
            ->orderBy('id', 'desc');
    }

    public function isCorreccionSolicitada(): bool
    {
        return $this->estado === self::ESTADO_CORRECCION_SOLICITADA;
    }

    public function isCancelado(): bool
    {
        return in_array(
            $this->estado,
            [self::ESTADO_CANCELADO, 'Cancelada'],
            true
        );
    }

    public function isCancelable(): bool
    {
        return ! $this->isCancelado()
            && $this->estado !== self::ESTADO_DEVUELTO;
    }

    public function isVencida(): bool
    {
        if ($this->fecha_vencimiento === null) {
            return false;
        }

        return Carbon::now(self::TIMEZONE)
            ->greaterThanOrEqualTo($this->fecha_vencimiento);
    }

    public function hasRestriccionPublicacion(): bool
    {
        return $this->isVencida()
            || (bool) $this->devolucion_en_proceso
            || (bool) $this->incidencia_abierta
            || $this->isCancelado()
            || $this->estado === self::ESTADO_DEVUELTO;
    }

    public function isDisponible(): bool
    {
        return (bool) $this->disponible
            && $this->estado === self::ESTADO_APROBADA
            && ! $this->hasRestriccionPublicacion();
    }

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

    public function isExpirado(): bool
    {
        return $this->isVencida();
    }

    public function getVigenciaTextoAttribute(): string
    {
        if (! $this->fecha_primera_aprobacion) {
            return 'Aún no inicia su vigencia';
        }

        if ($this->fecha_vencimiento) {
            if ($this->isExpirado()) {
                return 'Vencido el '
                    . $this->fecha_vencimiento->format('d/m/Y');
            }

            return 'Vence el '
                . $this->fecha_vencimiento->format('d/m/Y');
        }

        return 'En revisión';
    }

    public function getFechaRegistroTextoAttribute(): string
    {
        if (! empty($this->fecha_captura)) {
            $raw = $this->getRawOriginal('fecha_captura');

            if ($raw) {
                $formatted = Carbon::parse($raw)->format('d/m/Y • h:i a');

                return str_replace(
                    ['am', 'pm'],
                    ['a. m.', 'p. m.'],
                    $formatted
                );
            }

            $formatted = $this->fecha_captura->format('d/m/Y • h:i a');

            return str_replace(
                ['am', 'pm'],
                ['a. m.', 'p. m.'],
                $formatted
            );
        }

        if (! empty($this->created_at)) {
            $formatted = $this->created_at
                ->timezone(self::TIMEZONE)
                ->format('d/m/Y • h:i a');

            return str_replace(
                ['am', 'pm'],
                ['a. m.', 'p. m.'],
                $formatted
            );
        }

        return '';
    }

    public function getFechaCancelacionTextoAttribute(): string
    {
        if (! $this->fecha_cancelacion) {
            return '';
        }

        $formatted = $this->fecha_cancelacion
            ->timezone(self::TIMEZONE)
            ->format('d/m/Y • h:i a');

        return str_replace(
            ['am', 'pm'],
            ['a. m.', 'p. m.'],
            $formatted
        );
    }

    public function getFechaHallazgoTextoAttribute(): string
    {
        if (! $this->fecha_hallazgo) {
            return 'No especificada';
        }

        $fechaStr = $this->fecha_hallazgo instanceof Carbon
            ? $this->fecha_hallazgo->format('d/m/Y')
            : Carbon::parse($this->fecha_hallazgo)->format('d/m/Y');

        if (! empty($this->hora_hallazgo)) {
            try {
                $horaRaw = Carbon::parse($this->hora_hallazgo)
                    ->format('h:i a');

                $horaStr = str_replace(
                    ['am', 'pm'],
                    ['a. m.', 'p. m.'],
                    $horaRaw
                );

                return "{$fechaStr} • {$horaStr}";
            } catch (\Throwable $e) {
                return "{$fechaStr} • {$this->hora_hallazgo}";
            }
        }

        return $fechaStr;
    }

    public function isEnCorreccion(): bool
    {
        return in_array(
            $this->estado,
            [self::ESTADO_REQUIERE_CORRECCION, self::ESTADO_CORRECCION_SOLICITADA],
            true
        );
    }

    public function puedeEditarse(): bool
    {
        if (
            $this->estado === self::ESTADO_CANCELADO
            || $this->estado === self::ESTADO_DEVUELTO
        ) {
            return false;
        }

        if ($this->isExpirado()) {
            return false;
        }

        if (
            $this->tiene_incidencia
            || in_array(
                $this->estado_devolucion,
                ['Pendiente de verificación', 'Verificada', 'En incidencia'],
                true
            )
        ) {
            return false;
        }

        if ($this->situacion_resguardo === self::RESGUARDO_CONFIRMADO) {
            return false;
        }

        if (
            in_array(
                $this->estado,
                [
                    self::ESTADO_PENDIENTE,
                    self::ESTADO_REQUIERE_CORRECCION,
                    self::ESTADO_CORRECCION_SOLICITADA,
                ],
                true
            )
        ) {
            return true;
        }

        if (
            $this->estado === self::ESTADO_APROBADA
            && (bool) $this->disponible
        ) {
            return true;
        }

        return false;
    }

    public function puedeCancelarse(): bool
    {
        if (
            ! in_array(
                $this->estado,
                [
                    self::ESTADO_PENDIENTE,
                    self::ESTADO_REQUIERE_CORRECCION,
                    self::ESTADO_CORRECCION_SOLICITADA,
                ],
                true
            )
        ) {
            return false;
        }

        if ($this->tiene_incidencia || ! empty($this->estado_devolucion)) {
            return false;
        }

        if ($this->situacion_resguardo === self::RESGUARDO_CONFIRMADO) {
            return false;
        }

        return true;
    }

    public function getFotoPrincipalAttribute(): ?Fotografia
    {
        return $this->fotografias->firstWhere('es_principal', true)
            ?? $this->fotografias->first();
    }

    public function isBajoResguardoBiblioteca(): bool
    {
        $lugar = mb_strtolower((string) ($this->lugar_hallazgo ?? ''));
        $area = mb_strtolower((string) ($this->area_zona ?? ''));

        return str_contains($lugar, 'biblioteca')
            || str_contains($area, 'biblioteca');
    }

    public function scopePendientesRevision($query)
    {
        return $query->whereIn('estado', [
            self::ESTADO_PENDIENTE,
            'Pendiente de revisión',
            'Pendiente',
        ]);
    }

    public function isPendienteRevision(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_PENDIENTE,
            'Pendiente de revisión',
            'Pendiente',
        ], true);
    }

    public function reenviarARevision(): void
    {
        $this->update([
            'estado' => self::ESTADO_PENDIENTE,
            'fecha_envio_revision' => Carbon::now(self::TIMEZONE),
        ]);
    }
}