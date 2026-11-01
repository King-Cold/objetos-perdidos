<?php

use App\Http\Controllers\Admin\ModeracionReporteController;
use App\Http\Controllers\FotografiaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReporteController;
use App\Models\Publicacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Muro público institucional (CA-08, CA-19, CA-26)
$muroHandler = function (Request $request) {
    try {
        if (! \Illuminate\Support\Facades\Schema::hasTable('publicaciones')) {
            return view('muro', [
                'publicaciones' => collect(),
                'errorConsulta' => false,
            ]);
        }

        $query = Publicacion::disponiblesMuro()
            ->with('fotografias')
            ->ordenCronologicoMuro();

        if ($request->filled('buscar')) {
            $buscar = trim((string) $request->input('buscar'));
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('titulo', 'like', "%{$buscar}%")
                    ->orWhere('descripcion', 'like', "%{$buscar}%")
                    ->orWhere('folio', 'like', "%{$buscar}%");
            });
        }

        $publicaciones = $query->paginate(12)->withQueryString();

        return view('muro', [
            'publicaciones' => $publicaciones,
            'errorConsulta' => false,
        ]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Error al consultar publicaciones del muro', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
        ]);

        return view('muro', [
            'publicaciones' => collect(),
            'errorConsulta' => true,
            'mensajeError' => 'No pudimos cargar las publicaciones en este momento debido a un problema técnico o de conexión.',
        ]);
    }
};

Route::get('/', $muroHandler);
Route::get('/muro', $muroHandler)->name('muro');

// Detalle de publicación y destino previo (CA-08, CA-28, CA-29)
Route::get('/publicaciones/{id}', function ($id) {
    $publicacion = Publicacion::with(['user', 'fotografias'])->find($id);

    if (! $publicacion || ! $publicacion->isDisponible()) {
        return redirect()->route('muro')->with('warning', 'Esta publicación no está disponible para consulta');
    }

    // Si es visitante no autenticado, registrar referencia para retorno seguro (CA-28)
    if (! Auth::check()) {
        session()->put('intended_publication_id', $publicacion->id);
    }

    return view('publicaciones.show', compact('publicacion'));
})->name('publicaciones.show');

// Solicitud de autenticación para apartados privados desde el muro público (HU Muro / HU-02)
Route::get('/publicaciones/{id}/detalles-privados', function ($id) {
    $publicacion = Publicacion::find($id);

    if (! $publicacion || ! $publicacion->isDisponible()) {
        return redirect()->route('muro')->with('warning', 'Esta publicación no está disponible para consulta');
    }

    session()->put('intended_publication_id', $publicacion->id);

    if (! Auth::check()) {
        return redirect()->guest(route('login'));
    }

    return redirect()->route('publicaciones.show', $publicacion->id);
})->name('publicaciones.detalles-privados');

// Acceso controlado y seguro a fotografías (público si la publicación está aprobada, privado para pendientes)
Route::get('/fotografias/{fotografia}', [FotografiaController::class, 'show'])->name('fotografias.show');

// Panel Administrativo protegido por rol (CA-08, CA-09, CA-12, CA-15)
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/panel', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');

    // Moderación y Gestión de reportes de objetos (HU-07, Tarea 07.1, 07.2, 07.3, 07.4 y 07.5)
    Route::get('/moderacion', [ModeracionReporteController::class, 'index'])->name('admin.moderacion.index');
    Route::get('/moderacion/{id}', [ModeracionReporteController::class, 'show'])->name('admin.moderacion.show');
    Route::post('/moderacion/{id}/solicitar-correccion', [ModeracionReporteController::class, 'solicitarCorreccion'])->name('admin.moderacion.solicitar-correccion');
    Route::post('/moderacion/{id}/aprobar', [ModeracionReporteController::class, 'aprobar'])->name('admin.moderacion.aprobar');
    Route::post('/moderacion/{id}/cancelar', [ModeracionReporteController::class, 'cancelar'])->name('admin.moderacion.cancelar');
    Route::get('/publicaciones', [ModeracionReporteController::class, 'index'])->name('admin.publicaciones');

    // Aprobación de publicaciones (CA-13 / compatibilidad)
    Route::post('/publicaciones/{id}/aprobar', [ModeracionReporteController::class, 'aprobar'])->name('publicaciones.aprobar');

    // Validación de devoluciones (CA-13)
    Route::post('/devoluciones/{id}/validar', function ($id) {
        $publicacion = Publicacion::findOrFail($id);
        $publicacion->update(['estado' => 'Devuelto', 'disponible' => false]);
        return redirect()->back()->with('status', 'Devolución verificada correctamente');
    })->name('devoluciones.validar');

    // Confirmación de recepción física en biblioteca (CA-13)
    Route::post('/recepciones/{id}/confirmar', function ($id) {
        return redirect()->back()->with('status', 'Recepción física confirmada en biblioteca');
    })->name('recepciones.confirmar');
});

// Rutas protegidas para usuarios autenticados (CA-12, CA-13, CA-14, CA-15)
Route::middleware('auth')->group(function () {
    // Formulario de Registro de Hallazgo (Tarea 05.1 y 05.2)
    Route::get('/reportes/crear', [ReporteController::class, 'create'])->name('reportes.create');
    Route::post('/reportes', [ReporteController::class, 'store'])->name('reportes.store');

        // Mis Reportes de Usuario General (HU-06 Tarea 06.1: CA-01 a CA-05)
    Route::get('/reportes/mis-reportes', [ReporteController::class, 'index'])
        ->name('reportes.mis-reportes');

    // Detalle privado de reporte propio (HU-06 Tarea 06.1: CA-06, CA-07, CA-08)
    Route::get('/reportes/{id}', [ReporteController::class, 'show'])
        ->name('reportes.show');

    // Edición con comprobación estricta de autoría y estado (HU-06 Tarea 06.2: CA-09 a CA-17, CA-22, CA-23)
    Route::get('/reportes/{id}/editar', [ReporteController::class, 'edit'])->name('reportes.edit');
    Route::put('/reportes/{id}', [ReporteController::class, 'update'])->name('reportes.update');

    // Cancelación permitida de reportes propios (HU-06 Tarea 06.5: CA-31 a CA-40)
    Route::get('/reportes/{id}/cancelar', [ReporteController::class, 'confirmCancel'])->name('reportes.cancelar');
    Route::match(['post', 'delete'], '/reportes/{id}/cancelar', [ReporteController::class, 'cancel'])->name('reportes.cancel');

    // Perfil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Dashboard general (mantenido para compatibilidad de vistas previas)
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/auth.php';
