<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Aviso extends Model
{
    use HasFactory;

    public const TIMEZONE = 'America/Merida';

    // Tipos de eventos institucionales
    public const TIPO_PUBLICACION_APROBADA = 'publicacion_aprobada';
    public const TIPO_PUBLICACION_CORRECCION = 'publicacion_correccion';
    public const TIPO_PUBLICACION_CANCELADA = 'publicacion_cancelada';
    public const TIPO_PUBLICACION_VENCIDA = 'publicacion_vencida';
    public const TIPO_DEVOLUCION_APROBADA = 'devolucion_aprobada';
    public const TIPO_DEVOLUCION_CORRECCION = 'devolucion_correccion';

    protected $table = 'avisos';

    protected $fillable = [
        'user_id',
        'publicacion_id',
        'devolucion_id',
        'tipo_evento',
        'titulo',
        'folio_reporte',
        'mensaje',
        'motivo_autorizado',
        'enlace_destino',
        'leido',
        'leido_at',
        'clave_duplicado',
    ];

    protected function casts(): array
    {
        return [
            'leido' => 'boolean',
            'leido_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Usuario destinatario del aviso.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Publicación o reporte de hallazgo relacionado (si aplica).
     */
    public function publicacion(): BelongsTo
    {
        return $this->belongsTo(Publicacion::class);
    }

    /**
     * Proceso de devolución física relacionado (si aplica).
     */
    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class);
    }

    /**
     * Scope para filtrar avisos pertenecientes a un usuario.
     */
    public function scopeParaUsuario($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para avisos no leídos.
     */
    public function scopeNoLeidos($query)
    {
        return $query->where('leido', false);
    }

    /**
     * Scope para avisos leídos.
     */
    public function scopeLeidos($query)
    {
        return $query->where('leido', true);
    }

    /**
     * Scope para orden cronológico descendente (más recientes primero).
     */
    public function scopeRecientes($query)
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Comprueba si el aviso ha sido leído.
     */
    public function isLeido(): bool
    {
        return (bool) $this->leido;
    }

    /**
     * Marca el aviso como leído de forma persistente registrando la fecha y hora.
     * Si ya estaba leído, retorna false para evitar modificaciones innecesarias.
     */
    public function marcarComoLeido(): bool
    {
        if ($this->leido) {
            return false;
        }

        return (bool) $this->update([
            'leido' => true,
            'leido_at' => now(),
        ]);
    }

    /**
     * Obtiene la fecha y hora formateada en la zona horaria del campus.
     */
    public function getFechaFormateadaAttribute(): string
    {
        $fecha = $this->created_at ?? Carbon::now(self::TIMEZONE);
        if (! $fecha instanceof Carbon) {
            $fecha = Carbon::parse($fecha, self::TIMEZONE);
        } else {
            $fecha = $fecha->copy()->setTimezone(self::TIMEZONE);
        }

        return $fecha->format('d/m/Y H:i');
    }

    /**
     * Retorna la etiqueta legible del tipo de evento.
     */
    public function getTipoEtiquetaAttribute(): string
    {
        return match ($this->tipo_evento) {
            self::TIPO_PUBLICACION_APROBADA => 'Aprobación de publicación',
            self::TIPO_PUBLICACION_CORRECCION => 'Corrección requerida',
            self::TIPO_PUBLICACION_CANCELADA => 'Cancelación administrativa',
            self::TIPO_PUBLICACION_VENCIDA => 'Aviso de vencimiento',
            self::TIPO_DEVOLUCION_APROBADA => 'Devolución aprobada',
            self::TIPO_DEVOLUCION_CORRECCION => 'Corrección de devolución',
            default => 'Aviso de proceso',
        };
    }

    /**
     * Retorna los estilos Tailwind correspondientes al tipo de evento.
     */
    public function getColorBadgeAttribute(): string
    {
        return match ($this->tipo_evento) {
            self::TIPO_PUBLICACION_APROBADA, self::TIPO_DEVOLUCION_APROBADA => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            self::TIPO_PUBLICACION_CORRECCION, self::TIPO_DEVOLUCION_CORRECCION => 'bg-amber-50 text-amber-800 border-amber-200',
            self::TIPO_PUBLICACION_CANCELADA => 'bg-rose-50 text-rose-800 border-rose-200',
            self::TIPO_PUBLICACION_VENCIDA => 'bg-slate-100 text-slate-800 border-slate-300',
            default => 'bg-blue-50 text-blue-800 border-blue-200',
        };
    }

    /**
     * Determina si el aviso está vinculado a un proceso de devolución física.
     */
    public function esDevolucion(): bool
    {
        return $this->devolucion_id !== null
            || str_starts_with((string) $this->tipo_evento, 'devolucion_')
            || str_contains((string) $this->enlace_destino, '/devoluciones/');
    }

    /**
     * Retorna el texto del botón de acción según el proceso relacionado («Ver reporte» o «Ver devolución»).
     */
    public function getTextoAccionAttribute(): string
    {
        return $this->esDevolucion() ? 'Ver devolución' : 'Ver reporte';
    }
}

