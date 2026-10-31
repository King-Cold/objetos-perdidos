<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Fotografia extends Model
{
    use HasFactory;

    protected $table = 'fotografias';

    protected $fillable = [
        'publicacion_id',
        'ruta',
        'nombre_original',
        'mime_type',
        'tamano_bytes',
    ];

    /**
     * Relación: La fotografía pertenece a una publicación (reporte de hallazgo).
     */
    public function publicacion(): BelongsTo
    {
        return $this->belongsTo(Publicacion::class);
    }

    /**
     * Obtiene la URL de acceso privado y controlado para la fotografía (Tarea 05.5).
     */
    public function getUrlAttribute(): string
    {
        return route('fotografias.show', $this->id);
    }
}

