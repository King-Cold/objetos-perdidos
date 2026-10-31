<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHallazgoRequest;
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
