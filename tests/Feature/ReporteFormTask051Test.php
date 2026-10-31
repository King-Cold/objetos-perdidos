<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteFormTask051Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Requisito 2 & 5: Visitante no autenticado es redirigido al login.
     */
    public function test_unauthenticated_visitor_cannot_access_hallazgo_form(): void
    {
        $response = $this->get(route('reportes.create'));

        $response->assertRedirect(route('login'));
    }

    /**
     * Requisito 2: Botón «Encontré un objeto» aparece en el apartado del cliente (Mis reportes).
     */
    public function test_registrar_hallazgo_button_appears_in_mis_reportes(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $response = $this->actingAs($user)->get(route('reportes.mis-reportes'));

        $response->assertStatus(200);
        $response->assertSee('Encontré un objeto');
        $response->assertSee(route('reportes.create'));
    }

    /**
     * Requisito 2: Botón «Encontré un objeto» aparece en el muro para usuario autenticado.
     */
    public function test_registrar_hallazgo_button_appears_in_muro_for_authenticated_client(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $response = $this->actingAs($user)->get(route('muro'));

        $response->assertStatus(200);
        $response->assertSee('Encontré un objeto');
        $response->assertSee(route('reportes.create'));
    }

    /**
     * Requisito 2: Botón «Encontré un objeto» aparece en el dashboard del cliente.
     */
    public function test_registrar_hallazgo_button_appears_in_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Encontré un objeto');
        $response->assertSee(route('reportes.create'));
    }

    /**
     * Requisito 2 & 3: Usuario autenticado accede al formulario y carga sin errores.
     */
    public function test_authenticated_user_can_access_hallazgo_form_successfully(): void
    {
        $user = User::factory()->create([
            'name' => 'María Pérez Gómez',
            'phone' => '9991234567',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $response = $this->actingAs($user)->get(route('reportes.create'));

        $response->assertStatus(200);
        $response->assertSee('Registrar Hallazgo');
    }

    /**
     * Requisito 3 & 4: El formulario contiene todos los campos obligatorios y opcionales con sus leyendas e indicaciones.
     */
    public function test_form_contains_all_required_and_optional_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Carlos Dzib Canul',
            'phone' => '9998765432',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $response = $this->actingAs($user)->get(route('reportes.create'));

        // Campo 1: Nombre del objeto encontrado
        $response->assertSee('Nombre del objeto encontrado');
        $response->assertSee('id="nombre"', false);

        // Campo 2: Descripción del objeto
        $response->assertSee('Descripción del objeto');
        $response->assertSee('id="descripcion"', false);

        // Campo 3: Fecha en que fue encontrado
        $response->assertSee('Fecha en que fue encontrado');
        $response->assertSee('id="fecha_hallazgo"', false);

        // Campo 4: Área o zona donde se encontró
        $response->assertSee('Área o zona donde se encontró');
        $response->assertSee('id="area_zona"', false);

        // Campo 5: Ubicación específica
        $response->assertSee('Ubicación específica');
        $response->assertSee('id="ubicacion_especifica"', false);

        // Campo 6: Hora en que fue encontrado (opcional)
        $response->assertSee('Hora en que fue encontrado');
        $response->assertSee('id="hora_hallazgo"', false);
        $response->assertSee('Opcional');

        // Campo 7: Fotografías (preparación visual)
        $response->assertSee('Fotografías del objeto');
        $response->assertSee('id="fotografia"', false);

        // Leyenda de campos obligatorios
        $response->assertSee('Campos obligatorios');
    }

    /**
     * Requisito 3: Los datos del usuario autenticado (nombre y teléfono) se muestran como solo lectura
     * y existe un enlace a su perfil.
     */
    public function test_user_contact_data_is_displayed_as_readonly_with_profile_link(): void
    {
        $user = User::factory()->create([
            'name' => 'Alejandro Morales Chan',
            'phone' => '9995551234',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $response = $this->actingAs($user)->get(route('reportes.create'));

        // Nombre de usuario en solo lectura
        $response->assertSee('Alejandro Morales Chan');
        $response->assertSee('id="user_name"', false);
        $response->assertSee('value="Alejandro Morales Chan"', false);

        // Teléfono en solo lectura
        $response->assertSee('9995551234');
        $response->assertSee('id="user_phone"', false);
        $response->assertSee('value="9995551234"', false);

        // Atributo readonly en los campos
        $response->assertSee('readonly', false);
        $response->assertSee('Solo lectura');

        // Enlace al perfil del usuario para actualizar datos
        $response->assertSee(route('profile.edit'));
        $response->assertSee('Actualizar datos en mi perfil');
    }

    /**
     * Requisito 4: El formulario incluye aviso de revisión administrativa y botones de Cancelar y Enviar a revisión.
     */
    public function test_form_includes_review_notice_and_action_buttons(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $response = $this->actingAs($user)->get(route('reportes.create'));

        // Aviso institucional de que no se publicará inmediatamente
        $response->assertSee('Aviso importante sobre la publicación de hallazgos');
        $response->assertSee('revisión y validación institucional');

        // Botón Cancelar con enlace de retorno
        $response->assertSee('Cancelar');
        $response->assertSee(route('reportes.mis-reportes'));

        // Botón Enviar reporte / Encontré un objeto
        $response->assertSee('Encontré un objeto');
        $response->assertSee('id="btn-enviar-revision"', false);
    }
}

