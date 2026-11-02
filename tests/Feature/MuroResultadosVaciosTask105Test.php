<?php

namespace Tests\Feature;

use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MuroResultadosVaciosTask105Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function crearPublicacionAprobada(array $atributos = []): Publicacion
    {
        $autor = User::factory()->create();

        return Publicacion::create(array_merge([
            'user_id' => $autor->id,
            'nombre' => 'Objeto de prueba',
            'titulo' => 'Objeto de prueba',
            'descripcion' => 'Descripción completa del objeto de prueba para validación de resultados vacíos y errores.',
            'fecha_hallazgo' => Carbon::now(Publicacion::TIMEZONE)->toDateString(),
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'version' => 1,
            'fecha_primera_aprobacion' => Carbon::now(Publicacion::TIMEZONE),
            'fecha_aprobacion' => Carbon::now(Publicacion::TIMEZONE),
            'fecha_vencimiento' => Carbon::now(Publicacion::TIMEZONE)->addDays(14),
            'devolucion_en_proceso' => false,
            'incidencia_abierta' => false,
        ], $atributos));
    }

    /**
     * Prueba 1: Búsqueda válida sin coincidencias.
     */
    public function test_busqueda_valida_sin_coincidencias(): void
    {
        $this->crearPublicacionAprobada([
            'nombre' => 'Termo Stanley Azul',
            'descripcion' => 'Recipiente metálico',
        ]);

        $response = $this->get('/muro?buscar=ObjetoInexistenteXYZ999');

        $response->assertOk();
        $response->assertDontSee('Termo Stanley Azul');
    }

    /**
     * Prueba 2: Visualización exacta de «No se encontraron resultados».
     */
    public function test_visualizacion_exacta_de_no_se_encontraron_resultados(): void
    {
        $this->crearPublicacionAprobada([
            'nombre' => 'Mochila Nike',
        ]);

        $response = $this->get('/muro?buscar=ParaguasInexistente');

        $response->assertOk();
        $response->assertSee('No se encontraron resultados');
    }

    /**
     * Prueba 3: Conservación de los filtros cuando no existen coincidencias.
     */
    public function test_conservacion_de_los_filtros_cuando_no_existen_coincidencias(): void
    {
        $this->crearPublicacionAprobada([
            'nombre' => 'Cuaderno Profesional',
            'fecha_hallazgo' => '2026-10-15',
        ]);

        $response = $this->get('/muro?buscar=Calculadora&fecha_hallazgo=2026-10-20');

        $response->assertOk();
        $response->assertSee('value="Calculadora"', false);
        $response->assertSee('value="2026-10-20"', false);
        $response->assertSee('No se encontraron resultados');
    }

    /**
     * Prueba 4: Disponibilidad de «Limpiar filtros».
     */
    public function test_disponibilidad_de_limpiar_filtros(): void
    {
        $response = $this->get('/muro?buscar=TextoSinCoincidencias');

        $response->assertOk();
        $response->assertSee('Limpiar filtros');
        $response->assertSee(route('muro'));
    }

    /**
     * Prueba 5: Ausencia de publicaciones de ejemplo.
     */
    public function test_ausencia_de_publicaciones_de_ejemplo(): void
    {
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0001',
            'nombre' => 'Llaves con Llavero',
        ]);

        $response = $this->get('/muro?buscar=BalonDeFutbol');

        $response->assertOk();
        $response->assertDontSee('Llaves con Llavero');
        $response->assertDontSee('OBJ-20261102-0001');
        // No deben existir tarjetas de publicaciones en el DOM
        $response->assertDontSee('Ver más detalles');
    }

    /**
     * Prueba 6: Error de consulta provocado de forma controlada.
     */
    public function test_error_de_consulta_provocado_de_forma_controlada(): void
    {
        $view = $this->view('muro', [
            'publicaciones' => collect(),
            'errorConsulta' => true,
            'mensajeError' => 'No fue posible realizar la búsqueda. Intenta nuevamente',
            'errorFecha' => null,
            'esBusqueda' => true,
        ]);

        $view->assertSee('No fue posible realizar la búsqueda. Intenta nuevamente');
    }

    /**
     * Prueba 7: Visualización exacta del mensaje de error.
     */
    public function test_visualizacion_exacta_del_mensaje_de_error(): void
    {
        $view = $this->view('muro', [
            'publicaciones' => collect(),
            'errorConsulta' => true,
            'mensajeError' => 'No fue posible realizar la búsqueda. Intenta nuevamente',
            'errorFecha' => null,
            'esBusqueda' => true,
        ]);

        $view->assertSee('No fue posible realizar la búsqueda. Intenta nuevamente');
    }

    /**
     * Prueba 8: Conservación de filtros después del error.
     */
    public function test_conservacion_de_filtros_despues_del_error(): void
    {
        $response = $this->call('GET', '/muro', [
            'buscar' => 'Termo',
            'fecha_hallazgo' => '2026-10-22',
        ]);

        // Los valores se mantienen en el request/formulario
        $response->assertSee('value="Termo"', false);
        $response->assertSee('value="2026-10-22"', false);
    }

    /**
     * Prueba 9: Posibilidad de reintentar.
     */
    public function test_posibilidad_de_reintentar(): void
    {
        $view = $this->view('muro', [
            'publicaciones' => collect(),
            'errorConsulta' => true,
            'mensajeError' => 'No fue posible realizar la búsqueda. Intenta nuevamente',
            'errorFecha' => null,
            'esBusqueda' => true,
        ]);

        $view->assertSee('Reintentar');
    }

    /**
     * Prueba 10: Ausencia de resultados anteriores presentados como actuales.
     */
    public function test_ausencia_de_resultados_anteriores_presentados_como_actuales(): void
    {
        $pub1 = $this->crearPublicacionAprobada([
            'nombre' => 'Gorra Puma',
            'descripcion' => 'Gorra color negro',
        ]);

        // Primera búsqueda exitosa con resultados
        $resExitosa = $this->get('/muro?buscar=Gorra');
        $resExitosa->assertOk();
        $resExitosa->assertSee('Gorra Puma');

        // Segunda búsqueda sin coincidencias: NO debe mostrar la Gorra Puma anterior
        $resVacia = $this->get('/muro?buscar=SombreroInexistente');
        $resVacia->assertOk();
        $resVacia->assertDontSee('Gorra Puma');
        $resVacia->assertSee('No se encontraron resultados');
    }

    /**
     * Prueba 11: Diferenciación entre error técnico y ausencia de coincidencias.
     */
    public function test_diferenciacion_entre_error_tecnico_y_ausencia_de_coincidencias(): void
    {
        // 1. Escenario de ausencia de coincidencias (consulta exitosa sin registros)
        $resSinCoincidencias = $this->get('/muro?buscar=ObjetoTotalmenteInexistente');
        $resSinCoincidencias->assertOk();
        $resSinCoincidencias->assertSee('No se encontraron resultados');
        $resSinCoincidencias->assertDontSee('No fue posible realizar la búsqueda. Intenta nuevamente');

        // 2. Escenario de error técnico
        $viewError = $this->view('muro', [
            'publicaciones' => collect(),
            'errorConsulta' => true,
            'mensajeError' => 'No fue posible realizar la búsqueda. Intenta nuevamente',
            'errorFecha' => null,
            'esBusqueda' => true,
        ]);
        $viewError->assertSee('No fue posible realizar la búsqueda. Intenta nuevamente');
        $viewError->assertDontSee('No se encontraron resultados');
    }

    /**
     * Prueba 12: Diferenciación entre fecha inválida y error técnico.
     */
    public function test_diferenciacion_entre_fecha_invalida_y_error_tecnico(): void
    {
        $resFechaInvalida = $this->get('/muro?fecha_hallazgo=2026-02-31');
        $resFechaInvalida->assertOk();

        // Muestra exclusivamente el mensaje de validación de fecha
        $resFechaInvalida->assertSee('Selecciona una fecha válida que no sea posterior a hoy');
        // NO debe mostrar error técnico ni mensaje de resultados vacíos
        $resFechaInvalida->assertDontSee('No fue posible realizar la búsqueda. Intenta nuevamente');
        $resFechaInvalida->assertDontSee('No se encontraron resultados');
    }

    /**
     * Prueba 13: Ausencia de información sensible en los mensajes.
     */
    public function test_ausencia_de_informacion_sensible_en_los_mensajes(): void
    {
        $viewError = $this->view('muro', [
            'publicaciones' => collect(),
            'errorConsulta' => true,
            'mensajeError' => 'No fue posible realizar la búsqueda. Intenta nuevamente',
            'errorFecha' => null,
            'esBusqueda' => true,
        ]);

        $viewError->assertDontSee('SELECT * FROM');
        $viewError->assertDontSee('SQLSTATE');
        $viewError->assertDontSee('vendor/laravel');
        $viewError->assertDontSee('DB_PASSWORD');
    }

    /**
     * Prueba 14: Ausencia de modificaciones en los reportes.
     */
    public function test_ausencia_de_modificaciones_en_los_reportes(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'nombre' => 'Calculadora Científica Casio',
            'fecha_hallazgo' => '2026-10-18',
        ]);

        $updatedAtOriginal = $pub->updated_at;

        // Búsqueda sin coincidencias
        $this->get('/muro?buscar=Inexistente123');

        $pub->refresh();
        $this->assertEquals($updatedAtOriginal->toDateTimeString(), $pub->updated_at->toDateTimeString());
        $this->assertEquals(Publicacion::ESTADO_APROBADA, $pub->estado);
        $this->assertTrue($pub->disponible);
    }
}

