<?php

use App\Http\Controllers\Admin\DevolucionBibliotecaController;
use App\Http\Controllers\Admin\IncidenciaController;
use App\Http\Controllers\Admin\InventarioController;
use App\Http\Controllers\Admin\ModeracionReporteController;
use App\Http\Controllers\Admin\RecepcionController;
use App\Http\Controllers\Admin\VerificacionDevolucionController;
use App\Http\Controllers\AvisoController;
use App\Http\Controllers\DevolucionController;
use App\Http\Controllers\FotografiaController;
use App\Http\Controllers\HistorialController;
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

// Función de sanitización y retorno seguro al muro (HU-10 / Tarea 10.4, HU-09 / Tarea 09.4)
$sanitizarRetornoMuro = function (Request $request): string {
    // 1. Parámetro explícito de retorno (retorno, return_to, return_url, ref, volver)
    $candidato = $request->query('retorno')
        ?? $request->query('return_to')
        ?? $request->query('return_url')
        ?? $request->query('ref')
        ?? $request->query('volver');

    if ($candidato !== null && $candidato !== '') {
        $candidato = trim((string) $candidato);

        // Bloquear esquemas maliciosos, protocolo relativo //, barra inversa \ y javascript/data/vbscript:
        if (! str_starts_with($candidato, '//') 
            && ! str_starts_with($candidato, '\\') 
            && ! str_starts_with($candidato, '/\\')
            && ! preg_match('/^(javascript|data|vbscript):/i', $candidato)) {
            
            $parsed = parse_url($candidato);
            // Si incluye host, debe coincidir estrictamente con el host institucional local
            if (! isset($parsed['host']) || $parsed['host'] === $request->getHost()) {
                $path = rtrim($parsed['path'] ?? '/', '/');
                $path = ($path === '') ? '/' : $path;
                if ($path === '/' || $path === '/muro') {
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
            $path = rtrim($parsed['path'] ?? '/', '/');
            $path = ($path === '') ? '/' : $path;
            if ($path === '/' || $path === '/muro') {
                return $sesionUrl;
            }
        }
    }

    return route('muro');
};

Route::get('/', $muroHandler);
Route::get('/muro', $muroHandler)->name('muro');

// Detalle de publicación y destino previo (CA-08, CA-28, CA-29, HU-10 / Tarea 10.4, HU-09 / Tarea 09.4)
Route::get('/publicaciones/{id}', function (Request $request, $id) use ($sanitizarRetornoMuro) {
    $publicacion = Publicacion::with(['user', 'fotografias'])->find($id);

    if (! $publicacion || ! $publicacion->isDisponible()) {
        $urlVolver = $sanitizarRetornoMuro($request);
        return redirect()->to($urlVolver)->with('warning', 'Esta publicación no está disponible para consulta');
    }

    // Si es visitante no autenticado, registrar referencia para retorno seguro (CA-28)
    if (! Auth::check()) {
        session()->put('intended_publication_id', $publicacion->id);
    }

    $urlVolver = $sanitizarRetornoMuro($request);

    return view('publicaciones.show', compact('publicacion', 'urlVolver'));
})->name('publicaciones.show');

// Solicitud de autenticación para apartados privados desde el muro público (HU Muro / HU-02, HU-10 / Tarea 10.4, HU-09 / Tarea 09.4)
Route::get('/publicaciones/{id}/detalles-privados', function (Request $request, $id) use ($sanitizarRetornoMuro) {
    $publicacion = Publicacion::find($id);

    if (! $publicacion || ! $publicacion->isDisponible()) {
        $urlVolver = $sanitizarRetornoMuro($request);
        return redirect()->to($urlVolver)->with('warning', 'Esta publicación no está disponible para consulta');
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

    // Verificación y Decisión de Devoluciones Físicas (HU-12 / Tareas 1 y 2)
    Route::get('/devoluciones', [VerificacionDevolucionController::class, 'index'])->name('admin.devoluciones.index');
    Route::get('/devoluciones/{id}', [VerificacionDevolucionController::class, 'show'])->name('admin.devoluciones.show');
    Route::post('/devoluciones/{id}/aprobar', [VerificacionDevolucionController::class, 'aprobar'])->name('admin.devoluciones.aprobar');
    Route::post('/devoluciones/{id}/solicitar-correccion', [VerificacionDevolucionController::class, 'solicitarCorreccion'])->name('admin.devoluciones.solicitar-correccion');

    // Confirmación de recepción física en biblioteca (CA-13)
    Route::post('/recepciones/{id}/confirmar', function ($id) {
        return redirect()->back()->with('status', 'Recepción física confirmada en biblioteca');
    })->name('recepciones.confirmar');

    // HU-16: Resguardo de objetos no reclamados en biblioteca (Tarea 16.1, 16.2 y 16.3)
    Route::get('/reportes/{id}/recepcion', [RecepcionController::class, 'create'])->name('admin.recepciones.create');
    Route::post('/reportes/{id}/recepcion', [RecepcionController::class, 'store'])->name('admin.recepciones.store');
    Route::get('/inventario', [InventarioController::class, 'index'])->name('admin.inventario.index');
    Route::get('/inventario/{id}', [InventarioController::class, 'show'])->name('admin.inventario.show');
    Route::match(['put', 'patch', 'post'], '/inventario/{id}/ubicacion', [InventarioController::class, 'updateUbicacion'])->name('admin.inventario.update-ubicacion');

    // Devolución desde biblioteca (HU: Devolución desde biblioteca / CA-01)
    Route::get('/reportes/{id}/devolucion-biblioteca', [DevolucionBibliotecaController::class, 'create'])->name('admin.devoluciones-biblioteca.create');
    Route::post('/reportes/{id}/devolucion-biblioteca', [DevolucionBibliotecaController::class, 'store'])->name('admin.devoluciones-biblioteca.store');

    // Historial del objeto desde apartados de gestión (HU-13 / Tarea 1)
    Route::get('/reportes/{id}/historial', [HistorialController::class, 'show'])->name('admin.reportes.historial');

    // HU-17: Gestión de incidencias y dudas de entrega (Subtarea 1, Subtarea 2 y Subtarea 3)
    Route::get('/incidencias', [IncidenciaController::class, 'index'])->name('admin.incidencias.index');
    Route::get('/incidencias/crear/{publicacionId?}', [IncidenciaController::class, 'create'])->name('admin.incidencias.create');
    Route::post('/incidencias/{publicacionId?}', [IncidenciaController::class, 'store'])->name('admin.incidencias.store');
    Route::get('/incidencias/{id}', [IncidenciaController::class, 'show'])->name('admin.incidencias.show');
    Route::post('/incidencias/{id}/observaciones', [IncidenciaController::class, 'storeObservacion'])->name('admin.incidencias.observaciones.store');
    Route::post('/incidencias/{id}/revision', [IncidenciaController::class, 'gestionarRevision'])->name('admin.incidencias.revision');
    Route::post('/incidencias/{id}/confirmar-entrega-correcta', [IncidenciaController::class, 'confirmarEntregaCorrecta'])->name('admin.incidencias.confirmar-entrega-correcta');
    Route::post('/incidencias/{id}/solicitar-correccion', [IncidenciaController::class, 'solicitarCorreccion'])->name('admin.incidencias.solicitar-correccion');
    Route::post('/incidencias/{id}/confirmar-entrega-incorrecta', [IncidenciaController::class, 'confirmarEntregaIncorrecta'])->name('admin.incidencias.confirmar-entrega-incorrecta');
    Route::post('/incidencias/{id}/recuperacion-fisica', [IncidenciaController::class, 'registrarRecuperacionFisica'])->name('admin.incidencias.recuperacion-fisica');
    Route::post('/incidencias/{id}/cerrar', [IncidenciaController::class, 'cerrarIncidencia'])->name('admin.incidencias.cerrar');
    Route::post('/incidencias/{id}/canalizar', [IncidenciaController::class, 'canalizarSinResolver'])->name('admin.incidencias.canalizar');
    Route::post('/reportes/{id}/incidencias', [IncidenciaController::class, 'store'])->name('admin.reportes.incidencias.store');
    Route::post('/devoluciones/{id}/incidencias', [IncidenciaController::class, 'storeFromDevolucion'])->name('admin.devoluciones.incidencias.store');
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

    // Historial privado del objeto (HU-13 / Tarea 1: Acceso y encabezado privado)
    Route::get('/reportes/{id}/historial', [HistorialController::class, 'show'])
        ->name('reportes.historial');

    // Edición con comprobación estricta de autoría y estado (HU-06 Tarea 06.2: CA-09 a CA-17, CA-22, CA-23)
    Route::get('/reportes/{id}/editar', [ReporteController::class, 'edit'])->name('reportes.edit');
    Route::put('/reportes/{id}', [ReporteController::class, 'update'])->name('reportes.update');

    // Cancelación permitida de reportes propios (HU-06 Tarea 06.5: CA-31 a CA-40)
    Route::get('/reportes/{id}/cancelar', [ReporteController::class, 'confirmCancel'])->name('reportes.cancelar');
    Route::match(['post', 'delete'], '/reportes/{id}/cancelar', [ReporteController::class, 'cancel'])->name('reportes.cancel');

    // Validación y solicitud de eliminación permanente de reportes propios cancelados (HU-11 / Subtarea 2)
    Route::match(['post', 'delete'], '/reportes/{id}', [ReporteController::class, 'destroy'])->name('reportes.destroy');
    Route::match(['post', 'delete'], '/reportes/{id}/eliminar', [ReporteController::class, 'destroy'])->name('reportes.eliminar');

    // HU-11: Registro y seguimiento de devolución física (Tareas 1 a 4)
    Route::get('/reportes/{id}/devolucion', [DevolucionController::class, 'create'])->name('devoluciones.create');
    Route::post('/reportes/{id}/devolucion', [DevolucionController::class, 'store'])->name('devoluciones.store');
    Route::get('/devoluciones/{id}', [DevolucionController::class, 'show'])->name('devoluciones.show');
    Route::get('/devoluciones/{id}/evidencia', [DevolucionController::class, 'evidencia'])->name('devoluciones.evidencia');

    // HU-12 Tarea 3: Corrección y reenvío de devolución física
    Route::get('/devoluciones/{id}/editar', [DevolucionController::class, 'edit'])->name('devoluciones.edit');
    Route::get('/devoluciones/{id}/corregir', [DevolucionController::class, 'edit']);
    Route::match(['put', 'patch', 'post'], '/devoluciones/{id}', [DevolucionController::class, 'update'])->name('devoluciones.update');
    Route::match(['put', 'patch', 'post'], '/devoluciones/{id}/reenviar', [DevolucionController::class, 'update']);

    // Perfil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Apartado privado de avisos de procesos (HU: Avisos / Task 1 y Task 2)
    Route::get('/avisos', [AvisoController::class, 'index'])->name('avisos.index');
    Route::match(['patch', 'post'], '/avisos/marcar-todos-leidos', [AvisoController::class, 'marcarTodosLeidos'])->name('avisos.marcar-todos-leidos');
    Route::match(['patch', 'post'], '/avisos/leer-todos', [AvisoController::class, 'marcarTodosLeidos'])->name('avisos.leer-todos');
    Route::match(['patch', 'post'], '/avisos/{id}/marcar-leido', [AvisoController::class, 'marcarLeido'])->name('avisos.marcar-leido');
    Route::match(['patch', 'post'], '/avisos/{id}/leer', [AvisoController::class, 'marcarLeido'])->name('avisos.leer');
    Route::get('/avisos/{id}/ir', [AvisoController::class, 'ir'])->name('avisos.ir');
    Route::get('/avisos/{id}', [AvisoController::class, 'ir'])->name('avisos.show');
});

// Dashboard general (mantenido para compatibilidad de vistas previas)
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/auth.php';
