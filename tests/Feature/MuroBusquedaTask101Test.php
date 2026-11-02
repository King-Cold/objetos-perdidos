<?php

namespace Tests\Feature;

use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MuroBusquedaTask101Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Prueba 1: Los controles aparecen para visitantes sin sesión.
     */
    public function test_controles_aparecen_para_visitantes(): void
    {
        $response = $this->get(route('muro'));

        $response->assertOk();
        $response->assertSee('role="search"', false);
        $response->assertSee('name="buscar"', false);
        $response->assertSee('placeholder="Buscar por nombre o descripción"', false);
        $response->assertSee('Fecha del hallazgo');
        $response->assertSee('name="fecha_hallazgo"', false);
        $response->assertSee('Buscar');
        $response->assertSee('Limpiar filtros');
        $response->assertSee(route('muro'));
    }

    /**
     * Prueba 2: Los controles aparecen para usuarios autenticados.
     */
    public function test_controles_aparecen_para_usuarios_autenticados(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('muro'));

        $response->assertOk();
        $response->assertSee('role="search"', false);
        $response->assertSee('name="buscar"', false);
        $response->assertSee('placeholder="Buscar por nombre o descripción"', false);
        $response->assertSee('Fecha del hallazgo');
        $response->assertSee('name="fecha_hallazgo"', false);
        $response->assertSee('Buscar');
        $response->assertSee('Limpiar filtros');
    }

    /**
     * Prueba 3: No se exige iniciar sesión para consultar el muro con parámetros de búsqueda.
     */
    public function test_no_se_exige_iniciar_sesion(): void
    {
        $response = $this->get('/muro?buscar=mochila&fecha_hallazgo=2026-10-30');

        $response->assertOk();
        $this->assertGuest();
        $response->assertDontSee('Iniciar sesión para buscar');
    }

    /**
     * Prueba 4: El botón «Buscar» envía los parámetros esperados mediante GET y los conserva en la vista.
     */
    public function test_boton_buscar_envia_parametros_esperados(): void
    {
        $response = $this->get('/muro?buscar=Calculadora+Casio&fecha_hallazgo=2026-10-25');

        $response->assertOk();
        $response->assertSee('value="Calculadora Casio"', false);
        $response->assertSee('value="2026-10-25"', false);
    }

    /**
     * Prueba 5: Presionar Enter utiliza el mismo formulario semántico y produce una petición equivalente.
     */
    public function test_presionar_enter_utiliza_el_mismo_formulario(): void
    {
        $response = $this->get(route('muro'));

        $response->assertOk();
        // El formulario cuenta con method GET y action hacia la ruta del muro, con un botón type="submit"
        $response->assertSee('method="GET"', false);
        $response->assertSee('action="' . route('muro') . '"', false);
        $response->assertSee('type="submit"', false);
    }

    /**
     * Prueba 6: La opción «Limpiar filtros» dirige a la ruta del muro sin criterios en la URL.
     */
    public function test_opcion_limpiar_filtros_elimina_ambos_criterios(): void
    {
        // Al consultar con filtros activos
        $response = $this->get('/muro?buscar=Audifonos&fecha_hallazgo=2026-10-28');
        $response->assertOk();

        // El enlace «Limpiar filtros» apunta a la ruta limpia del muro
        $response->assertSee('href="' . route('muro') . '"', false);
        $response->assertSee('Limpiar filtros');

        // Al acceder al enlace limpio del muro, los inputs no contienen criterios
        $responseLimpio = $this->get(route('muro'));
        $responseLimpio->assertOk();
        $responseLimpio->assertDontSee('value="Audifonos"', false);
        $responseLimpio->assertDontSee('value="2026-10-28"', false);
    }

    /**
     * Prueba 7: Limpiar filtros regresa a la primera página.
     */
    public function test_limpiar_filtros_regresa_a_la_primera_pagina(): void
    {
        // En una consulta paginada con criterios
        $response = $this->get('/muro?buscar=mochila&page=2');
        $response->assertOk();

        // El botón Limpiar filtros conduce a route('muro') sin parámetro de página
        $response->assertSee('href="' . route('muro') . '"', false);
    }

    /**
     * Prueba 8: Los controles son accesibles mediante teclado y cuentan con etiquetas semánticas.
     */
    public function test_controles_son_accesibles_mediante_teclado(): void
    {
        $response = $this->get(route('muro'));

        $response->assertOk();
        // Etiquetas asociadas a sus respectivos inputs
        $response->assertSee('for="filtro_buscar"', false);
        $response->assertSee('id="filtro_buscar"', false);
        $response->assertSee('for="filtro_fecha_hallazgo"', false);
        $response->assertSee('id="filtro_fecha_hallazgo"', false);

        // Áreas táctiles y enfoque accesible
        $response->assertSee('focus:ring-tecnm-wine', false);
        $response->assertSee('min-h-[44px]', false);
    }

    /**
     * Prueba 9: La interfaz cuenta con clases para distribución responsiva (escritorio y móviles).
     */
    public function test_interfaz_cuenta_con_clases_responsivas(): void
    {
        $response = $this->get(route('muro'));

        $response->assertOk();
        // Columna en móviles y fila en pantallas grandes
        $response->assertSee('flex-col', false);
        $response->assertSee('lg:flex-row', false);
        $response->assertSee('lg:w-56', false);
        $response->assertSee('flex-1', false);
    }

    /**
     * Prueba 10: El formulario de búsqueda es de solo lectura y no modifica los reportes existentes.
     */
    public function test_formulario_no_modifica_los_reportes(): void
    {
        $autor = User::factory()->create();
        $fechaVencimiento = Carbon::now(Publicacion::TIMEZONE)->addDays(14);

        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261101-0099',
            'nombre' => 'Laptop HP Pavillion',
            'descripcion' => 'Laptop gris encontrada en laboratorio B con cargador incluido',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'version' => 1,
            'fecha_vencimiento' => $fechaVencimiento,
        ]);

        // Simular ejecución de búsqueda
        $response = $this->get('/muro?buscar=Laptop&fecha_hallazgo=2026-11-01');
        $response->assertOk();

        // El registro en base de datos permanece intacto
        $reporteFresco = $reporte->fresh();
        $this->assertEquals(Publicacion::ESTADO_APROBADA, $reporteFresco->estado);
        $this->assertTrue((bool) $reporteFresco->disponible);
        $this->assertEquals(1, $reporteFresco->version);
        $this->assertEquals($autor->id, $reporteFresco->user_id);
        $this->assertEquals('OBJ-20261101-0099', $reporteFresco->folio);
    }
}

