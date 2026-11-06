<?php

namespace App\Http\Controllers;

use App\Models\Publicacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HistorialController extends Controller
{
    /**
     * Muestra el historial privado del objeto para el autor original
     * o para personal administrador/bibliotecario autorizado.
     *
     * Protege el acceso frente a visitantes, otros autores y receptores.
     * Muestra la identificación y situación actual claramente diferenciadas
     * de los eventos históricos.
     */
    public function show(Request $request, int|string $id): View
    {
        $publicacion = Publicacion::with([
            'user',
            'fotografias' => fn ($q) => $q->orderByDesc('es_principal'),
            'decisiones.user',
            'devoluciones.user',
            'devoluciones.admin',
        ])->findOrFail($id);

        $user = Auth::user();

        // 1. Comprobación estricta de autorización:
        // Solo el autor original del reporte o un administrador/bibliotecario tienen acceso.
        // Ni otros autores ni los receptores del objeto adquieren permisos para consultar el historial.
        $isAutor = $user && ($user->id === $publicacion->user_id);
        $isAdmin = $user && method_exists($user, 'isAdmin') && $user->isAdmin();

        if (! $isAutor && ! $isAdmin) {
            abort(403, 'No tienes permiso para consultar el historial de este objeto.');
        }

        // Obtener la línea de tiempo de eventos en orden cronológico ascendente (HU-13 / Tarea 2)
        $eventos = $publicacion->getEventosHistorial();

        return view('historial.show', compact('publicacion', 'eventos', 'isAutor', 'isAdmin'));
    }
}

