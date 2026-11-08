<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Publicacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DevolucionExtemporaneaController extends Controller
{
    /**
     * Muestra la vista administrativa para «Registrar devolución reportada fuera de tiempo» (CA-02, CA-03).
     *
     * Condiciones estrictas:
     * - Exclusivo para el administrador/bibliotecario autenticado.
     * - Solo disponible en reportes expirados cuyo objeto no haya sido recibido físicamente en biblioteca.
     * - No puede utilizarse cuando exista otra devolución activa, en corrección o verificada que resulte incompatible.
     */
    public function create(int|string $id): View|RedirectResponse
    {
        $user = Auth::user();
        if (! $user || ! method_exists($user, 'isAdmin') || ! $user->isAdmin()) {
            abort(403, 'Acción exclusiva para el personal administrador o bibliotecario.');
        }

        $reporte = Publicacion::with([
            'user',
            'fotografias',
            'recepcion',
            'devoluciones',
            'devolucionActiva',
        ])->findOrFail($id);

        if (! $reporte->puedeRegistrarDevolucionExtemporanea()) {
            return redirect()
                ->route('admin.moderacion.show', $reporte->id)
                ->with('error', 'No es posible registrar una devolución reportada fuera de tiempo para este reporte porque no se encuentra expirado, ya fue recibido en biblioteca o existe otra devolución activa o verificada incompatible.');
        }

        $fotografiaPrincipal = $reporte->foto_principal ?? $reporte->fotografias->first();
        $fotografiasAdicionales = $reporte->fotografias->where('id', '!=', $fotografiaPrincipal?->id);

        return view('admin.devoluciones-extemporaneas.create', compact(
            'reporte',
            'fotografiaPrincipal',
            'fotografiasAdicionales'
        ));
    }
}

