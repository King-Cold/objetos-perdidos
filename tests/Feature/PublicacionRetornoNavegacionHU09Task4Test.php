<?php

namespace Tests\Feature;

use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicacionRetornoNavegacionHU09Task4Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper para crear publicación aprobada y disponible.
     */
    private function crearPublicacionAprobada(array $atributos = []): Publicacion
    {
        $autor = User::factory()->create([
            'phone' => '9991234567',
        ]);

        return Publicacion::factory()->create(array_merge([
            'user_id' => $autor->id,
            'nombre' => 'Objeto de prueba',
            'descripcion' => 'Descripción del objeto de prueba para navegación',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_hallazgo' => '2026-10-25',
            'area_zona' => 'Edificio A',
            'lugar_hallazgo' => 'Edificio A - Aula A1',
            'fecha_aprobacion' => now(),
            'fecha_primera_aprobacion' => now(),
            'fecha_vencimiento' => now()->addDays(15),
        ], $atributos));
    }

    /**
     * 1. Que «Volver al muro» regrese correctamente desde la vista de detalles.
     */
    public function test_volver_al_muro_regresa_correctamente_desde_el_detalle(): void
    {
        $pub = $this->crearPublicacionAprobada();
        $user = User::factory()->create();

        // 1.1 Usuario autenticado
        $resAuth = $this->actingAs($user)->get(route('publicaciones.show', $pub->id));
        $resAuth->assertOk();
        $resAuth->assertSee('Volver al muro');
        $resAuth->assertSee(route('muro'), false);

        // 1.2 Visitante sin sesión
        $resGuest = $this->get(route('publicaciones.show', $pub->id));
        $resGuest->assertOk();
        $resGuest->assertSee('Volver al muro');
        $resGuest->assertSee(route('muro'), false);
    }

    /**
     * 2. Que se conserve la página de origen.
     */
    public function test_volver_al_muro_conserva_pagina_de_origen(): void
    {
        $pub = $this->crearPublicacionAprobada();
        $user = User::factory()->create();

        $urlDetalleConPagina = route('publicaciones.show', [
            'id' => $pub->id,
            'page' => 3,
        ]);

        $response = $this->actingAs($user)->get($urlDetalleConPagina);
        $response->assertOk();
        $response->assertSee('/muro?page=3', false);
    }

    /**
     * 3. Que se mantengan los filtros por texto y fecha.
     */
    public function test_volver_al_muro_conserva_filtros_de_texto_y_fecha(): void
    {
        $pub = $this->crearPublicacionAprobada();
        $user = User::factory()->create();

        $urlConFiltros = route('publicaciones.show', [
            'id' => $pub->id,
            'buscar' => 'Mochila',
            'fecha_hallazgo' => '2026-10-25',
            'page' => 2,
        ]);

        $response = $this->actingAs($user)->get($urlConFiltros);
        $response->assertOk();
        $response->assertSee('/muro?buscar=Mochila&amp;fecha_hallazgo=2026-10-25&amp;page=2', false);
    }

    /**
     * 4. Que los valores de los filtros continúen visibles al regresar al muro.
     */
    public function test_valores_de_filtros_continuan_visibles_al_regresar_al_muro(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'nombre' => 'Calculadora Casio fx-991',
            'fecha_hallazgo' => '2026-10-25',
        ]);

        // Simular regreso al muro mediante la URL con parámetros de retorno
        $responseMuro = $this->get('/muro?buscar=Calculadora&fecha_hallazgo=2026-10-25');
        $responseMuro->assertOk();
        $responseMuro->assertSee('value="Calculadora"', false);
        $responseMuro->assertSee('value="2026-10-25"', false);
        $responseMuro->assertSee('Calculadora Casio fx-991');
    }

    /**
     * 5. Que la navegación funcione desde una tarjeta del muro conservando contexto.
     */
    public function test_navegacion_desde_tarjeta_del_muro_incluye_contexto_de_retorno(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'nombre' => 'Lentes de sol Ray-Ban',
            'fecha_hallazgo' => '2026-10-25',
        ]);

        // Visitar el muro con filtros de búsqueda
        $response = $this->get('/muro?buscar=Lentes&fecha_hallazgo=2026-10-25');
        $response->assertOk();

        // El enlace «Ver más detalles» en la tarjeta debe incluir los parámetros para el retorno
        $response->assertSee(
            '/publicaciones/' . $pub->id . '/detalles-privados?buscar=Lentes&amp;fecha_hallazgo=2026-10-25',
            false
        );
    }

    /**
     * 6. Que el retorno seguro no permita redirecciones externas ni esquemas inseguros.
     */
    public function test_retorno_seguro_rechaza_redirecciones_externas_o_inseguras(): void
    {
        $pub = $this->crearPublicacionAprobada();
        $user = User::factory()->create();

        $destinosInseguros = [
            'https://sitio-malicioso.com/phishing',
            'http://external-domain.org/hack',
            '//sitio-malicioso.com/test',
            '\\\\sitio-malicioso.com\\test',
            'javascript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            '/admin/moderacion',
        ];

        foreach ($destinosInseguros as $inseguro) {
            $response = $this->actingAs($user)->get(route('publicaciones.show', [
                'id' => $pub->id,
                'retorno' => $inseguro,
            ]));

            $response->assertOk();
            // Debe caer en el retorno seguro institucional por defecto (/muro)
            $response->assertSee(route('muro'), false);
            $response->assertDontSee($inseguro, false);
        }
    }

    /**
     * 7. Que se manejen publicaciones inexistentes o retiradas sin errores y con aviso.
     */
    public function test_manejo_de_publicacion_inexistente_o_retirada_redirige_al_muro(): void
    {
        $user = User::factory()->create();

        // 7.1 Publicación inexistente
        $resInexistente = $this->actingAs($user)->get('/publicaciones/99999');
        $resInexistente->assertRedirect(route('muro'));
        $resInexistente->assertSessionHas('warning', 'Esta publicación no está disponible para consulta');

        // 7.2 Publicación retirada / no disponible
        $pubRetirada = $this->crearPublicacionAprobada([
            'disponible' => false,
            'estado' => 'Entregada',
        ]);

        $resRetirada = $this->actingAs($user)->get(route('publicaciones.show', $pubRetirada->id));
        $resRetirada->assertRedirect(route('muro'));
        $resRetirada->assertSessionHas('warning', 'Esta publicación no está disponible para consulta');
    }

    /**
     * 8. Que una sesión cerrada o expirada no permita consultar información privada.
     */
    public function test_sesion_cerrada_o_expirada_no_muestra_informacion_privada(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'nombre' => 'Mochila Nike',
        ]);

        // Sin sesión iniciada (o después de cerrar sesión / expiración)
        $response = $this->get(route('publicaciones.show', $pub->id));
        $response->assertOk();

        // Información pública presente
        $response->assertSee('Mochila Nike');
        $response->assertSee('Volver al muro');
        $response->assertSee('Iniciar sesión');

        // Información privada estrictamente protegida
        $response->assertSee('Información de contacto protegida');
        $response->assertDontSee('Teléfono: 9991234567');
        $response->assertDontSee('id="boton-llamar"', false);
    }

    /**
     * 9. Que los visitantes continúen utilizando el flujo de autenticación y conserven contexto.
     */
    public function test_visitante_conserva_destino_y_contexto_tras_autenticarse(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'nombre' => 'Tablet Samsung Galaxy',
        ]);
        $user = User::factory()->create();

        // 1. Visitante hace clic en Ver más detalles desde un muro filtrado
        $resDetallesPrivados = $this->get(route('publicaciones.detalles-privados', [
            'id' => $pub->id,
            'buscar' => 'Tablet',
            'page' => 2,
        ]));

        // Es dirigido a iniciar sesión
        $resDetallesPrivados->assertRedirect(route('login'));
        $this->assertEquals($pub->id, session('intended_publication_id'));

        // 2. El usuario inicia sesión
        $resLogin = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Redirige al detalle de la publicación
        $resLogin->assertRedirect(route('publicaciones.show', $pub->id));

        // 3. Al consultar el detalle autenticado, el botón Volver al muro conserva los filtros del origen
        $resShow = $this->actingAs($user)->get(route('publicaciones.show', $pub->id));
        $resShow->assertOk();
        $resShow->assertSee('/muro?buscar=Tablet&amp;page=2', false);
    }

    /**
     * 10. Que la navegación y retorno sean de solo lectura sin alterar la publicación.
     */
    public function test_navegacion_y_retorno_no_alteran_publicacion(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'nombre' => 'Chamarra de Mezclilla',
        ]);
        $user = User::factory()->create();

        $estadoOriginal = $pub->estado;
        $disponibleOriginal = $pub->disponible;
        $vencimientoOriginal = $pub->fecha_vencimiento->format('Y-m-d H:i:s');

        // Navegar al detalle con filtros
        $res = $this->actingAs($user)->get(route('publicaciones.show', [
            'id' => $pub->id,
            'buscar' => 'Chamarra',
            'page' => 1,
        ]));
        $res->assertOk();

        // Regresar al muro
        $resMuro = $this->actingAs($user)->get('/muro?buscar=Chamarra&page=1');
        $resMuro->assertOk();

        $pub->refresh();
        $this->assertSame($estadoOriginal, $pub->estado);
        $this->assertSame($disponibleOriginal, $pub->disponible);
        $this->assertSame($vencimientoOriginal, $pub->fecha_vencimiento->format('Y-m-d H:i:s'));
    }
}

