<?php

namespace Tests\Feature\Auth;

use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTask024Test extends TestCase
{
    use RefreshDatabase;

    /**
     * CA-19: Cierre de sesión invalida sesión actual, retira datos privados y muestra mensaje.
     */
    public function test_logout_invalidates_session_and_shows_success_message(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('muro'));
        $response->assertSessionHas('status', 'Sesión cerrada correctamente');
    }

    /**
     * CA-20: Cierre de sesión no elimina cuentas ni reportes en base de datos.
     */
    public function test_logout_does_not_delete_user_or_reports(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->create([
            'user_id' => $user->id,
            'titulo' => 'Reporte intacto',
        ]);

        $this->actingAs($user)->post('/logout');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('publicaciones', ['id' => $reporte->id, 'titulo' => 'Reporte intacto']);
    }

    /**
     * CA-21 y CA-24: La sesión permanece vigente a los 9 minutos y 59 segundos (599 segundos).
     */
    public function test_session_remains_active_at_9_minutes_and_59_seconds(): void
    {
        $user = User::factory()->create();

        // Iniciar sesión
        $this->actingAs($user);
        session(['last_activity_time' => now()->timestamp]);

        // Simular transcurso de 9 minutos y 59 segundos (599s)
        $this->travel(9)->minutes();
        $this->travel(59)->seconds();

        // Petición a ruta protegida
        $response = $this->get(route('reportes.mis-reportes'));

        $this->assertAuthenticated();
        $response->assertStatus(200);
    }

    /**
     * CA-21, CA-24, CA-25 y CA-26: Sesión vencida exactamente a los 10 minutos (600 segundos).
     */
    public function test_session_expires_exactly_at_10_minutes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        session(['last_activity_time' => now()->timestamp]);

        // Simular transcurso exacto de 10 minutos (600s)
        $this->travel(10)->minutes();

        // Consulta a ruta protegida
        $response = $this->get(route('reportes.mis-reportes'));

        // Debe cerrar la sesión y redirigir al login informando la expiración
        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('warning', 'Tu sesión expiró por inactividad. Inicia sesión nuevamente para continuar');
    }

    /**
     * CA-22 y CA-24: Una interacción válida antes de los 10 minutos reinicia el plazo completo.
     */
    public function test_valid_interaction_before_limit_resets_inactivity_timer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        session(['last_activity_time' => now()->timestamp]);

        // Transcurren 6 minutos
        $this->travel(6)->minutes();

        // Interacción válida autenticada del usuario (abrir mis reportes)
        $interactionResponse = $this->get(route('reportes.mis-reportes'));
        $interactionResponse->assertStatus(200);

        // Transcurren otros 6 minutos desde la última interacción
        // (En total 12 min desde el inicio, pero solo 6 min desde la interacción válida)
        $this->travel(6)->minutes();

        // Debe seguir activa porque se reinició el contador completo
        $followResponse = $this->get(route('reportes.mis-reportes'));
        $this->assertAuthenticated();
        $followResponse->assertStatus(200);
    }

    /**
     * CA-23: Las solicitudes automáticas (polling/keepalive) NO reinician el plazo de inactividad.
     */
    public function test_automated_requests_do_not_reset_inactivity_timer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        session(['last_activity_time' => now()->timestamp]);

        // Transcurren 6 minutos
        $this->travel(6)->minutes();

        // Solicitud con encabezado de petición automática
        $this->withHeaders(['X-Automated-Request' => 'true'])
            ->get(route('reportes.mis-reportes'));

        // Transcurren otros 5 minutos (total transcurrido: 11 minutos sin interacción humana)
        $this->travel(5)->minutes();

        // Como la solicitud automática no debió reiniciar el plazo, la sesión debe estar vencida
        $response = $this->get(route('reportes.mis-reportes'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('warning', 'Tu sesión expiró por inactividad. Inicia sesión nuevamente para continuar');
    }

    /**
     * CA-22: Las solicitudes no autorizadas (403) NO renuevan el plazo de inactividad.
     */
    public function test_unauthorized_requests_do_not_renew_inactivity_timer(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $this->actingAs($user);
        session(['last_activity_time' => now()->timestamp]);

        // Transcurren 6 minutos
        $this->travel(6)->minutes();

        // Intento de acceso a ruta no autorizada (devuelve 403)
        $resForbidden = $this->get('/admin/panel');
        $resForbidden->assertStatus(403);

        // Transcurren otros 5 minutos (11 min totales)
        $this->travel(5)->minutes();

        // Debe vencer porque el 403 no renovó la actividad
        $response = $this->get(route('reportes.mis-reportes'));
        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    /**
     * CA-25: Formulario abierto antes del vencimiento y enviado después se rechaza sin guardar cambios.
     */
    public function test_operation_sent_after_expiration_is_rejected_without_saving_changes(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->create([
            'user_id' => $user->id,
            'titulo' => 'Título Inalterado',
            'descripcion' => 'Descripción inalterada.',
        ]);

        $this->actingAs($user);
        session(['last_activity_time' => now()->timestamp]);

        // Usuario abre el formulario... pero se ausenta 15 minutos antes de enviar
        $this->travel(15)->minutes();

        // Intento de envío del formulario
        $response = $this->put(route('reportes.update', $reporte->id), [
            'titulo' => 'Intento Tras Vencimiento',
            'descripcion' => 'No debe guardarse',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('warning', 'Tu sesión expiró por inactividad. Inicia sesión nuevamente para continuar');

        // Comprobación de que la BD quedó exactamente como estaba (CA-25)
        $this->assertEquals('Título Inalterado', $reporte->fresh()->titulo);
    }

    /**
     * CA-27: Encabezados Cache-Control impiden recuperar datos privados mediante botón «Atrás».
     */
    public function test_authenticated_responses_include_no_store_cache_headers(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('reportes.mis-reportes'));

        $response->assertStatus(200);

        // CA-27: Verificar directivas HTTP anti-caché
        $response->assertHeader('Cache-Control');
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertEquals('no-cache', $response->headers->get('Pragma'));
    }

    /**
     * CA-26: La consulta pública del muro puede continuar después de expirar la sesión.
     */
    public function test_public_muro_remains_accessible_after_session_expiration(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        session(['last_activity_time' => now()->timestamp]);

        // Vencer la sesión
        $this->travel(11)->minutes();

        // Consulta pública al muro
        $response = $this->get(route('muro'));

        // El muro carga correctamente como invitado (200)
        $response->assertStatus(200);
        $this->assertGuest();
    }
}

