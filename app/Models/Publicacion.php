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
        'categoria',
        'lugar_hallazgo',
        'estado',
        'disponible',
    ];

    protected function casts(): array
    {
        return [
            'disponible' => 'boolean',
            'fecha_hallazgo' => 'date:Y-m-d',
            'fecha_captura' => 'datetime',
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
     * Comprueba disponibilidad conforme a CA-29 y CT-10
     */
    public function isDisponible(): bool
    {
        return (bool) $this->disponible && $this->estado === 'Aprobada';
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
}
