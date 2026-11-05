<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDevolucionRequest;
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

class DevolucionController extends Controller
{
    /**
     * Muestra el formulario para registrar la devolución física de un objeto bajo resguardo del autor.
     * HU-11 Tarea 1: CA-01, CA-02, CA-03, CA-04, CA-05, CA-06.
     */
    public function create(int|string $id): View|RedirectResponse
    {
        $publicacion = Publicacion::findOrFail($id);

        // CA-01, CA-09: Disponible únicamente para el autor original con sesión activa
        if ($publicacion->user_id !== Auth::id()) {
            abort(403, 'No tienes permiso para registrar la devolución de este reporte.');
        }

        // CA-01, CA-12: Verificación de vigencia y mensaje especial en caso de vencimiento
        if ($publicacion->isVencida()) {
            return redirect()->route('reportes.mis-reportes')->with('warning', 
                'La publicación ha vencido. Si entregaste el objeto antes del vencimiento y no registraste la devolución, contacta a biblioteca e indica el folio del reporte.'
            );
        }

        // CA-01: Verificación de condiciones de elegibilidad del reporte en el servidor
        if (! $publicacion->puedeRegistrarDevolucion()) {
            return redirect()->route('reportes.mis-reportes')->with('warning', 
                'No es posible registrar la devolución para este reporte debido a su estado o situación actual.'
            );
        }

        // Parámetros de fecha/hora de referencia para el formulario
        $ahoraMerida = Carbon::now(Publicacion::TIMEZONE);
        $maxFechaEntrega = $ahoraMerida->format('Y-m-d');
        $maxHoraEntrega = $ahoraMerida->format('H:i');

        $minFechaEntrega = null;
        $minHoraEntrega = null;
        if ($publicacion->fecha_hallazgo) {
            $minFechaEntrega = $publicacion->fecha_hallazgo instanceof Carbon 
                ? $publicacion->fecha_hallazgo->format('Y-m-d') 
                : Carbon::parse($publicacion->fecha_hallazgo)->format('Y-m-d');

            if (! empty($publicacion->hora_hallazgo)) {
                $minHoraEntrega = substr((string) $publicacion->hora_hallazgo, 0, 5);
            }
        }

        return view('devoluciones.create', compact(
            'publicacion',
            'maxFechaEntrega',
            'maxHoraEntrega',
            'minFechaEntrega',
            'minHoraEntrega'
        ));
    }

