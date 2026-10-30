<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionInactivity
{
    /**
     * Límite de inactividad permitido: 10 minutos (600 segundos) (CA-21, CT-09)
     */
    public const TIMEOUT_SECONDS = 600;

    /**
     * Mensaje obligatorio de expiración por inactividad (CA-26)
     */
    public const EXPIRATION_MESSAGE = 'Tu sesión expiró por inactividad. Inicia sesión nuevamente para continuar';

    /**
     * Handle an incoming request.
     * CA-21 a CA-27: Vencimiento por 10 minutos de inactividad y protección contra caché.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('web')->check()) {
            $lastActivity = $request->session()->get('last_activity_time');

            // CA-25: Comprobar el vencimiento antes de atender o guardar cambios
            if ($lastActivity !== null) {
                $idleSeconds = now()->timestamp - (int) $lastActivity;

                // CA-21, CA-24, CA-25: Si alcanza o supera los 10 minutos (>= 600s), la sesión está vencida
                if ($idleSeconds >= self::TIMEOUT_SECONDS) {
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    // Solicitudes AJAX / API
                    if ($request->expectsJson() || $request->isXmlHttpRequest()) {
                        return response()->json([
                            'message' => self::EXPIRATION_MESSAGE,
                        ], 401);
                    }

                    // CA-26: Las funciones privadas solicitan autenticación y la consulta del muro puede continuar
                    if ($request->is('muro') || $request->is('/')) {
                        $request->session()->flash('warning', self::EXPIRATION_MESSAGE);
                        return $next($request);
                    }

                    return redirect()->route('login')->with('warning', self::EXPIRATION_MESSAGE);
                }
            }

            // CA-22 y CA-23: Distinguir solicitudes automáticas que no reinician el plazo
            $isAutomatedRequest = $this->isAutomated($request);

            // Procesar la solicitud autorizada
            $response = $next($request);

            // CA-22, CA-23, CA-24: Actualizar la última actividad únicamente si es válida y autorizada
            // Si el código es 403 (no autorizada) o es una petición automática, NO se renueva la inactividad
            if (! $isAutomatedRequest && $response->getStatusCode() < 400 && $response->getStatusCode() !== 403) {
                $request->session()->put('last_activity_time', now()->timestamp);
            }

            // CA-27: Evitar que datos privados se recuperen desde la caché del navegador al usar botón «Atrás»
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');

            return $response;
        }

        return $next($request);
    }

    /**
     * Determina si la solicitud corresponde a una actualización automática (CA-23, CT-09)
     */
    protected function isAutomated(Request $request): bool
    {
        return $request->headers->has('X-Automated-Request')
            || $request->headers->has('X-Requested-With-KeepAlive')
            || $request->query->has('automated')
            || $request->query->has('polling')
            || $request->is('api/ping*')
            || $request->is('livewire/message/*');
    }
}
