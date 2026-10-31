<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DecisionModeracion;
use App\Models\Publicacion;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ModeracionReporteController extends Controller
{
    /**
     * Muestra la lista de reportes de hallazgos pendientes de revisión (HU-07 / Tarea 07.1).
     *
     * - Acceso exclusivo para Administrador / Bibliotecario.
     * - Eager loading de 'user' para prevenir consultas N+1.
     * - Ordenamiento ascendente por fecha de último envío a revisión (el más antiguo primero),
     *   con id ascendente como criterio de desempate estable.
     * - Paginación para escalabilidad y adaptación a dispositivos móviles.
     */
    public function index(Request $request): View
    {
        $pendientes = Publicacion::with('user')
            ->pendientesRevision()
            ->orderBy('fecha_envio_revision', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(15)
            ->withQueryString();

        return view('admin.moderacion.index', compact('pendientes'));
    }

    /**
     * Muestra el detalle completo de un reporte para su moderación (HU-07 / Tarea 07.2).
     */
    public function show(int $id): View
    {
        $reporte = Publicacion::with(['user', 'fotografias', 'decisiones.user'])->findOrFail($id);

        $fotografiaPrincipal = $reporte->fotografias->first();
        $fotografiasAdicionales = $reporte->fotografias->slice(1);
        $isDisponibleParaRevision = $reporte->isPendienteRevision();

        return view('admin.moderacion.show', compact(
            'reporte',
            'fotografiaPrincipal',
            'fotografiasAdicionales',
            'isDisponibleParaRevision'
        ));
    }

    /**
     * Solicita corrección del reporte al autor registrando el motivo e historial (HU-07 / Tarea 07.3).
     *
     * - Aplica control estricto de concurrencia y versión.
     * - Valida que el motivo no sea vacío ni compuesto únicamente de espacios.
     * - Ejecuta la operación bajo transacción de base de datos para garantizar atomicidad.
     * - Cambia el estado a «Corrección solicitada», preserva folio y contenido.
     * - Registra al administrador responsable, fecha, hora y motivo en el historial de decisiones.
     */
    public function solicitarCorreccion(Request $request, int $id): RedirectResponse
    {
        // 1. Validación inicial de formato del motivo
        $rawMotivo = (string) $request->input('motivo');
        if (trim($rawMotivo) === '') {
            return redirect()->route('admin.moderacion.show', $id)
                ->withInput()
                ->withErrors(['motivo' => 'El motivo de corrección es obligatorio y no puede estar vacío ni contener solo espacios en blanco.']);
        }

        $validator = Validator::make($request->all(), [
            'motivo' => ['required', 'string', 'min:3', 'max:1000'],
            'lock_version' => ['nullable', 'integer'],
        ], [
            'motivo.required' => 'El motivo de corrección es obligatorio.',
            'motivo.min' => 'El motivo de corrección debe contener al menos 3 caracteres.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('admin.moderacion.show', $id)
                ->withInput()
                ->withErrors($validator);
        }

        $motivo = trim($rawMotivo);
        $lockVersion = $request->input('lock_version');
        $updatedAtToken = $request->input('updated_at_token');

        // 2. Transacción y bloqueo optimista / pesimista para atomicidad
        return DB::transaction(function () use ($id, $motivo, $lockVersion, $updatedAtToken) {
            $reporte = Publicacion::lockForUpdate()->findOrFail($id);

            // Comprobación de estado pendiente y concurrencia
            if (! $reporte->isPendienteRevision()) {
                return redirect()->route('admin.moderacion.show', $reporte->id)
                    ->withErrors(['concurrencia' => 'El reporte cambió. Revisa su información actual antes de continuar.'])
                    ->with('error', 'El reporte cambió. Revisa su información actual antes de continuar.');
            }

            // Comprobación estricta de versión
            $versionActual = (int) ($reporte->version ?? 1);
            if ($lockVersion !== null && (int) $lockVersion !== $versionActual) {
                return redirect()->route('admin.moderacion.show', $reporte->id)
                    ->withErrors(['concurrencia' => 'El reporte cambió. Revisa su información actual antes de continuar.'])
                    ->with('error', 'El reporte cambió. Revisa su información actual antes de continuar.');
            }

            // Comprobación de token de marca temporal si fue proporcionado
            if ($updatedAtToken !== null && (string) $reporte->updated_at?->timestamp !== (string) $updatedAtToken) {
                return redirect()->route('admin.moderacion.show', $reporte->id)
                    ->withErrors(['concurrencia' => 'El reporte cambió. Revisa su información actual antes de continuar.'])
                    ->with('error', 'El reporte cambió. Revisa su información actual antes de continuar.');
            }

            // 3. Actualizar reporte conservando folio, fotos y contenido
            $reporte->update([
                'estado' => Publicacion::ESTADO_CORRECCION_SOLICITADA,
                'observaciones' => $motivo,
                'disponible' => false,
                'version' => $versionActual + 1,
            ]);

            // 4. Registrar en el historial de decisiones de moderación
            DecisionModeracion::create([
                'publicacion_id' => $reporte->id,
                'user_id' => Auth::id(),
                'tipo_decision' => DecisionModeracion::TIPO_CORRECCION_SOLICITADA,
                'motivo' => $motivo,
                'fecha_decision' => Carbon::now(Publicacion::TIMEZONE),
            ]);

            return redirect()->route('admin.moderacion.index')
                ->with('status', "Se solicitó la corrección del reporte {$reporte->folio} exitosamente.")
                ->with('reporte_corregido_id', $reporte->id);
        });
    }

    /**
     * Aprueba la publicación de un reporte controlando su vigencia de 14 días naturales (HU-07 / Tarea 07.4).
     *
     * - Comprueba autorización, existencia y estado pendiente de revisión.
     * - Control estricto de concurrencia y versión.
     * - Si es primera aprobación: registra fecha_primera_aprobacion y calcula fecha_vencimiento (+14 días naturales, America/Merida).
     * - Si es reaprobación (tras corrección): conserva fecha_primera_aprobacion y fecha_vencimiento original (no reinicia los 14 días).
     * - Comprueba restricciones de disponibilidad (vencimiento, devolución en proceso, incidencias abiertas).
     * - No altera la custodia física, lugar del hallazgo ni autor/resguardo.
     * - Registra la decisión en el historial de moderación de forma atómica.
     */
    public function aprobar(Request $request, int $id): RedirectResponse
    {
        $lockVersion = $request->input('lock_version');
        $updatedAtToken = $request->input('updated_at_token');

        return DB::transaction(function () use ($id, $lockVersion, $updatedAtToken, $request) {
            $reporte = Publicacion::lockForUpdate()->findOrFail($id);

            // Comprobación de estado pendiente y concurrencia
            if (! $reporte->isPendienteRevision()) {
                return redirect()->route('admin.moderacion.show', $reporte->id)
                    ->withErrors(['concurrencia' => 'El reporte cambió. Revisa su información actual antes de continuar.'])
                    ->with('error', 'El reporte cambió. Revisa su información actual antes de continuar.');
            }

            // Comprobación estricta de versión
            $versionActual = (int) ($reporte->version ?? 1);
            if ($lockVersion !== null && (int) $lockVersion !== $versionActual) {
                return redirect()->route('admin.moderacion.show', $reporte->id)
                    ->withErrors(['concurrencia' => 'El reporte cambió. Revisa su información actual antes de continuar.'])
                    ->with('error', 'El reporte cambió. Revisa su información actual antes de continuar.');
            }

            // Comprobación de token de marca temporal si fue proporcionado
            if ($updatedAtToken !== null && (string) $reporte->updated_at?->timestamp !== (string) $updatedAtToken) {
                return redirect()->route('admin.moderacion.show', $reporte->id)
                    ->withErrors(['concurrencia' => 'El reporte cambió. Revisa su información actual antes de continuar.'])
                    ->with('error', 'El reporte cambió. Revisa su información actual antes de continuar.');
            }

            $ahora = Carbon::now(Publicacion::TIMEZONE);
            $esPrimeraAprobacion = ($reporte->fecha_primera_aprobacion === null);

            if ($esPrimeraAprobacion) {
                $reporte->fecha_primera_aprobacion = $ahora;
                $reporte->fecha_vencimiento = $ahora->copy()->addDays(Publicacion::DIAS_VIGENCIA);
            }

            $reporte->fecha_aprobacion = $ahora;
            $reporte->estado = Publicacion::ESTADO_APROBADA;
            $reporte->observaciones = null; // Observaciones resueltas
            $reporte->version = $versionActual + 1;

            // Comprobar restricciones de disponibilidad para publicación en muro
            if (! $reporte->hasRestriccionPublicacion()) {
                $reporte->disponible = true;
            } else {
                $reporte->disponible = false;
            }

            $reporte->save();

            // Registrar en el historial de decisiones de moderación
            DecisionModeracion::create([
                'publicacion_id' => $reporte->id,
                'user_id' => Auth::id(),
                'tipo_decision' => DecisionModeracion::TIPO_APROBADA,
                'motivo' => $request->filled('motivo') ? trim($request->input('motivo')) : 'Publicación autorizada y vigente para el muro institucional.',
                'fecha_decision' => $ahora,
            ]);

            $mensaje = $request->routeIs('publicaciones.aprobar')
                ? 'Publicación aprobada correctamente'
                : "El reporte {$reporte->folio} fue aprobado exitosamente.";

            return redirect()->route('admin.moderacion.index')
                ->with('status', $mensaje)
                ->with('reporte_aprobado_id', $reporte->id);
        });
    }

    /**
     * Cancela administrativamente una publicación documentando el motivo e historial (HU-07 / Tarea 07.5).
     *
     * - Restringido exclusivamente al Administrador / Bibliotecario.
     * - Rechaza motivos vacíos o formados únicamente por espacios.
     * - Protege procesos relacionados (devoluciones en proceso e incidencias abiertas).
     * - Aplica control estricto de concurrencia y versión.
     * - Cambia el estado a «Cancelado», retira del muro (disponible = false), conserva folio, contenido, fotos e historial.
     * - Permite documentar referencia de folio duplicado cuando aplique.
     * - Ejecuta la operación bajo transacción de base de datos para garantizar atomicidad.
     */
    public function cancelar(Request $request, int $id): RedirectResponse
    {
        // 1. Validación de motivo
        $rawMotivo = (string) $request->input('motivo');
        if (trim($rawMotivo) === '') {
            return redirect()->route('admin.moderacion.show', $id)
                ->withInput()
                ->withErrors(['motivo' => 'El motivo de cancelación es obligatorio y no puede estar vacío ni contener solo espacios en blanco.']);
        }

        $validator = Validator::make($request->all(), [
            'motivo' => ['required', 'string', 'min:3', 'max:1000'],
            'folio_referencia_duplicado' => ['nullable', 'string', 'max:50'],
            'lock_version' => ['nullable', 'integer'],
        ], [
            'motivo.required' => 'El motivo de cancelación es obligatorio.',
            'motivo.min' => 'El motivo de cancelación debe contener al menos 3 caracteres.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('admin.moderacion.show', $id)
                ->withInput()
                ->withErrors($validator);
        }

        $motivo = trim($rawMotivo);
        $folioDuplicado = trim((string) $request->input('folio_referencia_duplicado'));
        $lockVersion = $request->input('lock_version');
        $updatedAtToken = $request->input('updated_at_token');

        return DB::transaction(function () use ($id, $motivo, $folioDuplicado, $lockVersion, $updatedAtToken) {
            $reporte = Publicacion::lockForUpdate()->findOrFail($id);

            // Comprobación de estados incompatibles
            if ($reporte->isCancelado()) {
                return redirect()->route('admin.moderacion.show', $reporte->id)
                    ->withErrors(['cancelacion' => 'El reporte ya se encuentra cancelado.'])
                    ->with('error', 'El reporte ya se encuentra cancelado.');
            }

            if ($reporte->estado === 'Devuelto') {
                return redirect()->route('admin.moderacion.show', $reporte->id)
                    ->withErrors(['cancelacion' => 'No se puede cancelar un reporte cuyo objeto ya fue devuelto.'])
                    ->with('error', 'No se puede cancelar un reporte cuyo objeto ya fue devuelto.');
            }

            // Protección de procesos relacionados (devoluciones en proceso e incidencias)
            if ($reporte->devolucion_en_proceso) {
                return redirect()->route('admin.moderacion.show', $reporte->id)
                    ->withErrors(['cancelacion' => 'No se puede cancelar la publicación porque tiene una devolución en proceso activa. Debe atenderse primero la devolución.'])
                    ->with('error', 'No se puede cancelar la publicación porque tiene una devolución en proceso activa. Debe atenderse primero la devolución.');
            }

            if ($reporte->incidencia_abierta) {
                return redirect()->route('admin.moderacion.show', $reporte->id)
                    ->withErrors(['cancelacion' => 'No se puede cancelar la publicación porque tiene una incidencia abierta en investigación. Debe resolverse la incidencia primero.'])
                    ->with('error', 'No se puede cancelar la publicación porque tiene una incidencia abierta en investigación. Debe resolverse la incidencia primero.');
            }

            // Comprobación estricta de concurrencia y versión
            $versionActual = (int) ($reporte->version ?? 1);
            if ($lockVersion !== null && (int) $lockVersion !== $versionActual) {
                return redirect()->route('admin.moderacion.show', $reporte->id)
                    ->withErrors(['concurrencia' => 'El reporte cambió. Revisa su información actual antes de continuar.'])
                    ->with('error', 'El reporte cambió. Revisa su información actual antes de continuar.');
            }

            if ($updatedAtToken !== null && (string) $reporte->updated_at?->timestamp !== (string) $updatedAtToken) {
                return redirect()->route('admin.moderacion.show', $reporte->id)
                    ->withErrors(['concurrencia' => 'El reporte cambió. Revisa su información actual antes de continuar.'])
                    ->with('error', 'El reporte cambió. Revisa su información actual antes de continuar.');
            }

            $ahora = Carbon::now(Publicacion::TIMEZONE);

            // Preparar observaciones y motivo incluyendo referencia a duplicado si aplica
            $motivoCompleto = $motivo;
            if ($folioDuplicado !== '') {
                $motivoCompleto .= " [Referencia a reporte duplicado conservado: {$folioDuplicado}]";
            }

            // Actualizar estado a Cancelado retirando del muro y conservando toda la evidencia
            $reporte->update([
                'estado' => Publicacion::ESTADO_CANCELADO,
                'disponible' => false,
                'observaciones' => $motivoCompleto,
                'folio_referencia_duplicado' => $folioDuplicado !== '' ? $folioDuplicado : null,
                'version' => $versionActual + 1,
            ]);

            // Registrar decisión en el historial de moderación
            DecisionModeracion::create([
                'publicacion_id' => $reporte->id,
                'user_id' => Auth::id(),
                'tipo_decision' => DecisionModeracion::TIPO_CANCELADA,
                'motivo' => $motivoCompleto,
                'folio_referencia_duplicado' => $folioDuplicado !== '' ? $folioDuplicado : null,
                'fecha_decision' => $ahora,
            ]);

            return redirect()->route('admin.moderacion.show', $reporte->id)
                ->with('status', "La publicación {$reporte->folio} fue cancelada exitosamente.");
        });
    }
}
