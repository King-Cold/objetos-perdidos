<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetLinkRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     */
    public function store(PasswordResetLinkRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();
        $request->hitRateLimiter();

        $email = $request->validated('email');

        // Consultar la base de datos con el correo previamente normalizado (trim + lowercase)
        $user = User::where('email', $email)->first();

        // Si el usuario está registrado, despachar la generación del enlace/token de recuperación
        // El destinatario lógico es exclusivamente el correo registrado de esa cuenta ($user->email)
        // No se crean cuentas, no se modifican contraseñas ni se alteran datos de usuario
        if ($user) {
            try {
                Password::broker()->sendResetLink(['email' => $user->email]);
            } catch (\Throwable $e) {
                // Registrar el error sin exponer credenciales SMTP ni detalles técnicos en la vista
                report($e);

                return back()
                    ->withInput($request->only('email'))
                    ->with('error', 'No es posible procesar la recuperación en este momento. Intenta nuevamente más tarde')
                    ->withErrors(['email' => 'No es posible procesar la recuperación en este momento. Intenta nuevamente más tarde']);
            }
        }

        // Respuesta genérica idéntica para prevenir la enumeración pública de cuentas
        return back()->with('status', 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña');
    }
}
