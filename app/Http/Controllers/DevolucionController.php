<?php

namespace App\Http\Controllers;

use App\Events\DevolucionReenviadaRevision;
use App\Http\Requests\StoreDevolucionRequest;
use App\Http\Requests\UpdateDevolucionRequest;
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

                // 5. Crear el registro de la devolución sincronizado con el momento de entrega y captura
                $ahoraMerida = Carbon::now(Publicacion::TIMEZONE);
                $fechaCaptura = $ahoraMerida;

                // Si no se envió fecha u hora explícita, o si se dejó automática, tomar el momento actual exacto
                $fechaEntrega = $request->input('fecha_entrega') ?: $ahoraMerida->format('Y-m-d');
                $horaEntrega = $request->input('hora_entrega') ?: $ahoraMerida->format('H:i:s');

                // Cuando la entrega se registra para la fecha de hoy (o por omisión), sincronizar
                // estrictamente fecha_entrega y hora_entrega con el momento exacto del envío (fecha_captura).
                if ($fechaEntrega === $ahoraMerida->format('Y-m-d')) {
                    $horaEntrega = $ahoraMerida->format('H:i:s');
                }

                $devolucion = Devolucion::create([
                    'publicacion_id' => $publicacion->id,
                    'user_id' => Auth::id(), // Autor responsable obtenido de la sesión (no del navegador)
                    'receptor_nombre' => $request->input('receptor_nombre'),
                    'receptor_identificador' => $request->input('receptor_identificador'),
                    'fecha_entrega' => $fechaEntrega,
                    'hora_entrega' => $horaEntrega,
                    'fecha_captura' => $fechaCaptura,
                    'fecha_envio_revision' => $fechaCaptura,
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
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
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
        $devolucion = Devolucion::with(['publicacion.user', 'publicacion.decisiones.user', 'user', 'admin'])->findOrFail($id);

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

    /**
     * Muestra el formulario para corregir y reenviar la misma devolución (HU-12 / Tarea 3).
     * Acceso exclusivo para el autor original y únicamente cuando el estado sea 'Requiere corrección'.
     */
    public function edit(int|string $id): View|RedirectResponse
    {
        $devolucion = Devolucion::with(['publicacion.user', 'publicacion.fotografias', 'user'])->findOrFail($id);
        $publicacion = $devolucion->publicacion;

        // 1. Autorización: disponible únicamente para el autor original (CA-01, HU-12 Tarea 3)
        $autorId = $publicacion ? $publicacion->user_id : $devolucion->user_id;
        if (Auth::id() !== $autorId) {
            abort(403, 'No tienes permiso para modificar esta devolución.');
        }

        // 2. Comprobar que la devolución esté en estado 'Requiere corrección'
        if ($devolucion->isAprobada()) {
            return redirect()->route('devoluciones.show', $devolucion->id)
                ->with('error', 'Esta devolución ya fue aprobada y no puede modificarse.');
        }

        if (! $devolucion->isRequiereCorreccion()) {
            return redirect()->route('devoluciones.show', $devolucion->id)
                ->with('warning', 'Esta devolución no se encuentra en estado de corrección en este momento.');
        }

        // Parámetros de fecha/hora de referencia para el formulario
        $ahoraMerida = Carbon::now(Publicacion::TIMEZONE);
        $maxFechaEntrega = $ahoraMerida->format('Y-m-d');
        $maxHoraEntrega = $ahoraMerida->format('H:i');

        $minFechaEntrega = null;
        $minHoraEntrega = null;
        if ($publicacion && $publicacion->fecha_hallazgo) {
            $minFechaEntrega = $publicacion->fecha_hallazgo instanceof Carbon
                ? $publicacion->fecha_hallazgo->format('Y-m-d')
                : Carbon::parse($publicacion->fecha_hallazgo)->format('Y-m-d');

            if (! empty($publicacion->hora_hallazgo)) {
                $minHoraEntrega = substr((string) $publicacion->hora_hallazgo, 0, 5);
            }
        }

        return view('devoluciones.edit', compact(
            'devolucion',
            'publicacion',
            'maxFechaEntrega',
            'maxHoraEntrega',
            'minFechaEntrega',
            'minHoraEntrega'
        ));
    }

    /**
     * Actualiza la misma devolución atendiendo observaciones y la reenvía a la cola de verificación (HU-12 / Tarea 3).
     * Conserva la identidad original del registro, evento de entrega e historial administrativo.
     */
    public function update(UpdateDevolucionRequest $request, int|string $id): RedirectResponse
    {
        // 1. Verificación previa de autorización y estado
        $devolucionPrevia = Devolucion::with('publicacion')->findOrFail($id);
        $publicacionPrevia = $devolucionPrevia->publicacion;
        $autorId = $publicacionPrevia ? $publicacionPrevia->user_id : $devolucionPrevia->user_id;

        if (Auth::id() !== $autorId) {
            abort(403, 'No tienes permiso para modificar esta devolución.');
        }

        if (! $devolucionPrevia->isRequiereCorreccion()) {
            return redirect()->route('devoluciones.show', $devolucionPrevia->id)
                ->with('error', 'Esta devolución no se encuentra en estado de corrección o ya fue resuelta.');
        }

        $archivoNuevoGuardadoRuta = null;
        $archivoViejoAEliminar = null;

        try {
            DB::transaction(function () use ($request, $id, &$archivoNuevoGuardadoRuta, &$archivoViejoAEliminar) {
                /** @var Devolucion $devolucion */
                $devolucion = Devolucion::where('id', $id)->lockForUpdate()->firstOrFail();
                $publicacion = $devolucion->publicacion;

                // Revalidar autorización y estado con bloqueo pesimista
                $autorId = $publicacion ? $publicacion->user_id : $devolucion->user_id;
                if (Auth::id() !== $autorId) {
                    abort(403, 'No tienes permiso para modificar esta devolución.');
                }

                if (! $devolucion->isRequiereCorreccion()) {
                    return redirect()->route('devoluciones.show', $devolucion->id)
                        ->with('error', 'Esta devolución no se encuentra en estado de corrección o ya fue resuelta.');
                }

                // 3. Procesar fotografía si se envió una nueva
                if ($request->hasFile('fotografia')) {
                    $nuevaFoto = $request->file('fotografia');
                    $nombreArchivo = Str::uuid() . '.' . $nuevaFoto->getClientOriginalExtension();
                    $rutaDirectorio = 'devoluciones/' . ($publicacion ? $publicacion->id : $devolucion->publicacion_id);

                    // Almacenar en disco privado local
                    $archivoNuevoGuardadoRuta = $nuevaFoto->storeAs($rutaDirectorio, $nombreArchivo, 'local');

                    // Marcar archivo anterior para retirar solo tras commit exitoso
                    if ($devolucion->fotografia_ruta && $devolucion->fotografia_ruta !== $archivoNuevoGuardadoRuta) {
                        $archivoViejoAEliminar = $devolucion->fotografia_ruta;
                    }

                    $devolucion->fotografia_ruta = $archivoNuevoGuardadoRuta;
                    $devolucion->fotografia_nombre_original = $nuevaFoto->getClientOriginalName();
                    $devolucion->fotografia_mime_type = $nuevaFoto->getClientMimeType();
                    $devolucion->fotografia_tamano_bytes = $nuevaFoto->getSize();
                }

                // 4. Actualizar campos permitidos sobre la MISMA devolución (sin duplicar ni crear otra)
                $ahoraMerida = Carbon::now(Publicacion::TIMEZONE);

                $devolucion->receptor_nombre = $request->input('receptor_nombre');
                $devolucion->receptor_identificador = $request->input('receptor_identificador');
                $devolucion->fecha_entrega = $request->input('fecha_entrega');
                $devolucion->hora_entrega = $request->input('hora_entrega');
                $devolucion->explicacion_propiedad = $request->input('explicacion_propiedad');
                $devolucion->estado = Devolucion::ESTADO_PENDIENTE;
                $devolucion->fecha_envio_revision = $ahoraMerida;
                $devolucion->motivo_correccion = null; // Deja de considerarse vigente el motivo anterior
                $devolucion->admin_id = null;
                $devolucion->fecha_resolucion = null;
                $devolucion->save();

                // 5. Actualizar la publicación manteniéndola fuera del muro
                if ($publicacion) {
                    $publicacion->estado_devolucion = Devolucion::ESTADO_PENDIENTE;
                    $publicacion->disponible = false;
                    $publicacion->devolucion_en_proceso = true;
                    $publicacion->save();
                }

                // 6. Despachar evento de negocio desacoplado para integración con HU-14
                event(new DevolucionReenviadaRevision($devolucion, Auth::user()));
            });

            // 7. Eliminación física segura del archivo anterior únicamente tras commit exitoso de la transacción
            if ($archivoViejoAEliminar && Storage::disk('local')->exists($archivoViejoAEliminar)) {
                Storage::disk('local')->delete($archivoViejoAEliminar);
            }

            return redirect()->route('devoluciones.show', $id)
                ->with('status', 'La devolución se corrigió y fue reenviada a verificación administrativa.');

        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            // En caso de fallo en BD, revertir el archivo nuevo para no dejar huérfanos
            if ($archivoNuevoGuardadoRuta && Storage::disk('local')->exists($archivoNuevoGuardadoRuta)) {
                Storage::disk('local')->delete($archivoNuevoGuardadoRuta);
            }

            Log::error('Error al actualizar y reenviar devolución en HU-12: ' . $e->getMessage(), [
                'devolucion_id' => $id,
                'user_id' => Auth::id(),
                'exception' => $e,
            ]);

            return redirect()->route('devoluciones.edit', $id)
                ->withInput()
                ->withErrors(['general' => 'Ocurrió un error inesperado al procesar la corrección. Intenta nuevamente.']);
        }
    }
}
