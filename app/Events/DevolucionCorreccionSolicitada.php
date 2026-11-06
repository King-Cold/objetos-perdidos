<?php

namespace App\Events;

use App\Models\Devolucion;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de negocio emitido cuando se solicita corrección de una devolución física (HU-12 Tarea 2).
 * 
 * Punto de integración para HU-14 (Avisos internos institucionales):
 * La entrega efectiva del aviso interno al autor queda pendiente de la implementación de HU-14.
 * Este evento transmite el motivo documentado sin exponer datos privados innecesarios.
 */
class DevolucionCorreccionSolicitada
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Devolucion $devolucion,
        public User $admin,
        public string $motivo
    ) {}
}

