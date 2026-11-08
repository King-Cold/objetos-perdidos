<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDevolucionBibliotecaRequest;
use App\Models\DecisionModeracion;
use App\Models\Devolucion;
use App\Models\Publicacion;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DevolucionBibliotecaController extends Controller
{
    /**
     * Muestra el formulario privado para registrar la devolución física desde biblioteca (HU: Devolución desde biblioteca / Tarea 1).
     *
     * CA-01: Exclusivo para administrador/bibliotecario, con recepción física confirmada,
     * bajo resguardo y sin salida registrada, incluso si la publicación expiró o terminó el resguardo anual.
     * CA-02: Muestra folio, nombre, fotos de referencia y ubicación interna sin permitir cambiar el objeto.
     * CA-04: Formulario con receptor, fecha/hora reales, comprobación presencial y foto con preview.
     */
    public function create(int|string $id): View|RedirectResponse
    {
        $reporte = Publicacion::with([
            'user',
            'fotografias',
            'recepcion',
        ])->findOrFail($id);

        if (! $reporte->puedeRegistrarDevolucionDesdeBiblioteca()) {
            return redirect()->route('admin.inventario.index')
                ->with('error', 'El objeto no cumple con las condiciones para registrar devolución desde biblioteca o ya tiene una salida registrada.');
        }

        $recepcion = $reporte->recepcion;
        $fotografiaPrincipal = $reporte->foto_principal ?? $reporte->fotografias->first();
        $fotografiasAdicionales = $reporte->fotografias->where('id', '!=', $fotografiaPrincipal?->id);

        $ahoraMerida = Carbon::now(Publicacion::TIMEZONE);
        $fechaEntregaDefault = $ahoraMerida->format('Y-m-d');
        $horaEntregaDefault = $ahoraMerida->format('H:i');

        // Mínimo de fecha para entrega: fecha física de recepción
        $minFechaEntrega = $recepcion?->fecha_recepcion
            ? ($recepcion->fecha_recepcion instanceof Carbon ? $recepcion->fecha_recepcion->format('Y-m-d') : Carbon::parse($recepcion->fecha_recepcion)->format('Y-m-d'))
            : null;
        $minHoraEntrega = $recepcion?->hora_recepcion
            ? substr((string) $recepcion->hora_recepcion, 0, 5)
            : null;

        return view('admin.devoluciones-biblioteca.create', compact(
            'reporte',
            'recepcion',
            'fotografiaPrincipal',
            'fotografiasAdicionales',
            'fechaEntregaDefault',
            'horaEntregaDefault',
            'minFechaEntrega',
            'minHoraEntrega'
        ));
    }

    /**
     * Registra conjuntamente la devolución, salida física, fechas y bibliotecario responsable (HU: Devolución desde biblioteca / Tarea 3).
     *
     * CA-06: Confirmación previa requerida antes de completar la operación. Cancelar no modifica información.
     * CA-07: Cierre directo: objeto pasa a «Devuelto», sale de inventario y muro, y muestra mensaje exacto:
     *        «La devolución desde biblioteca se registró correctamente».
     * CA-10: Previene registros duplicados (lock pesimista) y fallos parciales con transacción DB.
     */
    public function store(StoreDevolucionBibliotecaRequest $request, int|string $id): RedirectResponse
    {
        $archivoGuardadoRuta = null;

        try {
            return DB::transaction(function () use ($request, $id, &$archivoGuardadoRuta) {
                // Bloqueo pesimista para evitar colisiones y registros duplicados por doble clic (CA-10)
                /** @var Publicacion $reporte */
                $reporte = Publicacion::with('recepcion')->where('id', $id)->lockForUpdate()->firstOrFail();

                if (! $reporte->puedeRegistrarDevolucionDesdeBiblioteca()) {
                    return redirect()->route('admin.inventario.index')
                        ->with('error', 'El objeto no cumple con las condiciones para registrar devolución desde biblioteca o ya tiene una salida registrada.');
                }

                $admin = Auth::user();
                $ahoraMerida = Carbon::now(Publicacion::TIMEZONE);

                // 1. Almacenamiento seguro de la fotografía de evidencia (disco local privado)
                $archivo = $request->file('fotografia');
                $extension = strtolower($archivo->getClientOriginalExtension());
                $nombreArchivo = 'evidencia_devolucion_' . Str::random(20) . '.' . $extension;
                $rutaDirectorio = 'devoluciones/' . $reporte->id;

                $archivoGuardadoRuta = $archivo->storeAs($rutaDirectorio, $nombreArchivo, 'local');

                // 2. Registrar la devolución como evento independiente (CA-07, CA-08)
                // user_id y admin_id corresponden al bibliotecario responsable que ejecuta la entrega física
                $devolucion = Devolucion::create([
                    'publicacion_id' => $reporte->id,
                    'user_id' => $admin->id,
                    'admin_id' => $admin->id,
                    'receptor_nombre' => $request->validated('receptor_nombre'),
                    'receptor_identificador' => $request->validated('receptor_identificador'),
                    'fecha_entrega' => $request->validated('fecha_entrega'),
                    'hora_entrega' => $request->validated('hora_entrega'),
                    'fecha_captura' => $ahoraMerida,
                    'fecha_envio_revision' => $ahoraMerida,
                    'fecha_resolucion' => $ahoraMerida,
                    'explicacion_propiedad' => $request->validated('explicacion_propiedad'),
                    'fotografia_ruta' => $archivoGuardadoRuta,
                    'fotografia_nombre_original' => $archivo->getClientOriginalName(),
                    'fotografia_mime_type' => $archivo->getClientMimeType(),
                    'fotografia_tamano_bytes' => $archivo->getSize(),
                    'estado' => Devolucion::ESTADO_VERIFICADA,
                ]);

                // 3. Registrar decisión en el historial de moderación
                $motivoDecision = "Devolución física desde biblioteca entregada a {$devolucion->receptor_nombre} ({$devolucion->receptor_identificador}). "
                    . "Comprobación de propiedad: {$devolucion->explicacion_propiedad}";

                DecisionModeracion::create([
                    'publicacion_id' => $reporte->id,
                    'user_id' => $admin->id,
                    'tipo_decision' => DecisionModeracion::TIPO_DEVOLUCION_BIBLIOTECA,
                    'motivo' => $motivoDecision,
                    'fecha_decision' => $ahoraMerida,
                ]);

                // 4. Actualizar el estado del objeto a «Devuelto» y retirarlo del inventario activo y del muro (CA-07, CA-11)
                $reporte->estado = Publicacion::ESTADO_DEVUELTO;
                $reporte->estado_devolucion = 'Devuelto';
                $reporte->disponible = false;
                $reporte->devolucion_en_proceso = false;
                $reporte->save();

                // Mensaje exacto de éxito exigido por CA-07 y CA-10
                return redirect()->route('admin.inventario.index')
                    ->with('status', 'La devolución desde biblioteca se registró correctamente.');
            });
        } catch (\Throwable $e) {
            // Revertir archivo subido si falla la base de datos (CA-10)
            if ($archivoGuardadoRuta && Storage::disk('local')->exists($archivoGuardadoRuta)) {
                Storage::disk('local')->delete($archivoGuardadoRuta);
            }

            Log::error('Error al registrar devolución desde biblioteca: ' . $e->getMessage(), [
                'publicacion_id' => $id,
                'admin_id' => Auth::id(),
                'exception' => $e,
            ]);

            return redirect()->route('admin.devoluciones-biblioteca.create', $id)
                ->withInput()
                ->withErrors(['general' => 'Ocurrió un error inesperado al procesar la devolución. Intenta nuevamente.'])
                ->with('error', 'Ocurrió un error inesperado al procesar la devolución. Intenta nuevamente.');
        }
    }
}

