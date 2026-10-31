<?php

use App\Http\Controllers\FotografiaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReporteController;
use App\Models\Publicacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Muro público institucional (CA-08, CA-19, CA-26)
Route::get('/', function () {
    $publicaciones = \Illuminate\Support\Facades\Schema::hasTable('publicaciones')
        ? Publicacion::where('disponible', true)->where('estado', 'Aprobada')->latest()->get()
        : collect();

    return view('muro', compact('publicaciones'));
});

Route::get('/muro', function () {
    $publicaciones = \Illuminate\Support\Facades\Schema::hasTable('publicaciones')
        ? Publicacion::where('disponible', true)->where('estado', 'Aprobada')->latest()->get()
        : collect();

    return view('muro', compact('publicaciones'));
})->name('muro');

// Detalle de publicación y destino previo (CA-08, CA-28, CA-29)
Route::get('/publicaciones/{id}', function ($id) {
    $publicacion = Publicacion::find($id);

    if (! $publicacion || ! $publicacion->isDisponible()) {
        return redirect()->route('muro')->with('warning', 'Esta publicación no está disponible para consulta');
    }

    // Si es visitante no autenticado, registrar referencia para retorno seguro (CA-28)
    if (! Auth::check()) {
        session()->put('intended_publication_id', $publicacion->id);
    }

    return view('publicaciones.show', compact('publicacion'));
})->name('publicaciones.show');

// Panel Administrativo protegido por rol (CA-08, CA-09, CA-12, CA-15)
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/panel', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');

    Route::get('/publicaciones', function () {
        $publicaciones = Publicacion::latest()->get();
        return view('admin.dashboard', compact('publicaciones'));
    })->name('admin.publicaciones');

    // Aprobación de publicaciones (CA-13)
    Route::post('/publicaciones/{id}/aprobar', function ($id) {
        $publicacion = Publicacion::findOrFail($id);
        $publicacion->update(['estado' => 'Aprobada', 'disponible' => true]);
        return redirect()->back()->with('status', 'Publicación aprobada correctamente');
    })->name('publicaciones.aprobar');

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

    // Mis Reportes de Usuario General (CA-13, CA-16, Tarea 05.5)
    Route::get('/reportes/mis-reportes', function () {
        $reportes = Publicacion::with('fotografias')->where('user_id', Auth::id())->latest()->get();
        return view('reportes.mis-reportes', compact('reportes'));
    })->name('reportes.mis-reportes');

    // Acceso privado y controlado a fotografías de reportes (Tarea 05.5)
    Route::get('/fotografias/{fotografia}', [FotografiaController::class, 'show'])->name('fotografias.show');

    // Edición con comprobación estricta de autoría (CA-13)
    Route::get('/reportes/{id}/editar', function ($id) {
        $publicacion = Publicacion::findOrFail($id);

        if ($publicacion->user_id !== Auth::id() && ! Auth::user()->isAdmin()) {
            abort(403, 'No tienes permiso para realizar esta acción');
        }

        return view('reportes.edit', compact('publicacion'));
    })->name('reportes.edit');

    Route::put('/reportes/{id}', function (Request $request, $id) {
        $publicacion = Publicacion::findOrFail($id);

        if ($publicacion->user_id !== Auth::id() && ! Auth::user()->isAdmin()) {
            abort(403, 'No tienes permiso para realizar esta acción');
        }

        $validated = $request->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['required', 'string'],
        ]);

        $publicacion->update($validated);

        return redirect()->route('reportes.mis-reportes')->with('status', 'Reporte actualizado correctamente');
    })->name('reportes.update');

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
