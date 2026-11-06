<?php

namespace App\Events;

use App\Models\Devolucion;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de negocio emitido cuando el autor atiende observaciones y reenvía la devolución a revisión (HU-12 Tarea 3).
 *
 * Punto de integración desacoplado para HU-14 (Avisos internos institucionales).
 */
class DevolucionReenviadaRevision
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Devolucion $devolucion,
        public User $autor
    ) {}
}