    /**
     * Guarda la devolución física documentada y retira temporalmente la publicación.
     * HU-11 Tarea 3: CA-01, CA-05, CA-07, CA-08, CA-10, CA-11, CA-12.
     */
    public function store(StoreDevolucionRequest $request, int|string $id): RedirectResponse
    {
        $archivoGuardadoRuta = null;

        try {
            return DB::transaction(function () use ($request, $id, &$archivoGuardadoRuta) {
                // Bloqueo pesimista para prevenir concurrencia y registros duplicados (CA-11)
                /** @var Publicacion $publicacion */
                $publicacion = Publicacion::where('id', $id)->lockForUpdate()->firstOrFail();

                // 1. Revalidar autoría y sesión activa (CA-01, CA-09)
                if ($publicacion->user_id !== Auth::id()) {
                    abort(403, 'No tienes permiso para registrar la devolución de este reporte.');
                }

                // 2. Revalidar vigencia con mensaje exacto (CA-12)
                if ($publicacion->isVencida()) {
                    return redirect()->route('reportes.mis-reportes')->with('warning',
                        'La publicación ha vencido. Si entregaste el objeto antes del vencimiento y no registraste la devolución, contacta a biblioteca e indica el folio del reporte.'
                    );
                }

                // 3. Revalidar condiciones de elegibilidad, resguardo y ausencia de devoluciones activas / incidencias (CA-01, CA-11)
                if (! $publicacion->puedeRegistrarDevolucion()) {
                    return redirect()->route('reportes.mis-reportes')->with('warning',
                        'No es posible registrar la devolución para este reporte debido a su estado o situación actual.'
                    );
                }

                // 4. Procesamiento seguro de la fotografía en disco privado local (CA-06, CA-09)
                $foto = $request->file('fotografia');
                $nombreArchivo = Str::uuid() . '.' . $foto->getClientOriginalExtension();
                $rutaDirectorio = 'devoluciones/' . $publicacion->id;
                
                // Guardar en el disco privado local
                $archivoGuardadoRuta = $foto->storeAs($rutaDirectorio, $nombreArchivo, 'local');

                // 5. Crear el registro de la devolución desacoplando fecha_captura del momento de entrega (CA-05, CA-07)
                $fechaCaptura = Carbon::now(Publicacion::TIMEZONE);

                $devolucion = Devolucion::create([
                    'publicacion_id' => $publicacion->id,
                    'user_id' => Auth::id(), // Autor responsable obtenido de la sesión (no del navegador)
                    'receptor_nombre' => $request->input('receptor_nombre'),
                    'receptor_identificador' => $request->input('receptor_identificador'),
                    'fecha_entrega' => $request->input('fecha_entrega'),
                    'hora_entrega' => $request->input('hora_entrega'),
                    'fecha_captura' => $fechaCaptura,
                    'explicacion_propiedad' => $request->input('explicacion_propiedad'),
                    'fotografia_ruta' => $archivoGuardadoRuta,
                    'fotografia_nombre_original' => $foto->getClientOriginalName(),
                    'fotografia_mime_type' => $foto->getClientMimeType(),
                    'fotografia_tamano_bytes' => $foto->getSize(),
                    'estado' => Devolucion::ESTADO_PENDIENTE,
                ]);

                // 6. Retirar la publicación del muro, búsquedas y detalle general sin marcar "Devuelto" ni alterar resguardo (CA-07, CA-08)
                $publicacion->update([
                    'disponible' => false,
                    'devolucion_en_proceso' => true,
                    'estado_devolucion' => Devolucion::ESTADO_PENDIENTE,
                    // 'estado' se mantiene en 'Aprobada' (NO se marca 'Devuelto' hasta HU-12)
                    // 'situacion_resguardo' se mantiene intacta (NO se indica resguardo biblioteca)
                ]);

                // Mensaje oficial establecido en CA-07 con enlace al seguimiento privado (CA-07, CA-13, Tarea 4)
                return redirect()->route('reportes.mis-reportes')
                    ->with('status', 'La devolución se registró y está pendiente de verificación')
                    ->with('devolucion_registrada_id', $devolucion->id)
                    ->with('publicacion_folio', $publicacion->folio);
            });
        } catch (\Throwable $e) {
            // Limpieza de archivo huérfano si la transacción falló antes de persistir (CA-10)
            if ($archivoGuardadoRuta && Storage::disk('local')->exists($archivoGuardadoRuta)) {
                Storage::disk('local')->delete($archivoGuardadoRuta);
            }

            Log::error('Error al registrar devolución física en HU-11: ' . $e->getMessage(), [
                'publicacion_id' => $id,
                'user_id' => Auth::id(),
                'exception' => $e,
            ]);

            return redirect()->route('devoluciones.create', $id)
                ->withInput()
                ->withErrors(['general' => 'Ocurrió un error inesperado al procesar la devolución. Intenta nuevamente.']);
        }
    }

    /**
     * Muestra la consulta privada del estado de la devolución, datos y fotografía.
     * HU-11 Tarea 4: CA-09, CA-13.
     * Acceso exclusivo para el autor original y el administrador/bibliotecario.
     */
    public function show(int|string $id): View
    {
        $devolucion = Devolucion::with(['publicacion.user', 'user'])->findOrFail($id);

        // CA-09: Verificación de acceso
        if (! $devolucion->puedeSerConsultadaPor(Auth::user())) {
            abort(403, 'No tienes permiso para consultar el seguimiento de esta devolución.');
        }

        return view('devoluciones.show', compact('devolucion'));
    }

    /**
     * Transmite de manera privada y segura la fotografía de evidencia de la entrega física.
     * HU-11 Tarea 4: CA-06, CA-09.
     * Acceso exclusivo para el autor original y el administrador/bibliotecario.
     * El receptor del objeto NO tiene acceso por ese simple hecho.
     */
    public function evidencia(int|string $id)
    {
        $devolucion = Devolucion::with('publicacion')->findOrFail($id);

        if (! $devolucion->puedeSerConsultadaPor(Auth::user())) {
            if (! Auth::check()) {
                return redirect()->guest(route('login'));
            }
            abort(403, 'No tienes permiso para ver esta fotografía de evidencia.');
        }

        if (! $devolucion->fotografia_ruta || ! Storage::disk('local')->exists($devolucion->fotografia_ruta)) {
            abort(404, 'Archivo de fotografía no encontrado.');
        }

        return Storage::disk('local')->response(
            $devolucion->fotografia_ruta,
            $devolucion->fotografia_nombre_original ?? ('evidencia_devolucion_' . $devolucion->id . '.jpg'),
            [
                'Content-Type' => $devolucion->fotografia_mime_type ?: 'image/jpeg',
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            ]
        );
    }
}
