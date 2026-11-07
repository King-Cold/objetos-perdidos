<?php

namespace App\Events;

use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de negocio emitido cuando una publicación vence y requiere entrega a biblioteca (HU-16 Tarea 4).
 *
 * Punto de integración desacoplado para HU-14 (Avisos internos institucionales).
 * Se despacha una sola vez por publicación y vencimiento cuando corresponde el
 * seguimiento «Pendiente de entrega a biblioteca».
 *
 * Informa al autor el plazo de dos días de atención de biblioteca posteriores al vencimiento.
 */
class PublicacionVencidaPendienteEntrega
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Publicacion $publicacion,
        public User $autor,
        public string $plazoTexto
    ) {}
}

