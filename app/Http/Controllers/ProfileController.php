<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        try {
            // Actualizar únicamente el teléfono de la cuenta autenticada (sin historial)
            $request->user()->update([
                'phone' => (string) $request->validated('phone'),
            ]);

            return Redirect::route('profile.edit')->with('status', 'Teléfono de contacto actualizado correctamente.');
        } catch (\Throwable $e) {
            // Conservar el teléfono anterior cuando la actualización no pueda completarse
            return Redirect::route('profile.edit')
                ->withErrors(['phone' => 'No se pudo completar la actualización. El teléfono anterior se conserva sin cambios.'])
                ->withInput();
        }
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
