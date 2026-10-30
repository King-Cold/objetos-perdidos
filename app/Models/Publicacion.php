<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Publicacion extends Model
{
    use HasFactory;

    protected $table = 'publicaciones';

    protected $fillable = [
        'user_id',
        'titulo',
        'descripcion',
        'categoria',
        'lugar_hallazgo',
        'estado',
        'disponible',
    ];

    protected function casts(): array
    {
        return [
            'disponible' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Comprueba disponibilidad conforme a CA-29 y CT-10
     */
    public function isDisponible(): bool
    {
        return (bool) $this->disponible && $this->estado === 'Aprobada';
    }
}

