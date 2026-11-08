<?php

namespace App\Http\Controllers\Admin;

use App\Events\DevolucionReenviadaRevision;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDevolucionExtemporaneaRequest;
use App\Http\Requests\UpdateDevolucionExtemporaneaRequest;
use App\Models\Devolucion;
use App\Models\Publicacion;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DevolucionExtemporaneaController extends Controller
{
    private function asegurarColumnasRegistroTardio(): void
    {
        try {
            if (! Schema::hasColumn('devoluciones', 'es_registro_extemporaneo')) {
                Schema::table('devoluciones', function (Blueprint $table) {
                    $table->boolean('es_registro_extemporaneo')->default(false)->after('admin_id');
                    $table->foreignId('registrado_por_id')->nullable()->after('es_registro_extemporaneo')->constrained('users')->nullOnDelete();
                    $table->text('motivo_registro_tardio')->nullable()->after('explicacion_propiedad');
                });
            }
        } catch (\Throwable $e) {
            // Ignorar si ya fueron creadas concurrentemente
        }
    }

    /**
     * Muestra el formulario administrativo para «Registrar devolución reportada fuera de tiempo».
     *
     * Condiciones estrictas:
     * - Exclusivo para el administrador/bibliotecario autenticado.
     * - Solo disponible en reportes expirados cuyo objeto no haya sido recibido físicamente en biblioteca.
     * - No puede utilizarse cuando exista otra devolución activa, en corrección o verificada que resulte incompatible.
     */
    public function create(int|string $id): View|RedirectResponse
    {
        $this->asegurarColumnasRegistroTardio();
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

        $minFechaEntrega = null;
        $minHoraEntrega = null;
        if ($reporte->fecha_hallazgo) {
            $minFechaEntrega = $reporte->fecha_hallazgo instanceof Carbon
                ? $reporte->fecha_hallazgo->format('Y-m-d')
                : Carbon::parse($reporte->fecha_hallazgo)->format('Y-m-d');

            if (! empty($reporte->hora_hallazgo)) {
                $minHoraEntrega = substr((string) $reporte->hora_hallazgo, 0, 5);
            }
        }

        $maxFechaEntrega = null;
        $maxHoraEntrega = null;
        if ($reporte->fecha_vencimiento) {
            $rawVenc = $reporte->getRawOriginal('fecha_vencimiento');
            $vencimientoLocal = $rawVenc
                ? Carbon::parse($rawVenc, Publicacion::TIMEZONE)
                : Carbon::parse($reporte->fecha_vencimiento->format('Y-m-d H:i:s'), Publicacion::TIMEZONE);

            $maxFechaEntrega = $vencimientoLocal->format('Y-m-d');
            $maxHoraEntrega = $vencimientoLocal->copy()->subMinute()->format('H:i');
        }

        return view('admin.devoluciones-extemporaneas.create', compact(
            'reporte',
            'fotografiaPrincipal',
            'fotografiasAdicionales',
            'minFechaEntrega',
            'minHoraEntrega',
            'maxFechaEntrega',
            'maxHoraEntrega'
        ));
    }

    /**
     * Persiste la devolución reportada fuera de tiempo capturando motivo, datos y evidencia del receptor,
     * y conservando por separado la fecha y hora reales de entrega, el momento actual de captura,
     * el autor original que entregó y el bibliotecario responsable que documentó el registro.
     */
    public function store(StoreDevolucionExtemporaneaRequest $request, int|string $id): RedirectResponse
    {
        $this->asegurarColumnasRegistroTardio();
        $archivoGuardadoRuta = null;

        try {
            return DB::transaction(function () use ($request, $id, &$archivoGuardadoRuta) {
                /** @var Publicacion $reporte */
                $reporte = Publicacion::where('id', $id)->lockForUpdate()->firstOrFail();

                $user = Auth::user();
                if (! $user || ! method_exists($user, 'isAdmin') || ! $user->isAdmin()) {
                    abort(403, 'Acción exclusiva para el personal administrador o bibliotecario.');
                }

                if (! $reporte->puedeRegistrarDevolucionExtemporanea()) {
                    return redirect()
                        ->route('admin.moderacion.show', $reporte->id)
                        ->with('error', 'No es posible registrar una devolución reportada fuera de tiempo para este reporte porque no se encuentra expirado, ya fue recibido en biblioteca o existe otra devolución activa o verificada incompatible.');
                }

                $foto = $request->file('fotografia');
                $nombreArchivo = Str::uuid() . '.' . $foto->getClientOriginalExtension();
                $rutaDirectorio = 'devoluciones/' . $reporte->id;
                $archivoGuardadoRuta = $foto->storeAs($rutaDirectorio, $nombreArchivo, 'local');

                $ahoraMerida = Carbon::now(Publicacion::TIMEZONE);
                $fechaEntregaReal = $request->input('fecha_entrega');
                $horaEntregaReal = substr((string) $request->input('hora_entrega'), 0, 5);

                $devolucion = Devolucion::create([
                    'publicacion_id' => $reporte->id,
                    'user_id' => $reporte->user_id, // Autor original que realizó la entrega física
                    'es_registro_extemporaneo' => true,
                    'registrado_por_id' => $user->id, // Bibliotecario responsable que documentó el registro tardío
                    'receptor_nombre' => $request->input('receptor_nombre'),
                    'receptor_identificador' => $request->input('receptor_identificador'),
                    'fecha_entrega' => $fechaEntregaReal,
                    'hora_entrega' => $horaEntregaReal,
                    'fecha_captura' => $ahoraMerida,
                    'fecha_envio_revision' => $ahoraMerida,
                    'explicacion_propiedad' => $request->input('explicacion_propiedad'),
                    'motivo_registro_tardio' => $request->input('motivo_registro_tardio'),
                    'fotografia_ruta' => $archivoGuardadoRuta,
                    'fotografia_nombre_original' => $foto->getClientOriginalName(),
                    'fotografia_mime_type' => $foto->getClientMimeType(),
                    'fotografia_tamano_bytes' => $foto->getSize(),
                    'estado' => Devolucion::ESTADO_PENDIENTE,
                ]);

                // Mantener fuera del muro y en proceso de revisión, sin aprobar automáticamente,
                // sin alterar fecha_vencimiento original y sin crear recepción ficticia en biblioteca.
                $reporte->update([
                    'disponible' => false,
                    'devolucion_en_proceso' => true,
                    'estado_devolucion' => Devolucion::ESTADO_PENDIENTE,
                ]);

                return redirect()
                    ->route('admin.devoluciones.show', $devolucion->id)
                    ->with('status', 'La devolución reportada fuera de tiempo se registró y está pendiente de verificación');
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($archivoGuardadoRuta && Storage::disk('local')->exists($archivoGuardadoRuta)) {
                Storage::disk('local')->delete($archivoGuardadoRuta);
            }

            Log::error('Error al registrar devolución reportada fuera de tiempo: ' . $e->getMessage(), [
                'publicacion_id' => $id,
                'admin_id' => Auth::id(),
                'exception' => $e,
            ]);

            return redirect()
                ->route('admin.devoluciones-extemporaneas.create', $id)
                ->withInput()
                ->withErrors(['general' => 'Ocurrió un error al guardar la devolución reportada fuera de tiempo. Verifica los datos e intenta nuevamente.']);
        }
    }

    /**
     * Muestra el formulario para corregir una devolución reportada fuera de tiempo.
     * Exclusivo para el bibliotecario/administrador cuando la devolución se encuentra en 'Requiere corrección'.
     */
    public function edit(int|string $id): View|RedirectResponse
    {
        $this->asegurarColumnasRegistroTardio();
        $user = Auth::user();
        if (! $user || ! method_exists($user, 'isAdmin') || ! $user->isAdmin()) {
            abort(403, 'Acción exclusiva para el personal administrador o bibliotecario.');
        }

        $devolucion = Devolucion::with([
            'publicacion.user',
            'publicacion.fotografias',
            'publicacion.decisiones.user',
            'user',
            'registradoPor',
        ])->findOrFail($id);

        if (! $devolucion->isRegistroExtemporaneo()) {
            return redirect()
                ->route('admin.devoluciones.show', $devolucion->id)
                ->with('error', 'Esta devolución no corresponde a un registro fuera de tiempo.');
        }

        if ($devolucion->isAprobada()) {
            return redirect()
                ->route('admin.devoluciones.show', $devolucion->id)
                ->with('error', 'Esta devolución ya fue aprobada y no puede modificarse.');
        }

        if (! $devolucion->isRequiereCorreccion()) {
            return redirect()
                ->route('admin.devoluciones.show', $devolucion->id)
                ->with('warning', 'Esta devolución no se encuentra en estado de corrección en este momento.');
        }

        $reporte = $devolucion->publicacion;
        $fotografiaPrincipal = $reporte?->foto_principal ?? $reporte?->fotografias->first();
        $fotografiasAdicionales = $reporte?->fotografias->where('id', '!=', $fotografiaPrincipal?->id) ?? collect();

        $minFechaEntrega = null;
        $minHoraEntrega = null;
        if ($reporte && $reporte->fecha_hallazgo) {
            $minFechaEntrega = $reporte->fecha_hallazgo instanceof Carbon
                ? $reporte->fecha_hallazgo->format('Y-m-d')
                : Carbon::parse($reporte->fecha_hallazgo)->format('Y-m-d');

            if (! empty($reporte->hora_hallazgo)) {
                $minHoraEntrega = substr((string) $reporte->hora_hallazgo, 0, 5);
            }
        }

        $maxFechaEntrega = null;
        $maxHoraEntrega = null;
        if ($reporte && $reporte->fecha_vencimiento) {
            $rawVenc = $reporte->getRawOriginal('fecha_vencimiento');
            $vencimientoLocal = $rawVenc
                ? Carbon::parse($rawVenc, Publicacion::TIMEZONE)
                : Carbon::parse($reporte->fecha_vencimiento->format('Y-m-d H:i:s'), Publicacion::TIMEZONE);

            $maxFechaEntrega = $vencimientoLocal->format('Y-m-d');
            $maxHoraEntrega = $vencimientoLocal->copy()->subMinute()->format('H:i');
        }

        return view('admin.devoluciones-extemporaneas.edit', compact(
            'devolucion',
            'reporte',
            'fotografiaPrincipal',
            'fotografiasAdicionales',
            'minFechaEntrega',
            'minHoraEntrega',
            'maxFechaEntrega',
            'maxHoraEntrega'
        ));
    }

    /**
     * Actualiza y reenvía a revisión la devolución reportada fuera de tiempo.
     * Conserva el mismo identificador de devolución, sustituye la fotografía anterior si se sube una nueva,
     * y conserva el historial previo de decisiones de moderación.
     */
    public function update(UpdateDevolucionExtemporaneaRequest $request, int|string $id): RedirectResponse
    {
        $this->asegurarColumnasRegistroTardio();
        $user = Auth::user();
        if (! $user || ! method_exists($user, 'isAdmin') || ! $user->isAdmin()) {
            abort(403, 'Acción exclusiva para el personal administrador o bibliotecario.');
        }

        $archivoNuevoGuardadoRuta = null;
        $archivoViejoAEliminar = null;

        try {
            $devolucion = DB::transaction(function () use ($request, $id, &$archivoNuevoGuardadoRuta, &$archivoViejoAEliminar) {
                /** @var Devolucion $devolucion */
                $devolucion = Devolucion::where('id', $id)->lockForUpdate()->firstOrFail();
                $reporte = Publicacion::where('id', $devolucion->publicacion_id)->lockForUpdate()->firstOrFail();

                if (! $devolucion->isRegistroExtemporaneo()) {
                    return redirect()
                        ->route('admin.devoluciones.show', $devolucion->id)
                        ->with('error', 'Esta devolución no corresponde a un registro fuera de tiempo.');
                }

                if (! $devolucion->isRequiereCorreccion()) {
                    return redirect()
                        ->route('admin.devoluciones.show', $devolucion->id)
                        ->with('error', 'Esta devolución no se encuentra en estado de corrección o ya fue resuelta.');
                }

                // Procesar sustitución de fotografía si se adjuntó una nueva
                if ($request->hasFile('fotografia')) {
                    $nuevaFoto = $request->file('fotografia');
                    $nombreArchivo = Str::uuid() . '.' . $nuevaFoto->getClientOriginalExtension();
                    $rutaDirectorio = 'devoluciones/' . $reporte->id;

                    $archivoNuevoGuardadoRuta = $nuevaFoto->storeAs($rutaDirectorio, $nombreArchivo, 'local');

                    if ($devolucion->fotografia_ruta && $devolucion->fotografia_ruta !== $archivoNuevoGuardadoRuta) {
                        $archivoViejoAEliminar = $devolucion->fotografia_ruta;
                    }

                    $devolucion->fotografia_ruta = $archivoNuevoGuardadoRuta;
                    $devolucion->fotografia_nombre_original = $nuevaFoto->getClientOriginalName();
                    $devolucion->fotografia_mime_type = $nuevaFoto->getClientMimeType();
                    $devolucion->fotografia_tamano_bytes = $nuevaFoto->getSize();
                }

                $ahoraMerida = Carbon::now(Publicacion::TIMEZONE);
                $fechaEntregaReal = $request->input('fecha_entrega');
                $horaEntregaReal = substr((string) $request->input('hora_entrega'), 0, 5);

                // Actualizar la misma devolución sin crear un registro duplicado
                $devolucion->motivo_registro_tardio = $request->input('motivo_registro_tardio');
                $devolucion->receptor_nombre = $request->input('receptor_nombre');
                $devolucion->receptor_identificador = $request->input('receptor_identificador');
                $devolucion->fecha_entrega = $fechaEntregaReal;
                $devolucion->hora_entrega = $horaEntregaReal;
                $devolucion->explicacion_propiedad = $request->input('explicacion_propiedad');
                $devolucion->estado = Devolucion::ESTADO_PENDIENTE;
                $devolucion->fecha_envio_revision = $ahoraMerida;
                $devolucion->motivo_correccion = null;
                $devolucion->admin_id = null;
                $devolucion->fecha_resolucion = null;
                $devolucion->save();

                // Mantener el reporte fuera del muro y en proceso de revisión
                $reporte->update([
                    'disponible' => false,
                    'devolucion_en_proceso' => true,
                    'estado_devolucion' => Devolucion::ESTADO_PENDIENTE,
                ]);

                event(new DevolucionReenviadaRevision($devolucion, Auth::user()));

                return $devolucion;
            });

            if ($devolucion instanceof RedirectResponse) {
                return $devolucion;
            }

            if ($archivoViejoAEliminar && Storage::disk('local')->exists($archivoViejoAEliminar)) {
                Storage::disk('local')->delete($archivoViejoAEliminar);
            }

            return redirect()
                ->route('admin.devoluciones.show', $devolucion->id)
                ->with('status', 'La devolución reportada fuera de tiempo se corrigió y está pendiente de verificación');

        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($archivoNuevoGuardadoRuta && Storage::disk('local')->exists($archivoNuevoGuardadoRuta)) {
                Storage::disk('local')->delete($archivoNuevoGuardadoRuta);
            }

            Log::error('Error al actualizar devolución reportada fuera de tiempo: ' . $e->getMessage(), [
                'devolucion_id' => $id,
                'admin_id' => Auth::id(),
                'exception' => $e,
            ]);

            return redirect()
                ->route('admin.devoluciones-extemporaneas.edit', $id)
                ->withInput()
                ->withErrors(['general' => 'Ocurrió un error al actualizar la devolución. Verifica los datos e intenta nuevamente.']);
        }
    }
}
