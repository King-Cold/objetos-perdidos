<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHallazgoRequest;
use App\Http\Requests\UpdateReporteRequest;
use App\Models\Fotografia;
use App\Models\Publicacion;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReporteController extends Controller
{
    /**
     * Muestra la lista de reportes propios del usuario autenticado.
     * HU-06 Tarea 06.1: CA-01 a CA-05, CA-07, CA-08, CT-01, CT-02.
     */
    public function index(): View
    {
        $reportes = Publicacion::with([
            'fotografias' => fn ($q) => $q->orderByDesc('es_principal'),
            'user',
        ])
            ->where('user_id', Auth::id())
            ->latest('id')
            ->get();

        return view('reportes.mis-reportes', compact('reportes'));
    }

    /**
     * Muestra el detalle privado del reporte para su autor o personal de biblioteca.
     * HU-06 Tarea 06.1: CA-06, CA-07, CA-08, CT-01, CT-02.
     */
    public function show(int|string $id): View
    {
        $publicacion = Publicacion::with(['fotografias' => fn ($q) => $q->orderByDesc('es_principal'), 'user'])
            ->findOrFail($id);

        // CT-01, CT-02: Protección estricta contra acceso de usuarios ajenos
        if ($publicacion->user_id !== Auth::id() && ! Auth::user()->isAdmin()) {
            abort(403, 'No tienes permiso para realizar esta acción');
        }

        return view('reportes.show', compact('publicacion'));
    }

    /**
     * Muestra el formulario para editar un reporte propio (HU-06 Tarea 06.2: CA-09 a CA-17, CA-22, CA-23).
     */
    public function edit(int|string $id): View|RedirectResponse
    {
        $publicacion = Publicacion::with(['fotografias' => fn ($q) => $q->orderByDesc('es_principal'), 'user'])
            ->findOrFail($id);

        // Comprobación estricta de autoría (CA-10, CT-01, CT-02)
        if ($publicacion->user_id !== Auth::id() && ! Auth::user()->isAdmin()) {
            abort(403, 'No tienes permiso para realizar esta acción');
        }

        // CA-09, CA-10, CA-40: Impedir edición en estados no permitidos o resguardo confirmado
        if (! $publicacion->puedeEditarse()) {
            return redirect()->route('reportes.mis-reportes')
                ->with('warning', 'El estado del reporte cambió. Consulta su información actual antes de continuar');
        }

        // CA-15, CA-16: Límites de fecha y hora calculados sobre la fecha_captura original
        $fechaCaptura = $publicacion->fecha_captura ?? $publicacion->created_at ?? Carbon::now(Publicacion::TIMEZONE);
        if (! $fechaCaptura instanceof Carbon) {
            $fechaCaptura = Carbon::parse($fechaCaptura, Publicacion::TIMEZONE);
        }

        $maxDate = $fechaCaptura->format('Y-m-d');
        $minDate = $fechaCaptura->copy()->subDays(7)->format('Y-m-d');
        $maxTime = $fechaCaptura->format('H:i');

        return view('reportes.edit', compact('publicacion', 'minDate', 'maxDate', 'maxTime'));
    }

    /**
     * Guarda los cambios de edición del reporte propio (HU-06 Tarea 06.2: CA-12, CA-13, CA-14, CA-15, CA-16, CA-17, CA-22).
     */
    public function update(UpdateReporteRequest $request, int|string $id): RedirectResponse
    {
        $publicacion = Publicacion::findOrFail($id);

        // Comprobación estricta de autoría (CA-10, CT-01, CT-02)
        if ($publicacion->user_id !== Auth::id() && ! Auth::user()->isAdmin()) {
            abort(403, 'No tienes permiso para realizar esta acción');
        }

        // CA-09, CA-10, CA-40: Impedir edición en estados no permitidos
        if (! $publicacion->puedeEditarse()) {
            return redirect()->route('reportes.mis-reportes')
                ->with('warning', 'El estado del reporte cambió. Consulta su información actual antes de continuar');
        }

        $validated = $request->validated();
        $rutasGuardadas = [];

        DB::beginTransaction();
        try {
            // CA-13: Protección contra alteración de folio, autor, fecha de captura, primera aprobación y vigencia.
            // CA-14: Solo se actualizan los campos modificables permitidos.
            $publicacion->nombre = $validated['nombre'];
            $publicacion->titulo = $validated['nombre'];
            if (isset($validated['categoria'])) {
                $publicacion->categoria = $validated['categoria'];
            }
            $publicacion->descripcion = $validated['descripcion'];
            $publicacion->fecha_hallazgo = $validated['fecha_hallazgo'];
            $publicacion->hora_hallazgo = $validated['hora_hallazgo'] ?? null;
            $publicacion->area_zona = $validated['area_zona'];
            $publicacion->ubicacion_especifica = $validated['ubicacion_especifica'];

            // CA-24, CA-26: Al guardar, deja el reporte en 'Pendiente de aprobación' y lo retira del muro (disponible = false)
            $publicacion->estado = Publicacion::ESTADO_PENDIENTE;
            $publicacion->disponible = false;

            $publicacion->save();

            // 1. Eliminar fotografías marcadas para retiro (CA-18, CA-30)
            $fotosAEliminar = $validated['eliminar_fotos'] ?? [];
            if (! empty($fotosAEliminar)) {
                $fotos = Fotografia::where('publicacion_id', $publicacion->id)
                    ->whereIn('id', $fotosAEliminar)
                    ->get();

                foreach ($fotos as $foto) {
                    try {
                        Storage::disk('local')->delete($foto->ruta);
                    } catch (\Throwable $storageEx) {
                        // Ignorar fallo secundario de borrado si no existe en disco físico
                    }
                    $foto->delete();
                }
            }

            // 2. Almacenar privadamente nuevas fotografías (CA-18, CA-19)
            $nuevasFotos = $request->file('nuevas_fotografias', []);
            if (! is_array($nuevasFotos)) {
                $nuevasFotos = [$nuevasFotos];
            }

            foreach ($nuevasFotos as $nuevaFoto) {
                if (! $nuevaFoto || ! $nuevaFoto->isValid()) {
                    continue;
                }

                $ruta = $nuevaFoto->store('hallazgos', 'local');
                $rutasGuardadas[] = $ruta;

                Fotografia::create([
                    'publicacion_id' => $publicacion->id,
                    'ruta' => $ruta,
                    'nombre_original' => $nuevaFoto->getClientOriginalName(),
                    'mime_type' => $nuevaFoto->getClientMimeType() ?: $nuevaFoto->getMimeType(),
                    'tamano_bytes' => $nuevaFoto->getSize(),
                    'es_principal' => false,
                ]);
            }

            // 3. Gestionar fotografía principal (CA-20)
            if (! empty($validated['foto_principal_id'])) {
                $publicacion->fotografias()->update(['es_principal' => false]);
                $publicacion->fotografias()
                    ->where('id', $validated['foto_principal_id'])
                    ->update(['es_principal' => true]);
            } else {
                // Si la principal fue retirada o ninguna está marcada, designar la primera restante
                $tienePrincipal = $publicacion->fotografias()->where('es_principal', true)->exists();
                if (! $tienePrincipal) {
                    $primera = $publicacion->fotografias()->first();
                    if ($primera) {
                        $primera->update(['es_principal' => true]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('reportes.mis-reportes')
                ->with('status', 'Los cambios se guardaron y el reporte se envió a revisión');

        } catch (\Throwable $e) {
            DB::rollBack();

            foreach ($rutasGuardadas as $ruta) {
                try {
                    Storage::disk('local')->delete($ruta);
                } catch (\Throwable $storageEx) {
                    // Ignorar
                }
            }

            Log::error('Error al actualizar reporte: ' . $e->getMessage(), [
                'publicacion_id' => $publicacion->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Ocurrió un error al actualizar el reporte. Por favor, intenta nuevamente.');
        }
    }

    /**
     * Muestra la pantalla de confirmación para cancelar un reporte propio (CA-31, CA-33, CA-38, CA-40).
     */
    public function confirmCancel(int|string $id): View|RedirectResponse
    {
        $publicacion = Publicacion::with(['fotografias', 'user'])->findOrFail($id);

        // CT-01, CT-02: Comprobación estricta de autoría
        if ($publicacion->user_id !== Auth::id() && ! Auth::user()->isAdmin()) {
            abort(403, 'No tienes permiso para realizar esta acción');
        }

        // CA-38: Una publicación disponible no tiene cancelación directa para el autor
        if ($publicacion->estado === Publicacion::ESTADO_APROBADA && (bool) $publicacion->disponible) {
            return redirect()->route('reportes.mis-reportes')
                ->with('warning', 'Para cancelar una publicación disponible en el muro, por favor acude a la biblioteca institucional o contacta al personal administrativo encargado.');
        }

        // CA-31, CA-40: Si el estado no permite cancelar (ej. ya cancelado, devuelto, o bajo resguardo)
        if (! $publicacion->puedeCancelarse()) {
            return redirect()->route('reportes.mis-reportes')
                ->with('warning', 'El estado del reporte cambió. Consulta su información actual antes de continuar');
        }

        return view('reportes.cancelar', compact('publicacion'));
    }

    /**
     * Procesa la cancelación de un reporte propio solicitando motivo y registrando fecha (CA-31 a CA-37, CA-39, CA-40).
     */
    public function cancel(Request $request, int|string $id): RedirectResponse
    {
        $publicacion = Publicacion::findOrFail($id);

        // CT-01, CT-02: Comprobación estricta de autoría
        if ($publicacion->user_id !== Auth::id() && ! Auth::user()->isAdmin()) {
            abort(403, 'No tienes permiso para realizar esta acción');
        }

        // CA-38: Bloqueo de cancelación directa para publicaciones disponibles
        if ($publicacion->estado === Publicacion::ESTADO_APROBADA && (bool) $publicacion->disponible) {
            return redirect()->route('reportes.mis-reportes')
                ->with('warning', 'Para cancelar una publicación disponible en el muro, por favor acude a la biblioteca institucional o contacta al personal administrativo encargado.');
        }

        // CA-31, CA-40: Si el estado cambió y la acción deja de estar permitida
        if (! $publicacion->puedeCancelarse()) {
            return redirect()->route('reportes.mis-reportes')
                ->with('warning', 'El estado del reporte cambió. Consulta su información actual antes de continuar');
        }

        // CA-32: Exigir motivo de cancelación y rechazar valores vacíos o de puros espacios
        $request->validate([
            'motivo_cancelacion' => [
                'required',
                'string',
                'max:500',
                function ($attribute, $value, $fail) {
                    if (trim((string) $value) === '') {
                        $fail('El motivo de cancelación es obligatorio.');
                    }
                },
            ],
        ], [
            'motivo_cancelacion.required' => 'El motivo de cancelación es obligatorio.',
            'motivo_cancelacion.max' => 'El motivo de cancelación no debe superar los 500 caracteres.',
        ]);

        DB::beginTransaction();
        try {
            // CA-35: Cancelación válida deja en 'Cancelado', conserva folio y contenido, registra motivo y fecha
            // CA-39: No altera devoluciones, incidencias ni evidencias
            $publicacion->estado = Publicacion::ESTADO_CANCELADO;
            $publicacion->disponible = false;
            $publicacion->motivo_cancelacion = trim((string) $request->input('motivo_cancelacion'));
            $publicacion->fecha_cancelacion = Carbon::now(Publicacion::TIMEZONE);
            $publicacion->save();

            DB::commit();

            // CA-36: Mensaje exacto institucional
            return redirect()->route('reportes.mis-reportes')
                ->with('status', 'El reporte se canceló correctamente');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error al cancelar reporte: ' . $e->getMessage(), [
                'publicacion_id' => $publicacion->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Ocurrió un error al intentar cancelar el reporte. Por favor, intenta nuevamente.');
        }
    }

    /**
     * Muestra el formulario para registrar un hallazgo (objeto encontrado).
     * Tareas 05.1 y 05.2
     */
    public function create(): View
    {
        $user = Auth::user();

        $timezone = StoreHallazgoRequest::TIMEZONE;
        $now = Carbon::now($timezone);
        $maxDate = $now->format('Y-m-d');
        $minDate = $now->copy()->subDays(7)->format('Y-m-d');
        $currentTime = $now->format('H:i');

        // Generar un token único de envío (idempotencia) para prevenir reenvíos accidentales
        $formToken = (string) Str::uuid();

        return view('reportes.create', compact('user', 'minDate', 'maxDate', 'currentTime', 'formToken'));
    }

    /**
     * Guarda el reporte pendiente de hallazgo y almacena privadamente sus fotografías.
     * Tareas 05.4 y 05.5: Guardar el reporte, privacidad, prevención de duplicados y redirección.
     */
    public function store(StoreHallazgoRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $userId = Auth::id();
        $formToken = $request->input('form_token');

        // 1. Control de duplicados por Token de Idempotencia
        if ($formToken) {
            $tokenCacheKey = "hallazgo_token_{$userId}_{$formToken}";
            if (Cache::has($tokenCacheKey)) {
                $existingId = Cache::get($tokenCacheKey);
                $existingPub = Publicacion::with('fotografias')->find($existingId);
                if ($existingPub) {
                    return $this->respondSuccess($request, $existingPub, true);
                }
            }
        }

        // 2. Bloqueo atómico por usuario para evitar pulsaciones simultáneas en el mismo instante
        $lockKey = "hallazgo_user_lock_{$userId}";
        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            usleep(300000); // Esperar 300ms a que termine la petición previa
            $recentPub = Publicacion::where('user_id', $userId)
                ->where('created_at', '>=', Carbon::now()->subSeconds(10))
                ->latest()
                ->first();

            if ($recentPub) {
                return $this->respondSuccess($request, $recentPub, true);
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tu solicitud ya se está procesando. Por favor espera un momento.',
                ], 429);
            }

            return redirect()->route('reportes.mis-reportes');
        }

        // 3. Verificación heurística de reporte duplicado reciente (mismo usuario, nombre, fecha y descripción en los últimos 60 seg)
        $duplicate = Publicacion::where('user_id', $userId)
            ->where('nombre', $validated['nombre'])
            ->where('fecha_hallazgo', $validated['fecha_hallazgo'])
            ->where('descripcion', $validated['descripcion'])
            ->where('created_at', '>=', Carbon::now()->subSeconds(60))
            ->latest()
            ->first();

        if ($duplicate) {
            $lock->release();
            return $this->respondSuccess($request, $duplicate, true);
        }

        $rutasGuardadas = [];

        DB::beginTransaction();
        try {
            // Fecha y hora exacta de captura generada en el servidor (America/Merida)
            $timezone = StoreHallazgoRequest::TIMEZONE;
            $fechaCaptura = Carbon::now($timezone);

            // Generación de folio único irrepetible (OBJ-YYYYMMDD-XXXX)
            $folio = Publicacion::generarFolio();

            // Guardar reporte asociado estrictamente al usuario autenticado en sesión
            $publicacion = Publicacion::create([
                'user_id' => $userId,
                'folio' => $folio,
                'nombre' => $validated['nombre'],
                'titulo' => $validated['nombre'],
                'descripcion' => $validated['descripcion'],
                'fecha_hallazgo' => $validated['fecha_hallazgo'],
                'hora_hallazgo' => $validated['hora_hallazgo'] ?? null,
                'area_zona' => $validated['area_zona'],
                'ubicacion_especifica' => $validated['ubicacion_especifica'],
                'lugar_hallazgo' => $validated['area_zona'] . ' - ' . $validated['ubicacion_especifica'],
                'categoria' => 'General',
                'estado' => Publicacion::ESTADO_PENDIENTE, // 'Pendiente de aprobación'
                'disponible' => false, // No publicado ni reclamable aún
                'fecha_captura' => $fechaCaptura,
                'fecha_envio_revision' => $fechaCaptura,
            ]);

            // Guardado privado de fotografías en storage/app/private/hallazgos (disco local)
            $fotos = $request->file('fotografias', []);
            if (! is_array($fotos)) {
                $fotos = [$fotos];
            }

            foreach ($fotos as $foto) {
                if (! $foto || ! $foto->isValid()) {
                    continue;
                }

                // Almacenamiento en disco privado local (Tarea 05.5)
                $ruta = $foto->store('hallazgos', 'local');
                $rutasGuardadas[] = $ruta;

                $publicacion->fotografias()->create([
                    'ruta' => $ruta,
                    'nombre_original' => $foto->getClientOriginalName(),
                    'mime_type' => $foto->getClientMimeType() ?: $foto->getMimeType(),
                    'tamano_bytes' => $foto->getSize(),
                ]);
            }

            DB::commit();

            // Registrar el token procesado en caché para evitar reenvíos
            if ($formToken) {
                Cache::put("hallazgo_token_{$userId}_{$formToken}", $publicacion->id, now()->addHours(2));
            }

            $lock->release();

            return $this->respondSuccess($request, $publicacion, false);

        } catch (\Throwable $e) {
            DB::rollBack();

            // Limpieza de archivos huérfanos almacenados parcialmente si falló la transacción
            foreach ($rutasGuardadas as $ruta) {
                try {
                    Storage::disk('local')->delete($ruta);
                } catch (\Throwable $storageEx) {
                    // Ignorar errores secundarios de limpieza
                }
            }

            try {
                $lock->release();
            } catch (\Throwable $lockEx) {
                // Ignorar
            }

            Log::error('Error al guardar hallazgo pendiente: ' . $e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ocurrió un error al intentar registrar el reporte. Por favor, intenta nuevamente.',
                ], 500);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Ocurrió un error al intentar registrar el reporte. Por favor, intenta nuevamente.');
        }
    }

    /**
     * Respuesta estandarizada tras registrar con éxito (o detectar duplicado idempotente).
     */
    protected function respondSuccess(Request $request, Publicacion $publicacion, bool $isDuplicate = false): RedirectResponse|JsonResponse
    {
        $mensajeExito = "El hallazgo ha sido registrado exitosamente con el folio {$publicacion->folio} y quedó pendiente de aprobación por Biblioteca.";

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'is_duplicate' => $isDuplicate,
                'message' => $mensajeExito,
                'folio' => $publicacion->folio,
                'estado' => $publicacion->estado,
                'publicacion_id' => $publicacion->id,
                'redirect_url' => route('reportes.mis-reportes'),
            ], 200);
        }

        return redirect()
            ->route('reportes.mis-reportes')
            ->with('status', $mensajeExito)
            ->with('folio', $publicacion->folio)
            ->with('estado', $publicacion->estado)
            ->with('nombre_objeto', $publicacion->nombre);
    }
}
