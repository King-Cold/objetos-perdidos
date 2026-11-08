<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUbicacionRecepcionRequest;
use App\Models\DecisionModeracion;
use App\Models\Publicacion;
use App\Models\Recepcion;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class InventarioController extends Controller
{
    /**
     * Muestra el inventario de objetos bajo resguardo físico en biblioteca (HU-16 / Tarea 2).
     */
    public function index(): View
    {
        $recepciones = Recepcion::with([
            'publicacion.fotografias',
            'publicacion.user',
            'user',
            'actualizador',
        ])
        ->whereHas('publicacion', function ($q) {
            $q->where('estado', '!=', Publicacion::ESTADO_DEVUELTO)
              ->where(function ($sub) {
                  $sub->whereNull('estado_devolucion')
                      ->orWhereNotIn('estado_devolucion', ['Devuelto', 'Verificada']);
              });
        })
        ->orderBy('fecha_recepcion', 'asc')
        ->orderBy('hora_recepcion', 'asc')
        ->orderBy('id', 'asc')
        ->paginate(15);

        // Publicaciones vencidas pendientes de recepción física en biblioteca
        $pendientesRecepcion = Publicacion::with(['user', 'fotografias'])
            ->whereDoesntHave('recepcion')
            ->where('situacion_resguardo', '!=', 'En biblioteca')
            ->where('estado', '!=', Publicacion::ESTADO_DEVUELTO)
            ->where('devolucion_en_proceso', false)
            ->get()
            ->filter(fn (Publicacion $p) => $p->esElegibleParaRecepcionFisica());

        return view('admin.inventario.index', compact('recepciones', 'pendientesRecepcion'));
    }

    /**
     * Muestra el detalle completo del resguardo institucional de un objeto (HU-16 / Tarea 2 y 3).
     */
    public function show(int|string $id): View
    {
        $recepcion = Recepcion::with([
            'publicacion.fotografias',
            'publicacion.user',
            'user',
            'actualizador',
        ])
        ->where('id', $id)
        ->orWhere('publicacion_id', $id)
        ->firstOrFail();

        $reporte = $recepcion->publicacion;
        $fotografiaPrincipal = $reporte->foto_principal;
        $fotografiasAdicionales = $reporte->fotografias->where('id', '!=', $fotografiaPrincipal?->id);

        return view('admin.inventario.show', compact(
            'recepcion',
            'reporte',
            'fotografiaPrincipal',
            'fotografiasAdicionales'
        ));
    }

    /**
     * Actualiza la ubicación física interna del objeto bajo resguardo en biblioteca (HU-16 / Tarea 3).
     */
    public function updateUbicacion(UpdateUbicacionRecepcionRequest $request, int|string $id): RedirectResponse
    {
        $recepcion = Recepcion::with(['publicacion', 'user'])
            ->where('id', $id)
            ->orWhere('publicacion_id', $id)
            ->firstOrFail();

        $reporte = $recepcion->publicacion;

        // Comprobar que el objeto continúe bajo resguardo físico de biblioteca
        if ($reporte->situacion_resguardo !== 'En biblioteca') {
            return redirect()->route('admin.inventario.show', $recepcion->id)
                ->withErrors(['ubicacion_resguardo' => 'El objeto no se encuentra actualmente bajo resguardo en biblioteca.'])
                ->with('error', 'El objeto no se encuentra actualmente bajo resguardo en biblioteca.');
        }

        $nuevaUbicacion = trim((string) $request->validated('ubicacion_resguardo'));
        $ubicacionAnterior = $recepcion->ubicacion_resguardo;
        $admin = $request->user();
        $ahoraMerida = Carbon::now(Recepcion::TIMEZONE);

        try {
            DB::transaction(function () use ($recepcion, $reporte, $admin, $nuevaUbicacion, $ubicacionAnterior, $ahoraMerida) {
                // Actualizar la ubicación interna y registrar responsable y fecha
                $recepcion->ubicacion_resguardo = $nuevaUbicacion;
                $recepcion->actualizado_por_id = $admin->id;
                $recepcion->fecha_cambio_ubicacion = $ahoraMerida;
                $recepcion->save();

                // Registrar evento administrativo en el historial de moderación
                $motivoDecision = "Cambio de ubicación física en biblioteca. Ubicación anterior: {$ubicacionAnterior}. Nueva ubicación: {$nuevaUbicacion}.";

                DecisionModeracion::create([
                    'publicacion_id' => $reporte->id,
                    'user_id' => $admin->id,
                    'tipo_decision' => DecisionModeracion::TIPO_CAMBIO_UBICACION_BIBLIOTECA,
                    'motivo' => $motivoDecision,
                    'fecha_decision' => $ahoraMerida,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Error al actualizar la ubicación física en biblioteca: ' . $e->getMessage(), [
                'recepcion_id' => $recepcion->id,
                'publicacion_id' => $reporte->id,
                'admin_id' => $admin->id,
                'exception' => $e,
            ]);

            return redirect()->route('admin.inventario.show', $recepcion->id)
                ->withErrors(['ubicacion_resguardo' => 'Ocurrió un error inesperado al actualizar la ubicación interna.'])
                ->with('error', 'Ocurrió un error inesperado al actualizar la ubicación interna.');
        }

        return redirect()->route('admin.inventario.show', $recepcion->id)
            ->with('status', 'La ubicación interna del objeto se actualizó correctamente.');
    }
}
