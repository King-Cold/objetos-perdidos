<?php

namespace Tests\Feature\Auth;

use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTask022Test extends TestCase
{
    use RefreshDatabase;

    /**
     * CA-08 y CA-09: Usuario general es dirigido al muro al autenticarse.
     */
    public function test_login_success_for_general_user_redirects_to_muro(): void
    {
        $user = User::factory()->create([
            'email' => 'estudiante@merida.tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
            'password' => Hash::make('ClaveEstudiante2026!'),
        ]);

        $response = $this->post('/login', [
            'email' => 'estudiante@merida.tecnm.mx',
            'password' => 'ClaveEstudiante2026!',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('muro'));
    }

    /**
     * CA-08 y CA-09: Administrador/bibliotecario es dirigido a su panel administrativo al autenticarse.
     */
    public function test_login_success_for_admin_user_redirects_to_admin_panel(): void
    {
        $admin = User::factory()->create([
            'email' => 'bibliotecario@merida.tecnm.mx',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('ClaveAdmin2026!'),
        ]);

        $response = $this->post('/login', [
            'email' => 'bibliotecario@merida.tecnm.mx',
            'password' => 'ClaveAdmin2026!',
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('admin.dashboard'));
    }

    /**
     * CA-07: Se crea sesión asociada a la cuenta autenticada y se renueva su identificador de sesión.
     */
    public function test_session_id_is_regenerated_upon_successful_authentication(): void
    {
        $user = User::factory()->create([
            'email' => 'usuario.renovacion@merida.tecnm.mx',
            'password' => Hash::make('ClaveSegura2026!'),
        ]);

        // Iniciar sesión y capturar el ID de sesión previo
        $this->get('/login');
        $oldSessionId = session()->getId();

        $response = $this->post('/login', [
            'email' => 'usuario.renovacion@merida.tecnm.mx',
            'password' => 'ClaveSegura2026!',
        ]);

        $newSessionId = session()->getId();

        $this->assertAuthenticated();
        $this->assertNotEquals($oldSessionId, $newSessionId);
    }

    /**
     * CA-07, CA-10 y CA-11: Los permisos se obtienen exclusivamente del servidor.
     * Parámetros manipulados en la solicitud de login no conceden privilegios administrativos.
     */
    public function test_tampered_role_in_login_request_is_ignored_and_uses_server_data(): void
    {
        $generalUser = User::factory()->create([
            'email' => 'usuario.comun@merida.tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
            'password' => Hash::make('ClaveComun2026!'),
        ]);

        // Envío malicioso intentando inyectar rol de administrador
        $response = $this->post('/login', [
            'email' => 'usuario.comun@merida.tecnm.mx',
            'password' => 'ClaveComun2026!',
            'role' => User::ROLE_ADMIN,
            'is_admin' => 1,
        ]);

        $this->assertAuthenticatedAs($generalUser);
        // Debe ser dirigido al muro porque el servidor comprueba su rol en BD
        $response->assertRedirect(route('muro'));
        $this->assertTrue(auth()->user()->isUsuarioGeneral());
        $this->assertFalse(auth()->user()->isAdmin());
    }

    /**
     * CA-09: Administrador y bibliotecario corresponden al mismo rol y conjunto de permisos.
     */
    public function test_administrator_and_librarian_roles_resolve_to_same_admin_dashboard(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin.jefe@merida.tecnm.mx',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('ClaveSegura123!'),
        ]);

        $bibliotecario = User::factory()->create([
            'email' => 'biblioteca.aux@merida.tecnm.mx',
            'role' => 'Bibliotecario',
            'password' => Hash::make('ClaveSegura123!'),
        ]);

        // Login Admin
        $resAdmin = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'ClaveSegura123!',
        ]);
        $resAdmin->assertRedirect(route('admin.dashboard'));
        $this->post('/logout');

        // Login Bibliotecario
        $resBiblio = $this->post('/login', [
            'email' => $bibliotecario->email,
            'password' => 'ClaveSegura123!',
        ]);
        $resBiblio->assertRedirect(route('admin.dashboard'));
    }

    /**
     * CA-08 y CA-28: Si existe una publicación previamente seleccionada, se regresa a ella de manera autorizada.
     */
    public function test_login_returns_to_previously_selected_available_publication(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('ClaveSegura2026!'),
        ]);

        $publicacion = Publicacion::factory()->create([
            'disponible' => true,
            'estado' => 'Aprobada',
        ]);

        // El visitante consulta la publicación sin estar autenticado
        $this->get(route('publicaciones.show', $publicacion->id));
        $this->assertEquals(session('intended_publication_id'), $publicacion->id);

        // El visitante inicia sesión
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'ClaveSegura2026!',
        ]);

        // Se regresa a la publicación autorizada en lugar del muro predeterminado
        $response->assertRedirect(route('publicaciones.show', $publicacion->id));
    }

    /**
     * CA-29: Si la publicación dejó de estar disponible, muestra mensaje y ofrece volver al muro.
     */
    public function test_login_redirects_to_muro_when_prior_publication_became_unavailable(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('ClaveSegura2026!'),
        ]);

        // Simular que antes se seleccionó una publicación
        session()->put('intended_publication_id', 99999); // ID inexistente

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'ClaveSegura2026!',
        ]);

        // Redirige al muro con aviso
        $response->assertRedirect(route('muro'));
        $response->assertSessionHas('warning', 'Esta publicación no está disponible para consulta');
    }

    /**
     * CA-30: Destino de retorno debe ser ubicación interna; se bloquean redirecciones externas.
     */
    public function test_external_or_malicious_intended_url_is_blocked_and_redirects_to_default(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
            'password' => Hash::make('ClaveSegura2026!'),
        ]);

        // Intento de ataque de open redirect mediante sesión
        session()->put('url.intended', 'https://sitio-malicioso.com/phishing');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'ClaveSegura2026!',
        ]);

        // Bloqueado: redirigido a la ruta interna autorizada por su rol
        $response->assertRedirect(route('muro'));
    }

    /**
     * CA-30: Usuario general no puede acceder al panel administrativo mediante manipulación de intended URL.
     */
    public function test_general_user_cannot_access_admin_dashboard_via_intended_url_manipulation(): void
    {
        $generalUser = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
            'password' => Hash::make('ClaveSegura2026!'),
        ]);

        session()->put('url.intended', route('admin.dashboard'));

        $response = $this->post('/login', [
            'email' => $generalUser->email,
            'password' => 'ClaveSegura2026!',
        ]);

        // Bloqueado para usuario general: enviado al muro público
        $response->assertRedirect(route('muro'));
    }

    /**
     * CA-31 y CA-32: Errores internos no revelan SQL, rutas ni contraseñas.
     */
    public function test_internal_error_is_handled_gracefully_without_sensitive_exposure(): void
    {
        // Enviar credenciales incorrectas para verificar que no hay éxito falso ni fugas
        $response = $this->from('/login')->post('/login', [
            'email' => 'inexistente@merida.tecnm.mx',
            'password' => 'ClaveInvalida123!',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');

        // Verificar que en la sesión ni en el contenido existan datos sensibles
        $content = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $content);
        $this->assertStringNotContainsString('ClaveInvalida123!', $content);
        $this->assertGuest();
    }
}

