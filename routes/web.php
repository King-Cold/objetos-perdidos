<?php

use App\Http\Controllers\Admin\ModeracionReporteController;
use App\Http\Controllers\FotografiaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReporteController;
use App\Models\Publicacion;
use Carbon\Carbon;
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
                'errorFecha' => null,
            ]);
        }

        $errorFecha = null;
        $fechaValida = null;

        if ($request->filled('fecha_hallazgo')) {
            $rawFecha = trim((string) $request->input('fecha_hallazgo'));
            $hoyMerida = Carbon::now(Publicacion::TIMEZONE)->startOfDay();

            $parsed = null;
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawFecha)) {
                [$y, $m, $d] = explode('-', $rawFecha);
                if (checkdate((int) $m, (int) $d, (int) $y)) {
                    $parsed = Carbon::createFromFormat('Y-m-d', $rawFecha, Publicacion::TIMEZONE)->startOfDay();
                }
            }

            if (! $parsed || $parsed->greaterThan($hoyMerida)) {
                $errorFecha = 'Selecciona una fecha válida que no sea posterior a hoy';
            } else {
                $fechaValida = $parsed->toDateString();
            }
        }

        $query = Publicacion::disponiblesMuro()
            ->with('fotografias')
            ->ordenCronologicoMuro();

        // 1. Filtro de texto (HU-10 / Tarea 10.2)
        if ($request->has('buscar')) {
            $query->buscarTexto($request->input('buscar'));
        }

        // 2. Filtro de fecha de hallazgo (HU-10 / Tarea 10.3)
        if ($fechaValida !== null) {
            $query->filtrarFechaHallazgo($fechaValida);
        }

        $publicaciones = $query->paginate(12)->withQueryString();

        // Guardar contexto de retorno en sesión si hay filtros o página activa (HU-10 / Tarea 10.4)
        if ($request->hasAny(['buscar', 'fecha_hallazgo', 'page'])) {
            $request->session()->put('muro_return_url', $request->fullUrl());
        } else {
            $request->session()->forget('muro_return_url');
        }

        // Determinar si el usuario ejecutó una búsqueda activa (HU-10 / Tarea 10.5)
        $esBusqueda = (trim((string) $request->input('buscar', '')) !== '')
            || ($request->filled('fecha_hallazgo') && $fechaValida !== null);

        return view('muro', [
            'publicaciones' => $publicaciones,
            'errorConsulta' => false,
            'errorFecha' => $errorFecha,
            'esBusqueda' => $esBusqueda,
        ]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Error al consultar publicaciones del muro', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
        ]);

        $esBusqueda = (trim((string) $request->input('buscar', '')) !== '')
            || $request->filled('fecha_hallazgo');

        $mensajeError = $esBusqueda
            ? 'No fue posible realizar la búsqueda. Intenta nuevamente'
            : 'No pudimos cargar las publicaciones en este momento debido a un problema técnico o de conexión.';

        return view('muro', [
            'publicaciones' => collect(),
            'errorConsulta' => true,
            'mensajeError' => $mensajeError,
            'errorFecha' => null,
            'esBusqueda' => $esBusqueda,
        ]);
    }
};

// Función de sanitización y retorno seguro al muro (HU-10 / Tarea 10.4)
$sanitizarRetornoMuro = function (Request $request): string {
    // 1. Parámetro explícito de retorno (retorno, return_to, return_url, ref, volver)
    $candidato = $request->query('retorno')
        ?? $request->query('return_to')
        ?? $request->query('return_url')
        ?? $request->query('ref')
        ?? $request->query('volver');

    if ($candidato !== null && $candidato !== '') {
        $candidato = trim((string) $candidato);

        // Bloquear esquemas maliciosos, protocolo relativo // y javascript:
        if (! str_starts_with($candidato, '//') && ! preg_match('/^javascript:/i', $candidato)) {
            $parsed = parse_url($candidato);
            // Si incluye host, debe coincidir estrictamente con el host institucional local
            if (! isset($parsed['host']) || $parsed['host'] === $request->getHost()) {
                $path = $parsed['path'] ?? '/';
                if ($path === '/' || $path === '/muro' || str_ends_with($path, '/muro')) {
                    $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
                    return url($path) . $query;
                }
            }
        }
    }

    // 2. Parámetros directos de filtro en la petición actual
    $paramsFiltro = array_filter(
        $request->only(['buscar', 'fecha_hallazgo', 'page']),
        fn($val) => ! is_null($val) && $val !== ''
    );

    if (! empty($paramsFiltro)) {
        return route('muro', $paramsFiltro);
    }

    // 3. Contexto registrado en sesión durante la navegación en el muro
    if ($request->session()->has('muro_return_url')) {
        $sesionUrl = (string) $request->session()->get('muro_return_url');
        $parsed = parse_url($sesionUrl);
        if (! isset($parsed['host']) || $parsed['host'] === $request->getHost()) {
            $path = $parsed['path'] ?? '/';
            if ($path === '/' || $path === '/muro' || str_ends_with($path, '/muro')) {
                return $sesionUrl;
            }
        }
    }

    return route('muro');
};

Route::get('/', $muroHandler);
Route::get('/muro', $muroHandler)->name('muro');

// Detalle de publicación y destino previo (CA-08, CA-28, CA-29, HU-10 / Tarea 10.4)
Route::get('/publicaciones/{id}', function (Request $request, $id) use ($sanitizarRetornoMuro) {
    $publicacion = Publicacion::with(['user', 'fotografias'])->find($id);

    if (! $publicacion || ! $publicacion->isDisponible()) {
        return redirect()->route('muro')->with('warning', 'Esta publicación no está disponible para consulta');
    }

    // Si es visitante no autenticado, registrar referencia para retorno seguro (CA-28)
    if (! Auth::check()) {
        session()->put('intended_publication_id', $publicacion->id);
    }

    $urlVolver = $sanitizarRetornoMuro($request);

    return view('publicaciones.show', compact('publicacion', 'urlVolver'));
})->name('publicaciones.show');

// Solicitud de autenticación para apartados privados desde el muro público (HU Muro / HU-02, HU-10 / Tarea 10.4)
Route::get('/publicaciones/{id}/detalles-privados', function (Request $request, $id) use ($sanitizarRetornoMuro) {
    $publicacion = Publicacion::find($id);

    if (! $publicacion || ! $publicacion->isDisponible()) {
        return redirect()->route('muro')->with('warning', 'Esta publicación no está disponible para consulta');
    }

    session()->put('intended_publication_id', $publicacion->id);

    $urlVolver = $sanitizarRetornoMuro($request);
    if ($urlVolver !== route('muro')) {
        session()->put('muro_return_url', $urlVolver);
    }

    if (! Auth::check()) {
        return redirect()->guest(route('login'));
    }

    return redirect()->to(route('publicaciones.show', array_merge(['id' => $publicacion->id], $request->query())));
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
