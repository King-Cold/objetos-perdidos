<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recepcion extends Model
{
    use HasFactory;

    public const TIMEZONE = 'America/Merida';

    protected $table = 'recepciones';

    protected $fillable = [
        'publicacion_id',
        'user_id',
        'actualizado_por_id',
        'fecha_recepcion',
        'hora_recepcion',
        'fecha_captura',
        'fecha_cambio_ubicacion',
        'condicion_objeto',
        'ubicacion_resguardo',
        'observaciones',
        'confirmacion_cotejo',
        'fecha_limite_anual',
    ];

    protected function casts(): array
    {
        return [
            'fecha_recepcion' => 'date:Y-m-d',
            'fecha_captura' => 'datetime',
            'fecha_cambio_ubicacion' => 'datetime',
            'confirmacion_cotejo' => 'boolean',
            'fecha_limite_anual' => 'date:Y-m-d',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Recepcion $recepcion) {
            if (empty($recepcion->fecha_captura)) {
                $recepcion->fecha_captura = Carbon::now(self::TIMEZONE);
            }

            if (empty($recepcion->fecha_limite_anual) && ! empty($recepcion->fecha_recepcion)) {
                $recepcion->fecha_limite_anual = static::calcularFechaLimiteAnual(
                    $recepcion->fecha_recepcion,
                    $recepcion->hora_recepcion
                )->toDateString();
            }
        });
    }

    /**
     * Calcula la fecha límite de resguardo anual (1 año natural con regla addYearNoOverflow en America/Merida).
     *
     * Ejemplos:
     * - 10/11/2026 10:30 -> 10/11/2027 10:30
     * - 29/02/2024 15:45 -> 28/02/2025 15:45
     * - 28/02/2024 09:00 -> 28/02/2025 09:00
     */
    public static function calcularFechaLimiteAnual(\DateTimeInterface|string $fechaRecepcion, ?string $horaRecepcion = null): Carbon
    {
        $fechaStr = $fechaRecepcion instanceof \DateTimeInterface
            ? $fechaRecepcion->format('Y-m-d')
            : (string) $fechaRecepcion;

        if (str_contains($fechaStr, ' ')) {
            $partesEspacio = explode(' ', $fechaStr);
            $fechaStr = $partesEspacio[0];
            if (empty($horaRecepcion) && ! empty($partesEspacio[1])) {
                $horaRecepcion = $partesEspacio[1];
            }
        }

        $fecha = Carbon::parse($fechaStr, self::TIMEZONE)->startOfDay();

        if (! empty($horaRecepcion)) {
            $horaLimpa = trim($horaRecepcion);
            $partes = explode(':', $horaLimpa);
            if (count($partes) >= 2) {
                $fecha->setTime((int) $partes[0], (int) $partes[1], (int) ($partes[2] ?? 0));
            }
        }

        return $fecha->addYearNoOverflow();
    }

    /**
     * Obtiene la fecha y hora completa en que expira el resguardo anual.
     */
    public function getFechaLimiteAnualCompletaAttribute(): Carbon
    {
        $rawRecepcion = $this->getRawOriginal('fecha_recepcion') ?? $this->fecha_recepcion;
        if ($rawRecepcion) {
            return static::calcularFechaLimiteAnual($rawRecepcion, $this->hora_recepcion);
        }

        $rawLimite = $this->getRawOriginal('fecha_limite_anual') ?? $this->fecha_limite_anual;
        $limiteStr = $rawLimite instanceof \DateTimeInterface
            ? $rawLimite->format('Y-m-d')
            : (string) $rawLimite;

        $limite = Carbon::parse($limiteStr, self::TIMEZONE)->startOfDay();

        if (! empty($this->hora_recepcion)) {
            $partes = explode(':', trim($this->hora_recepcion));
            if (count($partes) >= 2) {
                $limite->setTime((int) $partes[0], (int) $partes[1], (int) ($partes[2] ?? 0));
            }
        }

        return $limite;
    }

    /**
     * Determina si el periodo de resguardo anual (1 año natural) ha concluido.
     */
    public function isAnualVencido(): bool
    {
        $limiteCompleto = $this->fecha_limite_anual_completa;
        if (! $limiteCompleto) {
            return false;
        }

        return Carbon::now(self::TIMEZONE)->greaterThanOrEqualTo($limiteCompleto);
    }

    /**
     * Obtiene la descripción textual del estado institucional del resguardo.
     */
    public function getEstadoResguardoAttribute(): string
    {
        if ($this->isAnualVencido()) {
            return 'Resguardo vencido';
        }

        return 'Bajo resguardo';
    }

    /**
     * Obtiene la fecha de captura en formato d/m/Y respetando el valor original.
     */
    public function getFechaCapturaFechaTextoAttribute(): string
    {
        if (! $this->fecha_captura) {
            return 'No registrada';
        }

        $raw = $this->getRawOriginal('fecha_captura');
        if ($raw) {
            return Carbon::parse($raw)->format('d/m/Y');
        }

        $dt = $this->fecha_captura instanceof Carbon
            ? $this->fecha_captura
            : Carbon::parse($this->fecha_captura);

        return $dt->format('d/m/Y');
    }

    /**
     * Obtiene la hora de captura en formato H:i respetando el valor original.
     */
    public function getHoraCapturaTextoAttribute(): string
    {
        if (! $this->fecha_captura) {
            return '';
        }

        $raw = $this->getRawOriginal('fecha_captura');
        if ($raw) {
            return Carbon::parse($raw)->format('H:i');
        }

        $dt = $this->fecha_captura instanceof Carbon
            ? $this->fecha_captura
            : Carbon::parse($this->fecha_captura);

        return $dt->format('H:i');
    }

    /**
     * Obtiene la fecha y hora completa de captura para presentación.
     */
    public function getFechaCapturaTextoAttribute(): string
    {
        if (! $this->fecha_captura) {
            return 'No registrada';
        }

        return "{$this->fecha_captura_fecha_texto} • {$this->hora_captura_texto} hrs";
    }

    /**
     * Obtiene la fecha y hora formateada del último cambio de ubicación.
     */
    public function getFechaCambioUbicacionTextoAttribute(): string
    {
        if (! $this->fecha_cambio_ubicacion) {
            return '';
        }

        $raw = $this->getRawOriginal('fecha_cambio_ubicacion');
        if ($raw) {
            $dt = Carbon::parse($raw, self::TIMEZONE);
            return $dt->format('d/m/Y • H:i') . ' hrs';
        }

        $dt = $this->fecha_cambio_ubicacion instanceof Carbon
            ? $this->fecha_cambio_ubicacion->copy()->setTimezone(self::TIMEZONE)
            : Carbon::parse($this->fecha_cambio_ubicacion, self::TIMEZONE);

        return $dt->format('d/m/Y • H:i') . ' hrs';
    }

    public function publicacion(): BelongsTo
    {
        return $this->belongsTo(Publicacion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Administrador responsable del último cambio de ubicación interna.
     */
    public function actualizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por_id');
    }

    /**
     * Comprueba si el objeto resguardado puede ser devuelto desde biblioteca (CA-01).
     */
    public function puedeRegistrarDevolucionDesdeBiblioteca(): bool
    {
        if (! $this->publicacion) {
            return false;
        }

        return $this->publicacion->puedeRegistrarDevolucionDesdeBiblioteca();
    }
}

