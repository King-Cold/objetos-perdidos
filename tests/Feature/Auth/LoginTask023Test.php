<?php

namespace Tests\Feature\Auth;

use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTask023Test extends TestCase
{
    use RefreshDatabase;

    /**
     * CA-16 y CA-18: Menú de navegación muestra únicamente opciones autorizadas para Usuario General.
     */
    public function test_navigation_menu_shows_only_general_user_options(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $response = $this->actingAs($user)->get(route('reportes.mis-reportes'));

        $response->assertStatus(200);

        // Opciones autorizadas para Usuario General (CA-16)
        $response->assertSee('Muro de Objetos');
        $response->assertSee('Mis Reportes');
        $response->assertSee('Cerrar sesión'); // CA-18

        // No debe mostrar opciones exclusivas de administrador
        $response->assertDontSee('Panel Administrativo');
        $response->assertDontSee('Gestión de Objetos');
    }

    /**
     * CA-16 y CA-18: Menú de navegación muestra únicamente opciones autorizadas para Administrador/bibliotecario.
     */
    public function test_navigation_menu_shows_only_admin_options(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);

        // Opciones autorizadas para Administrador/bibliotecario (CA-16)
        $response->assertSee('Panel Administrativo');
        $response->assertSee('Muro Público');
        $response->assertSee('Gestión de Objetos');
        $response->assertSee('Cerrar sesión'); // CA-18

        // No debe mostrar la vista individual de "Mis Reportes" de usuario general
        $response->assertDontSee('Mis Reportes');
    }

    /**
     * CA-15: Cuenta autenticada sin permisos recibe 403 «No tienes permiso para realizar esta acción».
     */
    public function test_authenticated_user_accessing_admin_panel_is_aborted_403_with_exact_message(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $response = $this->actingAs($user)->get('/admin/panel');

        $response->assertStatus(403);
        $response->assertSee('No tienes permiso para realizar esta acción');
    }

    /**
     * CA-13: Usuario general solo puede editar sus propios reportes; no los de otro usuario.
     */
    public function test_general_user_cannot_edit_another_users_report(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $reporte = Publicacion::factory()->create([
            'user_id' => $owner->id,
            'titulo' => 'Mochila negra original',
            'descripcion' => 'Descripción original del propietario.',
        ]);

        // Intento de edición GET
        $responseGet = $this->actingAs($otherUser)->get(route('reportes.edit', $reporte->id));
        $responseGet->assertStatus(403);
        $responseGet->assertSee('No tienes permiso para realizar esta acción');

        // Intento de modificación PUT
        $responsePut = $this->actingAs($otherUser)->put(route('reportes.update', $reporte->id), [
            'titulo' => 'Título alterado maliciosamente',
            'descripcion' => 'Descripción alterada',
        ]);
        $responsePut->assertStatus(403);

        // Verificar que los datos no cambiaron en BD
        $this->assertDatabaseHas('publicaciones', [
            'id' => $reporte->id,
            'titulo' => 'Mochila negra original',
        ]);
    }

    /**
     * CA-13: Autor original o administrador sí pueden editar el reporte propio.
     */
    public function test_author_can_edit_own_report(): void
    {
        $owner = User::factory()->create();

        $reporte = Publicacion::factory()->create([
            'user_id' => $owner->id,
            'nombre' => 'Audífonos Bluetooth',
            'titulo' => 'Audífonos Bluetooth',
            'descripcion' => 'Audífonos perdidos en biblioteca con longitud reglamentaria mayor a cincuenta caracteres.',
            'fecha_captura' => Carbon::now(Publicacion::TIMEZONE),
            'fecha_hallazgo' => Carbon::now(Publicacion::TIMEZONE)->format('Y-m-d'),
            'area_zona' => 'Biblioteca',
            'ubicacion_especifica' => 'Mesa 4',
        ]);

        Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/test_login023.jpg',
            'nombre_original' => 'test_login023.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
            'es_principal' => true,
        ]);

        $response = $this->actingAs($owner)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Audífonos Bluetooth Sony',
            'titulo' => 'Audífonos Bluetooth Sony',
            'descripcion' => 'Audífonos negros encontrados en mesa 4 con longitud adecuada mayor a cincuenta caracteres.',
            'fecha_hallazgo' => Carbon::now(Publicacion::TIMEZONE)->format('Y-m-d'),
            'area_zona' => 'Biblioteca',
            'ubicacion_especifica' => 'Mesa 4',
        ]);

        $response->assertRedirect(route('reportes.mis-reportes'));
        $this->assertDatabaseHas('publicaciones', [
            'id' => $reporte->id,
            'titulo' => 'Audífonos Bluetooth Sony',
        ]);
    }

    /**
     * CA-13: Usuario general no puede aprobar publicaciones.
     */
    public function test_general_user_cannot_approve_publications(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->pendiente()->create();

        $response = $this->actingAs($user)->post(route('publicaciones.aprobar', $reporte->id));

        $response->assertStatus(403);
        $response->assertSee('No tienes permiso para realizar esta acción');
        $this->assertEquals('Pendiente de aprobación', $reporte->fresh()->estado);
    }

    /**
     * CA-13: Usuario general no puede validar devoluciones.
     */
    public function test_general_user_cannot_validate_returns(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->create([
            'estado' => 'Aprobada',
            'disponible' => true,
        ]);

        $response = $this->actingAs($user)->post(route('devoluciones.validar', $reporte->id));

        $response->assertStatus(403);
        $response->assertSee('No tienes permiso para realizar esta acción');
        $this->assertEquals('Aprobada', $reporte->fresh()->estado);
    }

    /**
     * CA-13: Usuario general no puede confirmar recepciones en biblioteca.
     */
    public function test_general_user_cannot_confirm_library_receptions(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->create();

        $response = $this->actingAs($user)->post(route('recepciones.confirmar', $reporte->id));

        $response->assertStatus(403);
        $response->assertSee('No tienes permiso para realizar esta acción');
    }

    /**
     * CA-13: Administrador sí puede aprobar publicaciones y validar devoluciones.
     */
    public function test_admin_can_approve_publications_and_validate_returns(): void
    {
        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::factory()->pendiente()->create();

        // Aprobar publicación
        $resAprobar = $this->actingAs($admin)->post(route('publicaciones.aprobar', $reporte->id));
        $resAprobar->assertSessionHas('status', 'Publicación aprobada correctamente');
        $this->assertEquals('Aprobada', $reporte->fresh()->estado);

        // Validar devolución
        $resDevolucion = $this->actingAs($admin)->post(route('devoluciones.validar', $reporte->id));
        $resDevolucion->assertSessionHas('status', 'Devolución verificada correctamente');
        $this->assertEquals('Devuelto', $reporte->fresh()->estado);
    }

    /**
     * CA-14: Visitante sin sesión que intente consultar privado es dirigido a login;
     * y si intenta modificar, la solicitud es rechazada sin guardar cambios.
     */
    public function test_unauthenticated_visitor_cannot_access_or_modify_private_functions(): void
    {
        $reporte = Publicacion::factory()->create([
            'titulo' => 'Lapicera intacta',
        ]);

        // Intento de consulta GET privada -> redirigido a login
        $resGet = $this->get('/admin/panel');
        $resGet->assertRedirect(route('login'));

        // Intento de modificación PUT privada sin sesión -> rechazado, redirigido a login
        $resPut = $this->put(route('reportes.update', $reporte->id), [
            'titulo' => 'Lapicera modificada sin login',
            'descripcion' => 'Intento ilegal',
        ]);
        $resPut->assertRedirect(route('login'));

        // Comprobar que en BD no se aplicó ningún cambio
        $this->assertEquals('Lapicera intacta', $reporte->fresh()->titulo);
    }

    /**
     * CA-11: Servidor comprueba permisos por sesión/BD; manipulación de roles en URL no concede acceso.
     */
    public function test_tampered_role_in_request_query_or_header_does_not_grant_permissions(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        // Intento enviando query ?role=admin o headers
        $response = $this->actingAs($user)->get('/admin/panel?role=admin&is_admin=true');

        $response->assertStatus(403);
        $response->assertSee('No tienes permiso para realizar esta acción');
    }
}

