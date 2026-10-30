<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     * CA-03, CA-05 a CA-09, CA-28, CA-29, CA-30, CA-31, CA-32.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            // Autenticación con credenciales normalizadas (CA-03, CA-05)
            $request->authenticate();

            // Renovación de identificador de sesión contra fijación de sesión (CA-07)
            $request->session()->regenerate();

            // Marcador de última interacción para control de inactividad (CA-21)
            $request->session()->put('last_activity_time', now()->timestamp);

            /** @var User $user */
            $user = Auth::user();

            // Destino por defecto según el rol obtenido del servidor (CA-07, CA-08, CA-09)
            $defaultRoute = $user->isAdmin()
                ? route('admin.dashboard')
                : route('muro');

            // Determinar si existe un retorno previo autorizado (CA-08, CA-28, CA-29, CA-30)
            $targetUrl = $this->resolveSafeReturnDestination($request, $user, $defaultRoute);

            return redirect()->to($targetUrl);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // CA-31, CA-32: Mensaje seguro sin exponer SQL, rutas ni contraseñas
            Log::error('Error interno seguro durante inicio de sesión', [
                'error_type' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Ocurrió un error interno al procesar su solicitud. Por favor intente más tarde.');
        }
    }

    /**
     * Destroy an authenticated session.
     * CA-19, CA-20, CA-31, CA-32.
     */
    public function destroy(Request $request): RedirectResponse
    {
        try {
            Auth::guard('web')->logout();

            $request->session()->invalidate();

            $request->session()->regenerateToken();

            return redirect()->route('muro')->with('status', 'Sesión cerrada correctamente');

        } catch (\Throwable $e) {
            Log::error('Error interno seguro durante cierre de sesión', [
                'error_type' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('muro')->with('error', 'Ocurrió un problema al cerrar la sesión.');
        }
    }

    /**
     * Valida y resuelve el destino de retorno seguro.
     * CA-08, CA-28, CA-29, CA-30.
     */
    protected function resolveSafeReturnDestination(Request $request, User $user, string $defaultRoute): string
    {
        // 1. Destino explícito por publicación seleccionada previamente (CA-28)
        if ($request->session()->has('intended_publication_id')) {
            $pubId = $request->session()->pull('intended_publication_id');
            $publicacion = Publicacion::find($pubId);

            // CA-29: Comprobar disponibilidad antes de regresar
            if ($publicacion && $publicacion->isDisponible()) {
                return route('publicaciones.show', $publicacion->id);
            }

            // Si dejó de estar disponible o no existe
            $request->session()->flash('warning', 'Esta publicación no está disponible para consulta');
            return route('muro');
        }

        // 2. Destino previsto general en sesión
        $intended = $request->session()->pull('url.intended');
        if (! $intended) {
            return $defaultRoute;
        }

        // CA-30: Validar que sea una ruta interna válida
        $parsed = parse_url($intended);

        // Si tiene host externo, rechazar intento de redirección abierta
        if (isset($parsed['host']) && $parsed['host'] !== $request->getHost()) {
            return $defaultRoute;
        }

        $path = $parsed['path'] ?? '/';

        // Prevenir esquema de doble barra '//evil.com'
        if (str_starts_with($intended, '//') || str_starts_with($path, '//')) {
            return $defaultRoute;
        }

        // CA-30: Prevenir que un usuario general sea redirigido a paneles administrativos
        if (! $user->isAdmin() && (str_starts_with($path, '/admin') || str_contains($path, 'admin'))) {
            return route('muro');
        }

        // CA-29: Si la URL interna es una publicación, comprobar disponibilidad
        if (preg_match('#^/publicaciones/(\d+)#', $path, $matches)) {
            $pubId = $matches[1];
            $publicacion = Publicacion::find($pubId);

            if (! $publicacion || ! $publicacion->isDisponible()) {
                $request->session()->flash('warning', 'Esta publicación no está disponible para consulta');
                return route('muro');
            }
        }

        return $intended;
    }
}
