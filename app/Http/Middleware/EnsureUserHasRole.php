<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     * CA-09, CA-11, CA-12, CA-15: Comprueba en el servidor el rol de la cuenta autenticada.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Comprobación de rol de Administrador/bibliotecario (CA-09, CA-15)
        if ($role === 'admin' || $role === 'Administrador/bibliotecario') {
            if (! $user->isAdmin()) {
                abort(403, 'No tienes permiso para realizar esta acción');
            }
        } elseif ($role === 'general' || $role === 'Usuario general') {
            if (! $user->isUsuarioGeneral()) {
                abort(403, 'No tienes permiso para realizar esta acción');
            }
        }

        return $next($request);
    }
}

