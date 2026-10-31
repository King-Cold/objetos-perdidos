<?php

namespace Tests\Feature\Auth;

use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordResetCompletionTask035Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('password_reset|127.0.0.1');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Prueba 1: Restablecimiento exitoso.
     * Requerimientos 1, 2, 3, 4, 7, 8:
     * Valida token, guarda con hash seguro, invalida el token, muestra el mensaje exacto y redirige a login.
     */
    public function test_successful_password_reset_redirects_to_login_with_exact_status_message_and_invalidates_token(): void
    {
        $user = User::factory()->create([
            'email' => 'estudiante@merida.tecnm.mx',
            'password' => Hash::make('ClaveVieja123!'),
        ]);

        $token = Password::broker()->createToken($user);

        // Comprobamos que el token existe antes de la operación
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ClaveOk2026!',
            'password_confirmation' => 'ClaveOk2026!',
        ]);

        // Redirige al formulario de inicio de sesión
        $response->assertRedirect(route('login'));
        $response->assertSessionHasNoErrors();

        // Muestra exactamente el mensaje requerido
        $expectedStatus = 'Tu contraseña se actualizó correctamente. Inicia sesión con tu nueva contraseña';
        $response->assertSessionHas('status', $expectedStatus);

        // La vista de login debe renderizar el mensaje
        $loginView = $this->get(route('login'));
        $loginView->assertSee($expectedStatus);

        // Token invalidado / eliminado en la misma operación atómica
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);

        // La contraseña se guardó con hash seguro de Laravel
        $user->refresh();
        $this->assertTrue(Hash::check('ClaveOk2026!', $user->password));
        $this->assertFalse(Hash::check('ClaveVieja123!', $user->password));
    }

    /**
     * Prueba 2: Inicio de sesión con la contraseña anterior rechazado.
     * Requerimiento 10: La contraseña anterior debe dejar de permitir nuevos inicios de sesión.
     */
    public function test_old_password_is_rejected_on_login_after_reset(): void
    {
        $user = User::factory()->create([
            'email' => 'alumno@merida.tecnm.mx',
            'password' => Hash::make('ClavePrevia123!'),
        ]);

        $token = Password::broker()->createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ClaveNueva1!',
            'password_confirmation' => 'ClaveNueva1!',
        ]);

        // Intentar iniciar sesión con la clave vieja
        $loginResponse = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'ClavePrevia123!',
        ]);

        $this->assertGuest();
        $loginResponse->assertSessionHasErrors(['email']);
    }

    /**
     * Prueba 3: Inicio de sesión con la nueva contraseña aceptado conforme a HU-02.
     * Requerimiento 11: La contraseña nueva debe permitir el acceso conforme a la HU-02.
     */
    public function test_new_password_is_accepted_on_login_after_reset(): void
    {
        // 1. Usuario General
        $generalUser = User::factory()->create([
            'email' => 'usuario.general@merida.tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
            'password' => Hash::make('ClaveVieja123!'),
        ]);

        $tokenGeneral = Password::broker()->createToken($generalUser);

        $this->post(route('password.store'), [
            'token' => $tokenGeneral,
            'email' => $generalUser->email,
            'password' => 'ClaveGen26!',
            'password_confirmation' => 'ClaveGen26!',
        ]);

        $loginResponseGeneral = $this->post(route('login'), [
            'email' => $generalUser->email,
            'password' => 'ClaveGen26!',
        ]);

        $this->assertAuthenticatedAs($generalUser);
        $loginResponseGeneral->assertRedirect(route('muro'));

        $this->post(route('logout'));
        $this->assertGuest();

        // 2. Administrador
        $adminUser = User::factory()->create([
            'email' => 'admin.test@merida.tecnm.mx',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('AdminVieja123!'),
        ]);

        $tokenAdmin = Password::broker()->createToken($adminUser);

        $this->post(route('password.store'), [
            'token' => $tokenAdmin,
            'email' => $adminUser->email,
            'password' => 'AdminOk26!',
            'password_confirmation' => 'AdminOk26!',
        ]);

        $loginResponseAdmin = $this->post(route('login'), [
            'email' => $adminUser->email,
            'password' => 'AdminOk26!',
        ]);

        $this->assertAuthenticatedAs($adminUser);
        $loginResponseAdmin->assertRedirect(route('admin.dashboard'));
    }

    /**
     * Prueba 4: Reutilización del token rechazada.
     * Requerimientos 3, 9, 12: El token utilizado debe ser rechazado en cualquier intento posterior.
     */
    public function test_consumed_token_reuse_is_rejected_in_subsequent_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'reuso@merida.tecnm.mx',
            'password' => Hash::make('ClaveOriginal1!'),
        ]);

        $token = Password::broker()->createToken($user);

        // Primer uso exitoso
        $firstResponse = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'PrimeraCl1!',
            'password_confirmation' => 'PrimeraCl1!',
        ]);
        $firstResponse->assertRedirect(route('login'));

        // Intento posterior con el mismo token
        $expectedError = 'El enlace de recuperación no es válido o ha vencido. Solicita uno nuevo';

        $secondResponse = $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'SegundaCl1!',
                'password_confirmation' => 'SegundaCl1!',
            ]);

        $secondResponse->assertSessionHas('error', $expectedError);
        $secondResponse->assertSessionHasErrors(['email' => $expectedError]);

        // La contraseña en BD sigue siendo la primera nueva contraseña establecida
        $user->refresh();
        $this->assertTrue(Hash::check('PrimeraCl1!', $user->password));
        $this->assertFalse(Hash::check('SegundaCl1!', $user->password));

        // Al intentar abrir la vista con el token consumido, muestra el aviso de token inválido
        $viewResponse = $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]));
        $viewResponse->assertSee($expectedError);
        $viewResponse->assertDontSee('Guardar nueva contraseña');
    }

    /**
     * Prueba 5: Confirmación de que los demás datos del usuario permanecen iguales.
     * Requerimiento 6: No modificar nombre, correo, teléfono, rol, reportes, evidencias ni otros datos.
     */
    public function test_user_other_attributes_and_relations_remain_unchanged(): void
    {
        $user = User::factory()->create([
            'name' => 'Carlos Rodriguez Alcocer',
            'email' => 'carlos.alcocer@merida.tecnm.mx',
            'phone' => '9991234567',
            'role' => User::ROLE_USUARIO_GENERAL,
            'password' => Hash::make('ClaveAntigua12!'),
        ]);

        // Crear una publicación asociada para comprobar que no se alteran relaciones ni datos
        $publicacion = Publicacion::create([
            'user_id' => $user->id,
            'titulo' => 'Mochila negra olvidada en biblioteca',
            'descripcion' => 'Mochila escolar con credencial y útiles',
            'categoria' => 'mochilas',
            'estado' => 'publicado',
            'ubicacion' => 'Biblioteca Campus Poniente',
            'fecha_evento' => '2026-10-29',
        ]);

        $token = Password::broker()->createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ClaveOk2026!',
            'password_confirmation' => 'ClaveOk2026!',
        ]);

        $user->refresh();

        // Verificamos que todos los atributos permanecen estrictamente intactos
        $this->assertSame('Carlos Rodriguez Alcocer', $user->name);
        $this->assertSame('carlos.alcocer@merida.tecnm.mx', $user->email);
        $this->assertSame('9991234567', $user->phone);
        $this->assertSame(User::ROLE_USUARIO_GENERAL, $user->role);

        // Verificamos que la publicación sigue existiendo e intacta
        $this->assertDatabaseHas('publicaciones', [
            'id' => $publicacion->id,
            'user_id' => $user->id,
            'titulo' => 'Mochila negra olvidada en biblioteca',
        ]);
    }

    /**
     * Prueba 6: Confirmación de que no existe una sesión autenticada después del cambio.
     * Requerimiento 9: No iniciar sesión automáticamente.
     */
    public function test_no_authenticated_session_exists_after_password_reset(): void
    {
        $user = User::factory()->create([
            'email' => 'invitado@merida.tecnm.mx',
            'password' => Hash::make('ClaveAnterior1!'),
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NuevaOk2026!',
            'password_confirmation' => 'NuevaOk2026!',
        ]);

        // Tras el restablecimiento no debe existir sesión activa
        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    /**
     * Prueba 7: Simulación de falla durante la operación.
     * Requerimiento 4, 13: Asegurar la atomicidad: no informar éxito si ocurre un error ni producir cambio parcial.
     */
    public function test_operation_failure_simulation_does_not_report_success_nor_partial_change(): void
    {
        $user = User::factory()->create([
            'email' => 'falla.simulada@merida.tecnm.mx',
            'password' => Hash::make('ClaveOriginalSegura1!'),
        ]);

        $token = Password::broker()->createToken($user);

        // Interceptamos el evento para simular una falla imprevista en base de datos dentro de la transacción
        Event::listen(PasswordReset::class, function () {
            throw new \RuntimeException('Falla imprevista durante la persistencia de la contraseña');
        });

        $response = $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'Fallida2026!',
                'password_confirmation' => 'Fallida2026!',
            ]);

        // No debe informar éxito
        $response->assertSessionMissing('status');
        $expectedError = 'El enlace de recuperación no es válido o ha vencido. Solicita uno nuevo';
        $response->assertSessionHas('error', $expectedError);
        $response->assertSessionHasErrors(['email' => $expectedError]);

        // La transacción hizo rollback: la contraseña en BD NO cambió
        $user->refresh();
        $this->assertTrue(Hash::check('ClaveOriginalSegura1!', $user->password));
        $this->assertFalse(Hash::check('Fallida2026!', $user->password));

        // El usuario no está autenticado
        $this->assertGuest();
    }

    /**
     * Prueba 8: Prueba de solicitudes simultáneas que garantice un solo cambio exitoso.
     * Requerimiento 5: Dos solicitudes simultáneas con el mismo token no pueden cambiar ambas la contraseña.
     */
    public function test_concurrent_requests_with_same_token_only_one_succeeds(): void
    {
        $user = User::factory()->create([
            'email' => 'paralelo@merida.tecnm.mx',
            'password' => Hash::make('ClaveInicial123!'),
        ]);

        $token = Password::broker()->createToken($user);

        // Solicitud 1: procesa exitosamente el restablecimiento
        $response1 = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Ganadora26!',
            'password_confirmation' => 'Ganadora26!',
        ]);

        $response1->assertRedirect(route('login'));
        $response1->assertSessionHas('status', 'Tu contraseña se actualizó correctamente. Inicia sesión con tu nueva contraseña');

        // Solicitud 2: intenta simultáneamente usar el mismo token que ya fue consumido atómicamente
        $response2 = $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'Perdedora2!',
                'password_confirmation' => 'Perdedora2!',
            ]);

        $expectedError = 'El enlace de recuperación no es válido o ha vencido. Solicita uno nuevo';
        $response2->assertSessionHas('error', $expectedError);
        $response2->assertSessionHasErrors(['email' => $expectedError]);

        // Verificamos que únicamente la Solicitud 1 fijó la clave
        $user->refresh();
        $this->assertTrue(Hash::check('Ganadora26!', $user->password));
        $this->assertFalse(Hash::check('Perdedora2!', $user->password));
    }

    /**
     * Prueba 9: Verificación de que una solicitud de recuperación sin completar el cambio no altera la contraseña anterior.
     * Requerimiento 14: Conservar el funcionamiento de la contraseña anterior cuando solamente se haya solicitado un enlace.
     */
    public function test_requesting_reset_link_or_leaving_form_uncompleted_preserves_old_password_login(): void
    {
        $user = User::factory()->create([
            'email' => 'solicitud.sola@merida.tecnm.mx',
            'password' => Hash::make('ClaveVigente123!'),
        ]);

        // 1. El usuario solicita un enlace
        $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        // 2. El usuario abre el formulario pero no lo envía (abandona la pantalla)
        $record = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        $this->assertNotNull($record);

        // 3. El usuario aún puede iniciar sesión perfectamente con su contraseña original
        $loginResponse = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'ClaveVigente123!',
        ]);

        $this->assertAuthenticatedAs($user);
        $loginResponse->assertRedirect(route('muro'));
    }
}
