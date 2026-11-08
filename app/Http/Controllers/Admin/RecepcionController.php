<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecepcionRequest;
use App\Models\DecisionModeracion;
use App\Models\Publicacion;
use App\Models\Recepcion;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class RecepcionController extends Controller
{
    /**
     * Muestra el cotejo físico previo y el formulario de recepción por vencimiento.
     */
    public function create(int|string $id): View|RedirectResponse
    {
        $reporte = Publicacion::with(['user', 'fotografias'])->findOrFail($id);

        if (! $reporte->esElegibleParaRecepcionFisica()) {
            return redirect()->route('admin.moderacion.show', $reporte->id)
                ->with('error', 'El reporte no cumple las condiciones para registrar recepción física por vencimiento.');
        }

        $fotografiaPrincipal = $reporte->fotografias->where('es_principal', true)->first()
            ?? $reporte->fotografias->first();
        $fotografiasAdicionales = $reporte->fotografias->where('id', '!=', $fotografiaPrincipal?->id);

        $ahoraMerida = Carbon::now(Publicacion::TIMEZONE);
        $fechaRecepcionDefault = $ahoraMerida->format('Y-m-d');
        $horaRecepcionDefault = $ahoraMerida->format('H:i');

        return view('admin.recepciones.create', compact(
            'reporte',
            'fotografiaPrincipal',
            'fotografiasAdicionales',
            'fechaRecepcionDefault',
            'horaRecepcionDefault'
        ));
    }

    /**
     * Registra la recepción física del objeto bajo resguardo en biblioteca.
     */
    public function store(StoreRecepcionRequest $request, int|string $id): RedirectResponse
    {
        $reporte = Publicacion::findOrFail($id);

        if (! $reporte->esElegibleParaRecepcionFisica()) {
            return redirect()->route('admin.moderacion.show', $reporte->id)
                ->withErrors(['recepcion' => 'El reporte no cumple las condiciones para registrar recepción física por vencimiento.'])
                ->with('error', 'El reporte no cumple las condiciones para registrar recepción física por vencimiento.');
        }

        $admin = $request->user();
        $ahoraMerida = Carbon::now(Publicacion::TIMEZONE);

        $fechaRecepcion = $request->validated('fecha_recepcion');
        $horaRecepcion = substr((string) $request->validated('hora_recepcion'), 0, 5);
        $condicionObjeto = $request->validated('condicion_objeto');
        $ubicacionResguardo = $request->validated('ubicacion_resguardo');
        $observaciones = $request->validated('observaciones');

        try {
            DB::transaction(function () use (
                $id,
                $admin,
                $ahoraMerida,
                $fechaRecepcion,
                $horaRecepcion,
                $condicionObjeto,
                $ubicacionResguardo,
                $observaciones
            ) {
                // Bloqueo pesimista para evitar concurrencia y doble envío
                $lockedReporte = Publicacion::where('id', $id)->lockForUpdate()->firstOrFail();

                if (! $lockedReporte->esElegibleParaRecepcionFisica()) {
                    throw new \DomainException('El reporte ya no es elegible para registrar recepción física.');
                }

                // 1. Registrar la recepción física
                Recepcion::create([
                    'publicacion_id' => $lockedReporte->id,
                    'user_id' => $admin->id,
                    'fecha_recepcion' => $fechaRecepcion,
                    'hora_recepcion' => $horaRecepcion,
                    'fecha_captura' => $ahoraMerida,
                    'condicion_objeto' => $condicionObjeto,
                    'ubicacion_resguardo' => $ubicacionResguardo,
                    'observaciones' => $observaciones,
                    'confirmacion_cotejo' => true,
                    'fecha_limite_anual' => Recepcion::calcularFechaLimiteAnual($fechaRecepcion),
                ]);

                // 2. Actualizar el estado y situación del reporte
                $lockedReporte->situacion_resguardo = 'En biblioteca';
                $lockedReporte->disponible = false;
                $lockedReporte->save();

                // 3. Registrar en historial de decisiones de moderación
                $motivoDecision = "Recepción física registrada en biblioteca. Ubicación asignada: {$ubicacionResguardo}."
                    . (! empty($condicionObjeto) ? " Condición: {$condicionObjeto}." : '');

                DecisionModeracion::create([
                    'publicacion_id' => $lockedReporte->id,
                    'user_id' => $admin->id,
                    'tipo_decision' => 'recepcion_biblioteca',
                    'motivo' => $motivoDecision,
                    'fecha_decision' => $ahoraMerida,
                ]);
            });
        } catch (\DomainException $de) {
            return redirect()->route('admin.moderacion.show', $reporte->id)
                ->withErrors(['recepcion' => $de->getMessage()])
                ->with('error', $de->getMessage());
        } catch (\Throwable $e) {
            Log::error('Error al registrar recepción física por vencimiento: ' . $e->getMessage(), [
                'publicacion_id' => $reporte->id,
                'admin_id' => $admin->id,
                'exception' => $e,
            ]);

            return redirect()->route('admin.moderacion.show', $reporte->id)
                ->withErrors(['recepcion' => 'Ocurrió un error inesperado al registrar la recepción. Intenta nuevamente.'])
                ->with('error', 'Ocurrió un error inesperado al registrar la recepción. Intenta nuevamente.');
        }

        return redirect()->route('admin.moderacion.show', $reporte->id)
            ->with('status', 'La recepción del objeto se registró correctamente');
    }
}

