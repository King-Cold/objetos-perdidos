<?php

namespace App\Http\Controllers;

use App\Models\Fotografia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class FotografiaController extends Controller
{
    /**
     * Muestra o transmite de manera controlada y privada una fotografía asociada a un reporte.
     * 
     * Reglas de acceso y privacidad (Tarea 05.5 y HU Muro Público):
     * - Las fotografías de reportes aprobados y vigentes en el muro público están disponibles para consulta pública.
     * - Las fotografías de reportes pendientes o restringidos solo pueden ser visualizadas por el autor o personal administrativo.
     * - Visitantes no autenticados que intentan acceder a fotos pendientes son redirigidos al login.
     * - Usuarios no autorizados reciben 403 Forbidden.
     */
    public function show(Fotografia $fotografia): Response
    {
        $publicacion = $fotografia->publicacion;

        if (! $publicacion) {
            abort(404, 'Publicación asociada no encontrada.');
        }

        $user = Auth::user();
        $isAuthor = $user && $publicacion->user_id === $user->id;
        $isAdmin = $user && method_exists($user, 'isAdmin') && $user->isAdmin();
        $isPublicPublished = method_exists($publicacion, 'isDisponible') 
            && $publicacion->isDisponible() 
            && $publicacion->estado === 'Aprobada';

        if (! $isAuthor && ! $isAdmin && ! $isPublicPublished) {
            if (! $user) {
                return redirect()->guest(route('login'));
            }
            abort(403, 'No tienes permiso para ver esta fotografía.');
        }

        // Buscar primero en disco local (privado), luego en public (retrocompatibilidad)
        $disk = 'local';
        if (! Storage::disk('local')->exists($fotografia->ruta)) {
            if (Storage::disk('public')->exists($fotografia->ruta)) {
                $disk = 'public';
            } else {
                abort(404, 'Archivo de fotografía no encontrado.');
            }
        }

        return Storage::disk($disk)->response(
            $fotografia->ruta,
            $fotografia->nombre_original ?? basename($fotografia->ruta),
            [
                'Content-Type' => $fotografia->mime_type ?: 'image/jpeg',
                'Cache-Control' => $isPublicPublished ? 'public, max-age=86400' : 'private, no-cache, no-store, must-revalidate',
            ]
        );
    }
}

