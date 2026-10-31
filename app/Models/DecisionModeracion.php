<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DecisionModeracion extends Model
{
    use HasFactory;

    public const TIPO_CORRECCION_SOLICITADA = 'correccion_solicitada';
    public const TIPO_APROBADA = 'aprobada';
    public const TIPO_CANCELADA = 'cancelada';

    protected $table = 'decisiones_moderacion';

    protected $fillable = [
        'publicacion_id',
        'user_id',
        'tipo_decision',
        'motivo',
        'folio_referencia_duplicado',
        'fecha_decision',
    ];

    protected function casts(): array
    {
        return [
            'fecha_decision' => 'datetime',
        ];
    }

    /**
     * Reporte o publicación asociado a esta decisión.
     */
    public function publicacion(): BelongsTo
    {
        return $this->belongsTo(Publicacion::class);
    }

    /**
     * Administrador o bibliotecario responsable de la decisión.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

