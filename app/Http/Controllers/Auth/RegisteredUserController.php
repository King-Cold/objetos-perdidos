<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     * Subtarea 3: Creación de cuenta institucional con rol «Usuario general».
     * Subtarea 4: Inicio de sesión automático, regeneración de sesión y redirección al muro principal con notificación «Registro exitoso».
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = null;

        // 1. Creación atómica de la cuenta institucional
        try {
            DB::transaction(function () use ($request, &$user) {
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'password' => Hash::make($request->password),
                    'role' => User::ROLE_USUARIO_GENERAL,
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages([
                'email' => 'Este correo electrónico ya se encuentra registrado',
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry') || (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1062)) {
                throw ValidationException::withMessages([
                    'email' => 'Este correo electrónico ya se encuentra registrado',
                ]);
            }

            return back()->withInput($request->except(['password', 'password_confirmation']))->with(
                'error',
                'No fue posible completar el registro de tu cuenta en este momento. Por favor, verifica tus datos e inténtalo nuevamente.'
            );
        } catch (\Throwable $e) {
            return back()->withInput($request->except(['password', 'password_confirmation']))->with(
                'error',
                'Ocurrió un inconveniente al procesar tu registro. Por favor, inténtalo nuevamente.'
            );
        }

        // 2. Inicio de sesión automático y regeneración de sesión
        try {
            Auth::login($user);
            $request->session()->regenerate();
        } catch (\Throwable $e) {
            // Manejo de contingencia: la cuenta ya fue creada exitosamente en la BD.
            // No se vuelve a intentar la creación ni se elimina la cuenta existente.
            return redirect()->route('login')->with(
                'status',
                'Tu cuenta institucional fue creada correctamente. Por favor, inicia sesión con tus credenciales.'
            );
        }

        // 3. Redirección al panel/muro principal de la plataforma con la notificación institucional
        $redirectRoute = route('dashboard', absolute: false);

        return redirect()->intended($redirectRoute)->with('status', 'Registro exitoso');
    }
}
