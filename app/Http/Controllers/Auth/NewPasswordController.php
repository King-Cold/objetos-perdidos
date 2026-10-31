<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Muestra la vista de restablecimiento de contraseña.
     * Valida en el servidor el token y su asociación con la cuenta antes de mostrar el formulario (Requerimiento 1 y 2).
     * Abrir el enlace y consultar el formulario no consume el token ni modifica la contraseña (Requerimiento 13).
     */
    public function create(Request $request): View
    {
        $token = (string) $request->route('token');
        $email = (string) $request->query('email', '');

        $validation = $this->validateRecoveryToken($token, $email);

        return view('auth.reset-password', [
            'request' => $request,
            'token' => $token,
            'email' => $validation['email'] ?? $email,
            'isValid' => $validation['valid'],
            'errorMessage' => $validation['valid'] ? null : 'El enlace de recuperación no es válido o ha vencido. Solicita uno nuevo',
        ]);
    }

    /**
     * Procesa la solicitud de una nueva contraseña.
     * Revalida el token y aplica consumo atómico transaccional para proteger contra solicitudes concurrentes (Requerimientos 13, 14 y 15).
     */
    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $email = $request->validated('email');
        $token = $request->validated('token');
        $newPassword = $request->validated('password');

        $errorMessage = 'El enlace de recuperación no es válido o ha vencido. Solicita uno nuevo';

        $user = User::where('email', $email)->first();

        if (! $user) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', $errorMessage)
                ->withErrors(['email' => $errorMessage]);
        }

        // Operación transaccional con bloqueo a nivel de registro para garantizar uso único estricto ante concurrencia
        $resetSuccess = false;
        try {
            $resetSuccess = DB::transaction(function () use ($user, $token, $newPassword) {
                $record = DB::table('password_reset_tokens')
                    ->where('email', $user->email)
                    ->lockForUpdate()
                    ->first();

                // 1. Token inexistente o ya consumido
                if (! $record) {
                    return false;
                }

                // 2. Vigencia exacta: debe ser menor a 15 minutos; al alcanzar 15 minutos exactos se considera vencido
                $createdAt = Carbon::parse($record->created_at);
                if (now()->gte($createdAt->copy()->addMinutes(15))) {
                    DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                    return false;
                }

                // 3. Validación criptográfica del token (rechaza alterados o correspondientes a otra cuenta)
                if (! Hash::check($token, $record->token)) {
                    return false;
                }

                // 4. Consumo atómico: eliminación inmediata del token en la misma transacción bloqueada
                $deleted = DB::table('password_reset_tokens')
                    ->where('email', $user->email)
                    ->delete();

                if ($deleted !== 1) {
                    return false;
                }

                // 5. Actualización segura de la contraseña para la cuenta verificada (únicamente password y remember_token)
                $user->forceFill([
                    'password' => Hash::make($newPassword),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                return true;
            });
        } catch (\Throwable $e) {
            // En caso de falla técnica, no exponer detalles ni tokens en bitácoras y asegurar que no haya éxito parcial
            $resetSuccess = false;
        }

        if (! $resetSuccess) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', $errorMessage)
                ->withErrors(['email' => $errorMessage]);
        }

        return redirect()->route('login')->with('status', 'Tu contraseña se actualizó correctamente. Inicia sesión con tu nueva contraseña');
    }

    /**
     * Valida la existencia, expiración y asociación del token con la cuenta destinataria.
     *
     * @param  string|null  $token
     * @param  string|null  $email
     * @return array{valid: bool, email: string|null, user: User|null}
     */
    protected function validateRecoveryToken(?string $token, ?string $email = null): array
    {
        if (empty($token)) {
            return ['valid' => false, 'email' => null, 'user' => null];
        }

        $email = $email ? strtolower(trim($email)) : null;

        if ($email) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                return ['valid' => false, 'email' => $email, 'user' => null];
            }

            $record = DB::table('password_reset_tokens')->where('email', $email)->first();
            if (! $record) {
                return ['valid' => false, 'email' => $email, 'user' => $user];
            }

            // Expiración estricta de 15 minutos (>= 15 min -> vencido)
            $createdAt = Carbon::parse($record->created_at);
            if (now()->gte($createdAt->copy()->addMinutes(15))) {
                return ['valid' => false, 'email' => $email, 'user' => $user];
            }

            // Verificación del hash del token
            if (! Hash::check($token, $record->token)) {
                return ['valid' => false, 'email' => $email, 'user' => $user];
            }

            return ['valid' => true, 'email' => $email, 'user' => $user];
        }

        // Si el correo no vino en la URL, buscar entre los registros vigentes
        $records = DB::table('password_reset_tokens')->get();
        foreach ($records as $r) {
            $createdAt = Carbon::parse($r->created_at);
            if (now()->gte($createdAt->copy()->addMinutes(15))) {
                continue;
            }

            if (Hash::check($token, $r->token)) {
                $user = User::where('email', $r->email)->first();
                if ($user) {
                    return ['valid' => true, 'email' => $r->email, 'user' => $user];
                }
            }
        }

        return ['valid' => false, 'email' => null, 'user' => null];
    }
}
