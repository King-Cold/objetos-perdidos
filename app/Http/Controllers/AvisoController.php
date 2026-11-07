<?php

namespace App\Http\Controllers;

use App\Models\Aviso;
use App\Models\Devolucion;
use App\Models\Publicacion;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AvisoController extends Controller
{
    /**
     * Muestra el apartado privado de avisos del usuario autenticado (HU: Avisos / Task 1 y Task 2).
     *
     * Reglas aplicadas:
     * - Únicamente usuarios autenticados (protegido por middleware 'auth').
     * - Consulta exclusiva de los avisos propios del usuario en sesión.
     * - Orden cronológico descendente (más recientes primero).
     * - Paginación para optimizar la carga de información.
     * - Entrega del total de no leídos para actualizar indicadores de interfaz.
     */
    public function index(Request $request): View
    {
        $noLeidosCount = 0;

        try {
            if (! Schema::hasTable('avisos')) {
                $avisos = new LengthAwarePaginator(collect(), 0, 10);
                return view('avisos.index', compact('avisos', 'noLeidosCount'));
            }

            $user = Auth::user();
            $avisos = Aviso::paraUsuario($user->id)
                ->recientes()
                ->paginate(10);

            $noLeidosCount = (int) $user->avisosNoLeidos()->count();
        } catch (\Throwable) {
            $avisos = new LengthAwarePaginator(collect(), 0, 10);
            $noLeidosCount = 0;
        }

        return view('avisos.index', compact('avisos', 'noLeidosCount'));
    }

    /**
     * Marca un aviso individual como leído (HU: Avisos / Task 2).
     *
     * Reglas aplicadas:
     * - Verificación estricta de propiedad: únicamente el destinatario puede modificar el estado.
     * - Si el aviso ya estaba leído, no genera modificaciones innecesarias en base de datos.
     * - No modifica la publicación, reporte ni devolución relacionada.
     */
    public function marcarLeido(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $aviso = Aviso::findOrFail($id);

        // Seguridad: Un usuario únicamente puede modificar el estado de sus propios avisos
        if ($aviso->user_id !== Auth::id()) {
            abort(403, 'No tienes permiso para modificar este aviso.');
        }

        // Si ya está leído, no generar modificaciones ni sobreescrituras innecesarias
        if (! $aviso->leido) {
            $aviso->update([
                'leido' => true,
                'leido_at' => now(),
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Aviso marcado como leído.',
                'aviso' => $aviso->fresh(),
                'no_leidos_count' => (int) Auth::user()->avisosNoLeidos()->count(),
            ]);
        }

        return redirect()->back()->with('status', 'Aviso marcado como leído correctamente.');
    }

    /**
     * Marca todos los avisos pendientes del usuario autenticado como leídos (HU: Avisos / Task 2).
     *
     * Reglas aplicadas:
     * - Filtro estricto para actualizar únicamente avisos propios no leídos.
     * - Avisos previamente leídos conservan su estado y fecha original.
     * - Si no existen avisos pendientes, responde adecuadamente sin provocar errores.
     */
    public function marcarTodosLeidos(Request $request): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        $pendientesCount = (int) $user->avisosNoLeidos()->count();

        if ($pendientesCount === 0) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No tienes avisos pendientes por marcar como leídos.',
                    'actualizados' => 0,
                    'no_leidos_count' => 0,
                ]);
            }

            return redirect()->back()->with('info', 'No tienes avisos pendientes por marcar como leídos.');
        }

        $ahora = now();

        // Actualizar únicamente los avisos no leídos del usuario autenticado
        Aviso::paraUsuario($user->id)
            ->noLeidos()
            ->update([
                'leido' => true,
                'leido_at' => $ahora,
                'updated_at' => $ahora,
            ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Todos los avisos fueron marcados como leídos.',
                'actualizados' => $pendientesCount,
                'no_leidos_count' => 0,
            ]);
        }

        return redirect()->back()->with('status', 'Todos los avisos pendientes fueron marcados como leídos.');
    }

    /**
     * Navegación segura hacia el proceso vinculado a un aviso (HU: Avisos / Task 2).
     *
     * Reglas aplicadas:
     * - Comprueba que el aviso pertenezca al usuario autenticado (o administrador).
     * - Marca automáticamente el aviso como leído si estaba pendiente.
     * - Sanitiza el enlace de destino contra redirecciones no seguras o abiertas.
     * - Valida existencia del recurso relacionado (reporte o devolución); si no existe informa de forma segura.
     * - Valida permisos sobre el recurso vinculado antes de permitir la redirección.
     */
    public function ir(Request $request, int|string $id): RedirectResponse
    {
        $aviso = Aviso::findOrFail($id);

        // Seguridad: El aviso debe pertenecer al usuario autenticado (o administrador)
        if ($aviso->user_id !== Auth::id() && ! Auth::user()->isAdmin()) {
            abort(403, 'No tienes permiso para acceder a este aviso.');
        }

        // Marcar como leído de forma persistente si estaba pendiente al navegar al proceso
        if (! $aviso->leido) {
            $aviso->update([
                'leido' => true,
                'leido_at' => now(),
            ]);
        }

        $destino = trim((string) $aviso->enlace_destino);

        if ($destino === '') {
            return redirect()->route('avisos.index')->with('info', 'Este aviso no tiene un proceso específico vinculado.');
        }

        // Bloqueo de URLs maliciosas, relativas de protocolo o esquemas no seguros
        if (str_starts_with($destino, '//') || str_starts_with($destino, '\\') || preg_match('/^(javascript|data|vbscript):/i', $destino)) {
            return redirect()->route('avisos.index')->with('warning', 'El enlace de destino no es válido.');
        }

        $parsed = parse_url($destino);
        if (isset($parsed['host']) && $parsed['host'] !== $request->getHost()) {
            return redirect()->route('avisos.index')->with('warning', 'El enlace de destino externo no está permitido.');
        }

        $path = $parsed['path'] ?? $destino;

        // 1. Verificación si el destino o aviso vincula a un reporte / publicación (/reportes/{id} o /publicaciones/{id})
        if ($aviso->publicacion_id || preg_match('#^/(?:reportes|publicaciones)/(?<id>\d+)#', $path, $matches)) {
            $publicacionId = $aviso->publicacion_id ?? (int) ($matches['id'] ?? $matches[1] ?? 0);
            $publicacion = Publicacion::find($publicacionId);

            if (! $publicacion) {
                return redirect()->route('avisos.index')->with('warning', 'El reporte o publicación relacionado ya no se encuentra disponible.');
            }

            if ($publicacion->user_id !== Auth::id() && ! Auth::user()->isAdmin()) {
                abort(403, 'No tienes permiso para consultar este proceso.');
            }
        }

        // 2. Verificación si el destino o aviso vincula a una devolución (/devoluciones/{id})
        if ($aviso->devolucion_id || preg_match('#^/devoluciones/(?<id>\d+)#', $path, $matches)) {
            $devolucionId = $aviso->devolucion_id ?? (int) ($matches['id'] ?? $matches[1] ?? 0);
            $devolucion = Devolucion::find($devolucionId);

            if (! $devolucion) {
                return redirect()->route('avisos.index')->with('warning', 'El registro de devolución relacionado ya no se encuentra disponible.');
            }

            if (! $devolucion->puedeSerConsultadaPor(Auth::user())) {
                abort(403, 'No tienes permiso para consultar este proceso.');
            }
        }

        return redirect()->to($destino);
    }
}
