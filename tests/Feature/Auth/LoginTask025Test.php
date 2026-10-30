<?php

namespace Tests\Feature\Auth;

use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTask025Test extends TestCase
{
    use RefreshDatabase;

    /**
     * CA-08 y CA-28: Redirige a la publicación previa disponible conservando query params intactos.
     */
    public function test_login_redirects_to_available_intended_publication_preserving_query_params(): void
    {
        $user = User::factory()->create();
        $pub = Publicacion::factory()->create([
            'estado' => 'Aprobada',
            'disponible' => true,
            'titulo' => 'Mochila negra olvidada',
        ]);

        $intendedUrl = route('publicaciones.show', $pub->id) . '?filtro=recientes&origen=notificacion';

        $response = $this->withSession(['url.intended' => $intendedUrl])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticated();
        $response->assertRedirect($intendedUrl);
    }

    /**
     * CA-08 y CA-28: Redirige utilizando el session key intended_publication_id.
     */
    public function test_login_redirects_to_intended_publication_id(): void
    {
        $user = User::factory()->create();
        $pub = Publicacion::factory()->create([
            'estado' => 'Aprobada',
            'disponible' => true,
        ]);

        $response = $this->withSession(['intended_publication_id' => $pub->id])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('publicaciones.show', $pub->id));
        $this->assertFalse(session()->has('intended_publication_id'));
    }

    /**
     * CA-29: Si la publicación previa ya no existe (404), redirige al muro con mensaje preventivo.
     */
    public function test_login_redirects_to_muro_when_intended_publication_does_not_exist(): void
    {
        $user = User::factory()->create();
        $missingUrl = url('/publicaciones/99999');

        $response = $this->withSession(['url.intended' => $missingUrl])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('muro'));
        $response->assertSessionHas('warning', 'Esta publicación no está disponible para consulta');
    }

    /**
     * CA-29: Si la publicación fue retirada o ya no está disponible, redirige al muro con mensaje preventivo.
     */
    public function test_login_redirects_to_muro_when_intended_publication_is_not_disponible(): void
    {
        $user = User::factory()->create();
        $pub = Publicacion::factory()->create([
            'estado' => 'Entregada',
            'disponible' => false,
        ]);

        $intendedUrl = route('publicaciones.show', $pub->id);

        $response = $this->withSession(['url.intended' => $intendedUrl])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('muro'));
        $response->assertSessionHas('warning', 'Esta publicación no está disponible para consulta');
    }

    /**
     * CA-30: Intento de redirección abierta (Open Redirect) hacia dominio externo es neutralizado.
     */
    public function test_open_redirect_with_external_domain_is_blocked_and_redirects_to_default_route(): void
    {
        $user = User::factory()->create();

        $response = $this->withSession(['url.intended' => 'https://malicious-phishing-site.com/steal-data'])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('muro'));
    }

    /**
     * CA-30: Intento de redirección abierta con URLs relativas de protocolo (//evil.com) es neutralizado.
     */
    public function test_open_redirect_with_protocol_relative_url_is_blocked(): void
    {
        $user = User::factory()->create();

        $response = $this->withSession(['url.intended' => '//attacker-site.com/fakelogin'])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('muro'));
    }

    /**
     * CA-30: Usuario general que intente forzar intended_url hacia /admin/panel es redirigido al muro.
     */
    public function test_intended_admin_url_is_denied_for_general_user_and_redirected_to_muro(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $response = $this->withSession(['url.intended' => url('/admin/panel')])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('muro'));
    }

    /**
     * CA-08 y CA-28: Administrador con intended_url a gestión administrativa es redirigido a esa sección.
     */
    public function test_intended_admin_url_is_allowed_for_admin_user(): void
    {
        $admin = User::factory()->admin()->create();
        $adminSubroute = url('/admin/publicaciones?filter=pendientes');

        $response = $this->withSession(['url.intended' => $adminSubroute])
            ->post('/login', [
                'email' => $admin->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticated();
        $response->assertRedirect($adminSubroute);
    }

    /**
     * CA-08: Sin destino previo, el usuario general va al muro.
     */
    public function test_default_route_for_general_user_is_muro(): void
    {
        $user = User::factory()->create();
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $response->assertRedirect(route('muro'));
    }

    /**
     * CA-08: Sin destino previo, el administrador va a su panel administrativo.
     */
    public function test_default_route_for_admin_is_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);
        $response->assertRedirect(route('admin.dashboard'));
    }
}
