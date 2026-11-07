<?php

namespace App\Listeners;

use App\Events\DevolucionAprobada;
use App\Events\DevolucionCorreccionSolicitada;
use App\Services\AvisoService;
use Illuminate\Support\Facades\Log;

class GenerarAvisoDevolucionListener
{
    /**
     * Maneja el evento de devolución física aprobada.
     */
    public function handleDevolucionAprobada(DevolucionAprobada $event): void
    {
        try {
            AvisoService::generarAvisoDevolucionAprobada($event->devolucion);
        } catch (\Throwable $e) {
            Log::error('Error en GenerarAvisoDevolucionListener al procesar DevolucionAprobada: ' . $e->getMessage(), [
                'devolucion_id' => $event->devolucion->id ?? null,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Maneja el evento de solicitud de corrección en devolución física.
     */
    public function handleDevolucionCorreccionSolicitada(DevolucionCorreccionSolicitada $event): void
    {
        try {
            AvisoService::generarAvisoDevolucionCorreccion($event->devolucion, $event->motivo);
        } catch (\Throwable $e) {
            Log::error('Error en GenerarAvisoDevolucionListener al procesar DevolucionCorreccionSolicitada: ' . $e->getMessage(), [
                'devolucion_id' => $event->devolucion->id ?? null,
                'exception' => $e,
            ]);
        }
    }
}

