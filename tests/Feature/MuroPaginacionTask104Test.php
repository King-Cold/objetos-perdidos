<?php

namespace Tests\Feature;

use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MuroPaginacionTask104Test extends TestCase
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
            'descripcion' => 'Descripción completa del objeto de prueba para validación de paginación y filtros.',
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
     * Prueba 1: Los filtros permanecen al cambiar de página.
     */
    public function test_los_filtros_permanecen_al_cambiar_de_pagina(): void
    {
        // Crear 15 objetos que coincidan con "Termo" y fecha 2026-10-20
        for ($i = 1; $i <= 15; $i++) {
            $this->crearPublicacionAprobada([
                'folio' => sprintf('OBJ-20261102-%04d', $i),
                'nombre' => 'Termo Deportivo ' . $i,
                'descripcion' => 'Recipiente de acero inoxidable para agua',
                'fecha_hallazgo' => '2026-10-20',
                'fecha_primera_aprobacion' => Carbon::now(Publicacion::TIMEZONE)->subMinutes(20 - $i),
            ]);
        }

        // Consultar página 1 con filtros
        $responsePag1 = $this->get('/muro?buscar=Termo&fecha_hallazgo=2026-10-20');
        $responsePag1->assertOk();
        $responsePag1->assertSee('value="Termo"', false);
        $responsePag1->assertSee('value="2026-10-20"', false);
        // El enlace a la siguiente página debe contener los parámetros de búsqueda
        $responsePag1->assertSee('buscar=Termo');
        $responsePag1->assertSee('fecha_hallazgo=2026-10-20');
        $responsePag1->assertSee('page=2');

        // Consultar página 2
        $responsePag2 = $this->get('/muro?buscar=Termo&fecha_hallazgo=2026-10-20&page=2');
        $responsePag2->assertOk();
        $responsePag2->assertSee('value="Termo"', false);
        $responsePag2->assertSee('value="2026-10-20"', false);
        $responsePag2->assertSee('Mostrando página <span class="font-bold text-slate-800">2</span>', false);
    }

    /**
     * Prueba 2: Los filtros permanecen al recargar la página.
     */
    public function test_los_filtros_permanecen_al_recargar(): void
    {
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0001',
            'nombre' => 'Calculadora Científica',
            'fecha_hallazgo' => '2026-10-22',
        ]);

        $url = '/muro?buscar=Calculadora&fecha_hallazgo=2026-10-22';
        $res1 = $this->get($url);
        $res1->assertOk();
        $res1->assertSee('value="Calculadora"', false);
        $res1->assertSee('value="2026-10-22"', false);

        // Simular recarga con la misma URL GET
        $res2 = $this->get($url);
        $res2->assertOk();
        $res2->assertSee('value="Calculadora"', false);
        $res2->assertSee('value="2026-10-22"', false);
        $res2->assertSee('Calculadora Científica');
    }

    /**
     * Prueba 3: Los filtros permanecen al regresar del detalle.
     */
    public function test_los_filtros_permanecen_al_regresar_del_detalle(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0002',
            'nombre' => 'Audífonos Inalámbricos Bluetooth',
            'descripcion' => 'Estuche de carga color negro',
            'fecha_hallazgo' => '2026-10-18',
        ]);

        // Visitante entra al muro con filtros y hace clic a detalle
        $urlDetalle = route('publicaciones.show', [
            'id' => $pub->id,
            'buscar' => 'Audífonos',
            'fecha_hallazgo' => '2026-10-18',
        ]);

        $resDetalle = $this->get($urlDetalle);
        $resDetalle->assertOk();

        // El enlace «Volver al Muro» debe apuntar al muro con los filtros
        $resDetalle->assertSee('/muro?buscar=Aud%C3%ADfonos&amp;fecha_hallazgo=2026-10-18', false);

        // Al seguir ese enlace de retorno, se recuperan los filtros en el muro
        $resRetorno = $this->get('/muro?buscar=Audífonos&fecha_hallazgo=2026-10-18');
        $resRetorno->assertOk();
        $resRetorno->assertSee('value="Audífonos"', false);
        $resRetorno->assertSee('value="2026-10-18"', false);
        $resRetorno->assertSee('Audífonos Inalámbricos Bluetooth');
    }

    /**
     * Prueba 4: El retorno conserva la página de origen cuando corresponda.
     */
    public function test_el_retorno_conserva_la_pagina_de_origen_cuando_corresponda(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0003',
            'nombre' => 'Mochila Chenson',
        ]);

        $resDetalle = $this->get(route('publicaciones.show', [
            'id' => $pub->id,
            'buscar' => 'Mochila',
            'page' => '2',
        ]));

        $resDetalle->assertOk();
        $resDetalle->assertSee('/muro?buscar=Mochila&amp;page=2', false);
    }

    /**
     * Prueba 5: Una búsqueda nueva regresa a la primera página.
     */
    public function test_una_busqueda_nueva_regresa_a_la_primera_pagina(): void
    {
        // Enviar nueva búsqueda sin parámetro page
        $response = $this->get('/muro?buscar=NuevoTermino');
        $response->assertOk();

        $paginator = $response->viewData('publicaciones');
        $this->assertEquals(1, $paginator->currentPage());
    }

    /**
     * Prueba 6: Los resultados anteriores no se acumulan.
     */
    public function test_los_resultados_anteriores_no_se_acumulan(): void
    {
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0004',
            'nombre' => 'Termo Azul',
            'descripcion' => 'Recipiente azul metálico',
        ]);

        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0005',
            'nombre' => 'Sombrilla Amarilla',
            'descripcion' => 'Sombrilla de mano color amarillo',
        ]);

        // Primera búsqueda: Termo
        $res1 = $this->get('/muro?buscar=Termo');
        $res1->assertOk();
        $res1->assertSee('Termo Azul');
        $res1->assertDontSee('Sombrilla Amarilla');

        // Segunda búsqueda: Sombrilla (sustituye resultados, no acumula)
        $res2 = $this->get('/muro?buscar=Sombrilla');
        $res2->assertOk();
        $res2->assertSee('Sombrilla Amarilla');
        $res2->assertDontSee('Termo Azul');
    }

    /**
     * Prueba 7: Borrar solo el texto conserva la fecha.
     */
    public function test_borrar_solo_el_texto_conserva_la_fecha(): void
    {
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0006',
            'nombre' => 'Gorra TecNM',
            'fecha_hallazgo' => '2026-10-24',
        ]);

        $response = $this->get('/muro?buscar=&fecha_hallazgo=2026-10-24');
        $response->assertOk();
        $response->assertSee('Gorra TecNM');
        $response->assertSee('value="2026-10-24"', false);
    }

    /**
     * Prueba 8: Borrar solo la fecha conserva el texto.
     */
    public function test_borrar_solo_la_fecha_conserva_el_texto(): void
    {
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0007',
            'nombre' => 'Chamarra Universitaria',
            'fecha_hallazgo' => '2026-10-15',
        ]);

        $response = $this->get('/muro?buscar=Chamarra&fecha_hallazgo=');
        $response->assertOk();
        $response->assertSee('Chamarra Universitaria');
        $response->assertSee('value="Chamarra"', false);
    }

    /**
     * Prueba 9: «Limpiar filtros» elimina ambas condiciones.
     */
    public function test_limpiar_filtros_elimina_ambas_condiciones(): void
    {
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0008',
            'nombre' => 'Reloj Casio',
            'fecha_hallazgo' => '2026-10-10',
        ]);

        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0009',
            'nombre' => 'Lentes de Contacto',
            'fecha_hallazgo' => '2026-10-28',
        ]);

        // Simular acceso al muro limpio desde el botón «Limpiar filtros»
        $response = $this->get('/muro');
        $response->assertOk();
        $response->assertSee('Reloj Casio');
        $response->assertSee('Lentes de Contacto');
    }

    /**
     * Prueba 10: «Limpiar filtros» regresa a la página 1.
     */
    public function test_limpiar_filtros_regresa_a_la_pagina_1(): void
    {
        $response = $this->get('/muro');
        $response->assertOk();

        $paginator = $response->viewData('publicaciones');
        $this->assertEquals(1, $paginator->currentPage());
    }

    /**
     * Prueba 11: Se muestran como máximo 12 tarjetas por página.
     */
    public function test_se_muestran_como_maximo_12_tarjetas_por_pagina(): void
    {
        // Crear 15 publicaciones
        for ($i = 1; $i <= 15; $i++) {
            $this->crearPublicacionAprobada([
                'folio' => sprintf('OBJ-20261102-%04d', 100 + $i),
                'nombre' => 'Cuaderno Cuadriculado ' . $i,
            ]);
        }

        $response = $this->get('/muro');
        $response->assertOk();

        $paginator = $response->viewData('publicaciones');
        $this->assertCount(12, $paginator->items());
        $this->assertEquals(15, $paginator->total());
        $this->assertEquals(2, $paginator->lastPage());

        // Consultar página 2
        $resPag2 = $this->get('/muro?page=2');
        $resPag2->assertOk();
        $this->assertCount(3, $resPag2->viewData('publicaciones')->items());
    }

    /**
     * Prueba 12: Se conserva el orden cronológico institucional.
     */
    public function test_se_conserva_el_orden_cronologico(): void
    {
        $pubAntigua = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0010',
            'nombre' => 'Objeto Primeramente Aprobado',
            'fecha_primera_aprobacion' => Carbon::now(Publicacion::TIMEZONE)->subDays(3),
        ]);

        $pubReciente = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0011',
            'nombre' => 'Objeto Recientemente Aprobado',
            'fecha_primera_aprobacion' => Carbon::now(Publicacion::TIMEZONE)->subMinutes(5),
        ]);

        $response = $this->get('/muro');
        $response->assertOk();

        $items = $response->viewData('publicaciones')->items();
        $this->assertEquals($pubReciente->id, $items[0]->id);
        $this->assertEquals($pubAntigua->id, $items[1]->id);
    }

    /**
     * Prueba 13: No existen resultados duplicados.
     */
    public function test_no_existen_resultados_duplicados(): void
    {
        // Un objeto que contiene "Termo" tanto en su nombre como en su descripción
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0012',
            'nombre' => 'Termo Stanley',
            'descripcion' => 'Termo de acero inoxidable color plata',
        ]);

        $response = $this->get('/muro?buscar=Termo');
        $response->assertOk();

        $items = $response->viewData('publicaciones')->items();
        $this->assertCount(1, $items);
    }

    /**
     * Prueba 14: Las tarjetas mantienen los datos públicos de HU-08.
     */
    public function test_las_tarjetas_mantienen_los_datos_publicos_de_hu_08(): void
    {
        $autor = User::factory()->create([
            'name' => 'Profesor Carlos Mendez',
            'phone' => '9995554433',
        ]);

        $pub = $this->crearPublicacionAprobada([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261102-0013',
            'nombre' => 'Calculadora Texas Instruments',
            'descripcion' => 'Calculadora gráfica con tapa plástica negra.',
            'area_zona' => 'Edificio D - Posgrado',
            'ubicacion_especifica' => 'Mesa de laboratorio 2',
        ]);

        $response = $this->get('/muro');
        $response->assertOk();

        // Datos públicos permitidos
        $response->assertSee('Calculadora Texas Instruments');
        $response->assertSee('Calculadora gráfica con tapa plástica negra.');
        $response->assertSee('Ver más detalles');

        // Datos privados rigurosamente ausentes
        $response->assertDontSee('Profesor Carlos Mendez');
        $response->assertDontSee('9995554433');
        $response->assertDontSee('OBJ-20261102-0013');
        $response->assertDontSee('Edificio D - Posgrado');
        $response->assertDontSee('Mesa de laboratorio 2');
    }

    /**
     * Prueba 15: La búsqueda sin sesión no expone información privada.
     */
    public function test_la_busqueda_sin_sesion_no_expone_informacion_privada(): void
    {
        $autor = User::factory()->create([
            'name' => 'Ingeniero Fernando Lopez',
            'phone' => '9997776655',
        ]);

        $this->crearPublicacionAprobada([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261102-0014',
            'nombre' => 'Laptop HP Pavilion',
            'descripcion' => 'Laptop plateada de 15 pulgadas con cargador.',
        ]);

        $response = $this->get('/muro?buscar=Laptop');
        $response->assertOk();

        $response->assertDontSee('Ingeniero Fernando Lopez');
        $response->assertDontSee('9997776655');
        $response->assertDontSee('OBJ-20261102-0014');
    }

    /**
     * Prueba 16: «Ver más detalles» mantiene las restricciones de autenticación.
     */
    public function test_ver_mas_detalles_mantiene_las_restricciones_de_autenticacion(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0015',
            'nombre' => 'Mochila Nike Negra',
        ]);

        // Visitante sin sesión es redirigido a login al intentar acceder a detalles privados
        $responseGuest = $this->get(route('publicaciones.detalles-privados', $pub->id));
        $responseGuest->assertRedirect(route('login'));
        $this->assertEquals($pub->id, session('intended_publication_id'));

        // Usuario autenticado es redirigido al detalle
        $user = User::factory()->create();
        $responseAuth = $this->actingAs($user)->get(route('publicaciones.detalles-privados', $pub->id));
        $responseAuth->assertRedirect(route('publicaciones.show', $pub->id));
    }

    /**
     * Prueba 17: Los parámetros de retorno no permiten redirecciones externas.
     */
    public function test_los_parametros_de_retorno_no_permiten_redirecciones_externas(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0016',
            'nombre' => 'Carpeta con Documentos',
        ]);

        // Intento de inyección de URL externa maliciosa mediante return_to
        $resAtaque1 = $this->get(route('publicaciones.show', [
            'id' => $pub->id,
            'return_to' => 'https://sitio-malicioso.com/phishing',
        ]));
        $resAtaque1->assertOk();
        $resAtaque1->assertDontSee('https://sitio-malicioso.com/phishing');
        $resAtaque1->assertSee(route('muro'));

        // Intento de URL maliciosa de protocolo relativo //evil.com
        $resAtaque2 = $this->get(route('publicaciones.show', [
            'id' => $pub->id,
            'retorno' => '//evil.com',
        ]));
        $resAtaque2->assertOk();
        $resAtaque2->assertDontSee('//evil.com');
        $resAtaque2->assertSee(route('muro'));

        // Intento de javascript: URI
        $resAtaque3 = $this->get(route('publicaciones.show', [
            'id' => $pub->id,
            'retorno' => 'javascript:alert(1)',
        ]));
        $resAtaque3->assertOk();
        $resAtaque3->assertDontSee('javascript:alert(1)');
        $resAtaque3->assertSee(route('muro'));
    }

    /**
     * Prueba 18: Buscar, paginar y limpiar no modifican los reportes.
     */
    public function test_buscar_paginar_y_limpiar_no_modifican_los_reportes(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0017',
            'nombre' => 'Estuche de Dibujo Técnico',
            'fecha_hallazgo' => '2026-10-19',
        ]);

        $updatedAtOriginal = $pub->updated_at;

        // 1. Buscar
        $this->get('/muro?buscar=Estuche&fecha_hallazgo=2026-10-19');

        // 2. Paginar
        $this->get('/muro?page=1');

        // 3. Limpiar
        $this->get('/muro');

        $pub->refresh();
        $this->assertEquals($updatedAtOriginal->toDateTimeString(), $pub->updated_at->toDateTimeString());
        $this->assertEquals(Publicacion::ESTADO_APROBADA, $pub->estado);
        $this->assertTrue($pub->disponible);
    }
}

