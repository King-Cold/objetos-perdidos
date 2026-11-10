<?php

namespace App\Http\Controllers\Admin;

use App\Events\DevolucionAprobada;
use App\Events\DevolucionCorreccionSolicitada;
use App\Http\Controllers\Controller;
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
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class VerificacionDevolucionController extends Controller
{
    /**
     * Muestra la cola administrativa de devoluciones pendientes de verificación (HU-12 / Tarea 1).
     *
     * - Acceso exclusivo para Administrador / Bibliotecario.
     * - Eager loading de 'publicacion.user', 'publicacion.fotografias' y 'user' para prevenir consultas N+1.
     * - Ordenamiento ascendente por fecha de envío a revisión / reenvío (el más antiguo primero),
     *   con id ascendente como criterio de desempate estable.
     * - Filtra únicamente registros en estado 'Pendiente de verificación'.
     * - Excluye devoluciones aprobadas, en corrección y cierres directos desde biblioteca.
     * - Paginación para escalabilidad y adaptación a dispositivos móviles.
     */
    public function index(Request $request): View
    {
        $filtroEstado = $request->input('filtro', 'pendientes');

        $query = Devolucion::with(['publicacion.user', 'publicacion.fotografias', 'user']);

        if ($filtroEstado === 'aprobadas') {
            $query->whereIn('devoluciones.estado', [Devolucion::ESTADO_VERIFICADA, Devolucion::ESTADO_APROBADA]);
        } elseif ($filtroEstado === 'todas') {
            // Mostrar tanto pendientes como aprobadas/en corrección
        } else {
            $query->pendientesVerificacion();
            $filtroEstado = 'pendientes';
        }

        $devoluciones = $query
            ->orderBy(DB::raw('COALESCE(fecha_envio_revision, fecha_captura)'), 'asc')
            ->orderBy('id', 'asc')
            ->paginate(15)
            ->withQueryString();

        $contadores = [
            'todas' => Devolucion::count(),
            'pendientes' => Devolucion::pendientesVerificacion()->count(),
            'aprobadas' => Devolucion::whereIn('devoluciones.estado', [
                Devolucion::ESTADO_VERIFICADA,
                Devolucion::ESTADO_APROBADA,
            ])->count(),
        ];

        return view('admin.devoluciones.index', compact('devoluciones', 'filtroEstado', 'contadores'));
    }

    /**
     * Muestra el detalle privado completo de una devolución para revisión administrativa (HU-12 / Tareas 1 y 2).
     *
     * - Muestra información completa del hallazgo original (folio, nombre, descripción, fecha/hora hallazgo, fotos, autor).
     * - Muestra información detallada de la devolución (receptor, identificador, explicación, evidencia protegida).
     * - Diferencia explícitamente «Fecha de entrega física» y «Fecha de registro».
     * - Incorpora modales y acciones para «Aprobar devolución» y «Solicitar corrección».
     */
    public function show(int|string $id): View
    {
        $devolucion = Devolucion::with(['publicacion.user', 'publicacion.fotografias', 'publicacion.decisiones.user', 'user', 'admin'])->findOrFail($id);

        $publicacion = $devolucion->publicacion;
        $fotografiaPrincipal = $publicacion ? $publicacion->fotografias->first() : null;
        $fotografiasAdicionales = $publicacion ? $publicacion->fotografias->slice(1) : collect();

        return view('admin.devoluciones.show', compact(
            'devolucion',
            'publicacion',
            'fotografiaPrincipal',
            'fotografiasAdicionales'
        ));
    }

    /**
     * Aprueba administrativamente la devolución física documentada (HU-12 / Tarea 2).
     *
     * Reglas y validaciones:
     * 1. Comprueba concurrencia y previene conflictos (debe continuar 'Pendiente de verificación').
     * 2. Comprueba que todos los datos obligatorios estén completos.
     * 3. Comprueba coherencia de fechas respecto al hallazgo y no futuras.
     * 4. Comprueba existencia de explicación de comprobación de propiedad.
     * 5. Comprueba existencia física y legibilidad del archivo de evidencia fotográfica.
     * 6. Comprueba ausencia de incidencias activas en el reporte.
     * 7. Actualiza la devolución a 'Verificada' y el objeto a 'Devuelto' (fuera de muros y búsquedas).
     * 8. Registra administrador responsable, fecha y evento en el historial de decisiones.
     * 9. Despacha evento de negocio desacoplado para integración futura con HU-14.
     */
    public function aprobar(Request $request, int|string $id): RedirectResponse
    {
        try {
            return DB::transaction(function () use ($request, $id) {
                /** @var Devolucion $devolucion */
                $devolucion = Devolucion::where('id', $id)->lockForUpdate()->firstOrFail();
                $publicacion = $devolucion->publicacion;

                // 1. Prevención de conflictos: Comprobar que la devolución siga pendiente
                if ($devolucion->estado !== Devolucion::ESTADO_PENDIENTE) {
                    return redirect()->route('admin.devoluciones.show', $devolucion->id)
                        ->with('error', 'El estado de la devolución cambió. Consulta su información actual antes de continuar.');
                }

                // 2. Verificar datos obligatorios completos
                if (empty($devolucion->receptor_nombre) ||
                    empty($devolucion->receptor_identificador) ||
                    empty($devolucion->fecha_entrega) ||
                    empty($devolucion->hora_entrega) ||
                    empty($devolucion->fotografia_ruta) ||
                    trim((string) $devolucion->receptor_nombre) === '' ||
                    trim((string) $devolucion->receptor_identificador) === '') {
                    return redirect()->route('admin.devoluciones.show', $devolucion->id)
                        ->with('error', 'No es posible aprobar la devolución porque los datos obligatorios están incompletos.');
                }

                // 3. Verificar coherencia de fechas
                $ahoraMerida = Carbon::now(Publicacion::TIMEZONE);
                $fechaEntrega = Carbon::parse($devolucion->fecha_entrega);

                if ($fechaEntrega->isFuture() || ($fechaEntrega->isToday() && ! empty($devolucion->hora_entrega) && Carbon::parse($devolucion->hora_entrega)->format('H:i') > $ahoraMerida->format('H:i'))) {
                    return redirect()->route('admin.devoluciones.show', $devolucion->id)
                        ->with('error', 'Las fechas de entrega no son coherentes con la fecha actual o son futuras.');
                }

                if ($publicacion && $publicacion->fecha_hallazgo) {
                    $fechaHallazgo = Carbon::parse($publicacion->fecha_hallazgo);
                    if ($fechaEntrega->lt($fechaHallazgo)) {
                        return redirect()->route('admin.devoluciones.show', $devolucion->id)
                            ->with('error', 'La fecha de entrega física no puede ser anterior a la fecha de hallazgo del objeto.');
                    }

                    if ($fechaEntrega->equalTo($fechaHallazgo) && ! empty($publicacion->hora_hallazgo) && ! empty($devolucion->hora_entrega)) {
                        $horaHallazgoStr = substr((string) $publicacion->hora_hallazgo, 0, 5);
                        $horaEntregaStr = substr((string) $devolucion->hora_entrega, 0, 5);
                        if ($horaEntregaStr < $horaHallazgoStr) {
                            return redirect()->route('admin.devoluciones.show', $devolucion->id)
                                ->with('error', 'La hora de entrega física no puede ser anterior a la hora de hallazgo del objeto.');
                        }
                    }
                }

                // 4. Verificar explicación de propiedad válida
                if (trim((string) $devolucion->explicacion_propiedad) === '') {
                    return redirect()->route('admin.devoluciones.show', $devolucion->id)
                        ->with('error', 'Debe existir una explicación válida de la comprobación de propiedad.');
                }

                // 5. Verificar existencia y accesibilidad de la fotografía de evidencia
                if (! $devolucion->fotografia_ruta || ! Storage::disk('local')->exists($devolucion->fotografia_ruta)) {
                    return redirect()->route('admin.devoluciones.show', $devolucion->id)
                        ->with('error', 'La fotografía de evidencia no existe o no se encuentra accesible en el almacenamiento.');
                }

                // 6. Verificar que no exista incidencia activa que impida aprobar
                if ($publicacion && ($publicacion->tiene_incidencia || $publicacion->incidencia_abierta || $publicacion->estado_devolucion === 'En incidencia')) {
                    return redirect()->route('admin.devoluciones.show', $devolucion->id)
                        ->with('error', 'No es posible aprobar la devolución debido a una incidencia activa en el reporte.');
                }

                // 7. Aplicar actualización a la devolución
                $devolucion->estado = Devolucion::ESTADO_VERIFICADA;
                $devolucion->admin_id = Auth::id();
                $devolucion->fecha_resolucion = $ahoraMerida;
                $devolucion->save();

                // 8. Aplicar actualización a la publicación
                if ($publicacion) {
                    $publicacion->estado = Publicacion::ESTADO_DEVUELTO;
                    $publicacion->disponible = false;
                    $publicacion->devolucion_en_proceso = false;
                    $publicacion->estado_devolucion = Devolucion::ESTADO_VERIFICADA;
                    $publicacion->save();

                    // 9. Registrar evento en el historial de decisiones
                    $esExtemporanea = method_exists($devolucion, 'isRegistroExtemporaneo')
                        ? $devolucion->isRegistroExtemporaneo()
                        : (bool) ($devolucion->es_registro_extemporaneo ?? false);

                    $motivoDecision = $esExtemporanea
                        ? 'Devolución previa registrada tardíamente'
                        : 'Devolución física aprobada tras verificación y validación de evidencia.';

                    DecisionModeracion::create([
                        'publicacion_id' => $publicacion->id,
                        'user_id' => Auth::id(),
                        'tipo_decision' => DecisionModeracion::TIPO_DEVOLUCION_APROBADA,
                        'motivo' => $motivoDecision,
                        'fecha_decision' => $ahoraMerida,
                    ]);
                }

                // 10. Despachar evento desacoplado para integración futura con HU-14
                event(new DevolucionAprobada($devolucion, Auth::user()));

                return redirect()->route('admin.devoluciones.show', $devolucion->id)
                    ->with('status', 'La devolución fue aprobada correctamente y el objeto se marcó como devuelto.');
            });
        } catch (\Throwable $e) {
            Log::error('Error al aprobar devolución en HU-12: ' . $e->getMessage(), [
                'devolucion_id' => $id,
                'admin_id' => Auth::id(),
                'exception' => $e,
            ]);

            return redirect()->route('admin.devoluciones.show', $id)
                ->with('error', 'Ocurrió un error inesperado al procesar la aprobación. Intenta nuevamente.');
        }
    }

    /**
     * Solicita corrección de la evidencia o datos de la devolución física (HU-12 / Tarea 2).
     *
     * Reglas y validaciones:
     * 1. Valida obligatoriedad y formato del motivo (no vacío, no solo espacios).
     * 2. Comprueba concurrencia y previene conflictos (debe continuar 'Pendiente de verificación').
     * 3. Cambia la devolución a 'Requiere corrección'.
     * 4. Registra motivo, fecha y administrador responsable sin borrar datos ni fotos previas.
     * 5. Mantiene el objeto fuera del muro institucional y lo retira de la cola de pendientes.
     * 6. Registra la decisión en el historial y despacha evento para HU-14.
     */
    public function solicitarCorreccion(Request $request, int|string $id): RedirectResponse
    {
        // 1. Validación previa de contenido del motivo
        $rawMotivo = (string) $request->input('motivo');
        if (trim($rawMotivo) === '') {
            return redirect()->route('admin.devoluciones.show', $id)
                ->withInput()
                ->withErrors(['motivo' => 'El motivo de corrección es obligatorio y no puede estar vacío ni contener solo espacios en blanco.']);
        }

        $validator = Validator::make($request->all(), [
            'motivo' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'motivo.required' => 'El motivo de corrección es obligatorio y no puede estar vacío ni contener solo espacios en blanco.',
            'motivo.min' => 'El motivo de corrección debe tener al menos 3 caracteres.',
            'motivo.max' => 'El motivo de corrección no puede exceder los 1000 caracteres.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('admin.devoluciones.show', $id)
                ->withInput()
                ->withErrors($validator);
        }

        try {
            return DB::transaction(function () use ($rawMotivo, $id) {
                /** @var Devolucion $devolucion */
                $devolucion = Devolucion::where('id', $id)->lockForUpdate()->firstOrFail();
                $publicacion = $devolucion->publicacion;

                // 2. Prevención de conflictos: Comprobar que la devolución siga pendiente
                if ($devolucion->estado !== Devolucion::ESTADO_PENDIENTE) {
                    return redirect()->route('admin.devoluciones.show', $devolucion->id)
                        ->with('error', 'El estado de la devolución cambió. Consulta su información actual antes de continuar.');
                }

                $ahoraMerida = Carbon::now(Publicacion::TIMEZONE);
                $motivoLimpio = trim($rawMotivo);

                // 3. Actualizar la devolución a 'Requiere corrección' conservando datos y fotografía
                $devolucion->estado = Devolucion::ESTADO_REQUIERE_CORRECCION;
                $devolucion->motivo_correccion = $motivoLimpio;
                $devolucion->admin_id = Auth::id();
                $devolucion->fecha_resolucion = $ahoraMerida;
                $devolucion->save();

                // 4. Actualizar la publicación manteniéndola fuera del muro
                if ($publicacion) {
                    $publicacion->estado_devolucion = Devolucion::ESTADO_REQUIERE_CORRECCION;
                    $publicacion->disponible = false;
                    $publicacion->devolucion_en_proceso = true;
                    $publicacion->save();

                    // 5. Registrar decisión en el historial de decisiones
                    DecisionModeracion::create([
                        'publicacion_id' => $publicacion->id,
                        'user_id' => Auth::id(),
                        'tipo_decision' => DecisionModeracion::TIPO_DEVOLUCION_CORRECCION,
                        'motivo' => $motivoLimpio,
                        'fecha_decision' => $ahoraMerida,
                    ]);
                }

                // 6. Despachar evento desacoplado para integración futura con HU-14
                event(new DevolucionCorreccionSolicitada($devolucion, Auth::user(), $motivoLimpio));

                return redirect()->route('admin.devoluciones.show', $devolucion->id)
                    ->with('status', 'Solicitud de corrección registrada correctamente.');
            });
        } catch (\Throwable $e) {
            Log::error('Error al solicitar corrección de devolución en HU-12: ' . $e->getMessage(), [
                'devolucion_id' => $id,
                'admin_id' => Auth::id(),
                'exception' => $e,
            ]);

            return redirect()->route('admin.devoluciones.show', $id)
                ->with('error', 'Ocurrió un error inesperado al procesar la solicitud de corrección. Intenta nuevamente.');
        }
    }
}
