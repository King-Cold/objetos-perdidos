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

    protected $table = 'devoluciones';

    protected $fillable = [
        'publicacion_id',
        'user_id',
        'receptor_nombre',
        'receptor_identificador',
        'fecha_entrega',
        'hora_entrega',
        'fecha_captura',
        'explicacion_propiedad',
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
            'fotografia_tamano_bytes' => 'integer',
        ];
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

        $dt = $this->fecha_captura instanceof Carbon
            ? $this->fecha_captura
            : Carbon::parse($this->fecha_captura);

        $formatted = $dt->timezone(Publicacion::TIMEZONE)->format('d/m/Y • h:i a');
        return str_replace(['am', 'pm'], ['a. m.', 'p. m.'], $formatted);
    }
}
