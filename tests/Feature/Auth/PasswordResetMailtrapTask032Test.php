<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordResetMailtrapTask032Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('password_reset|127.0.0.1');
    }

    /**
     * Requerimiento 1 y 4:
     * Verifica la configuración de expiración de tokens a 15 minutos.
     */
    public function test_password_reset_token_expiration_is_configured_to_15_minutes(): void
    {
        $this->assertEquals(15, config('auth.passwords.users.expire'));
    }

    /**
     * Requerimientos 2 y 6:
     * Solicitar recuperación con cuenta registrada envía la notificación al destinatario lógico registrado.
     */
    public function test_reset_email_is_sent_only_to_registered_account_logical_recipient(): void
    {
        Notification::fake();

        $registeredEmail = 'usuario.legitimo@merida.tecnm.mx';
        $user = User::factory()->create([
            'name' => 'Usuario Legítimo',
            'email' => $registeredEmail,
        ]);

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $registeredEmail,
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('status', 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña');

        Notification::assertSentTo($user, ResetPassword::class, function ($notification, $channels, $notifiable) use ($registeredEmail) {
            return $notifiable->email === $registeredEmail;
        });
    }

    /**
     * Requerimiento 3 y 6:
     * El correo de recuperación tiene el asunto exacto y todo el contenido obligatorio en español.
     */
    public function test_reset_email_content_and_subject_are_correct(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'name' => 'Ana Gómez',
            'email' => 'ana.gomez@merida.tecnm.mx',
        ]);

        $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $mailMessage = $notification->toMail($user);

            // 1. Asunto exacto
            $this->assertEquals('Restablecimiento de contraseña', $mailMessage->subject);

            // Renderizar la plantilla HTML
            $rendered = (string) $mailMessage->render();

            // 2. Explicación de que se solicitó recuperar el acceso al sistema de Objetos Perdidos del Tec Campus Poniente
            $this->assertStringContainsString('Objetos Perdidos del Tec Campus Poniente', $rendered);

            // 3. Enlace para restablecer la contraseña
            $this->assertStringContainsString('reset-password', $rendered);
            $this->assertStringContainsString('Restablecer contraseña', $rendered);

            // 4. Indicación de vencimiento a los 15 minutos
            $this->assertStringContainsString('15 minutos', $rendered);

            // 5. Indicación de que puede utilizarse una sola vez
            $this->assertStringContainsString('una sola vez', $rendered);

            // 6. Aclaración de que puede ignorar el mensaje si no solicitó el cambio
            $this->assertStringContainsString('puedes ignorar este mensaje', $rendered);

            // 7. No debe incluir contraseñas provisionales
            $this->assertStringNotContainsString('contraseña provisional', strtolower($rendered));

            return true;
        });
    }

    /**
     * Requerimiento 2 y 6:
     * Si el correo institucional no existe, no se genera ni envía ningún mensaje.
     */
    public function test_reset_email_is_not_sent_when_email_does_not_exist(): void
    {
        Notification::fake();

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'no_existe_en_bd@merida.tecnm.mx',
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('status', 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña');

        Notification::assertNothingSent();
    }

    /**
     * Requerimiento 4:
     * No revelar públicamente la existencia de cuentas (respuestas idénticas para existentes e inexistentes).
     */
    public function test_public_response_is_strictly_identical_for_existing_and_nonexistent_emails(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'activo@merida.tecnm.mx',
        ]);

        $existingResp = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'activo@merida.tecnm.mx',
        ]);

        $nonExistingResp = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'inactivo@merida.tecnm.mx',
        ]);

        $this->assertEquals(
            $existingResp->getSession()->get('status'),
            $nonExistingResp->getSession()->get('status')
        );

        $this->assertNull($existingResp->getSession()->get('errors'));
        $this->assertNull($nonExistingResp->getSession()->get('errors'));
    }

    /**
     * Requerimiento 5 y 6:
     * Simulación de falla en el servicio SMTP.
     * Debe mostrar exactamente: «No es posible procesar la recuperación en este momento. Intenta nuevamente más tarde».
     */
    public function test_smtp_failure_displays_exact_generic_error_message(): void
    {
        $user = User::factory()->create([
            'email' => 'smtp.fail@merida.tecnm.mx',
        ]);

        // Mockeamos la notificación para simular falla de transporte SMTP
        Notification::shouldReceive('send')
            ->andThrow(new \Symfony\Component\Mailer\Exception\TransportException('Connection to sandbox.smtp.mailtrap.io:2525 timed out'));

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertRedirect(route('password.request'));

        $expectedError = 'No es posible procesar la recuperación en este momento. Intenta nuevamente más tarde';
        $response->assertSessionHas('error', $expectedError);
        $response->assertSessionHasErrors(['email' => $expectedError]);
    }

    /**
     * Requerimiento 5 y 6:
     * Una falla de envío no modifica la contraseña actual ni impide iniciar sesión con ella.
     */
    public function test_smtp_failure_does_not_modify_current_password_and_user_can_still_login(): void
    {
        $rawPassword = 'MiPasswordSeguro2026!';
        $user = User::factory()->create([
            'email' => 'seguro.login@merida.tecnm.mx',
            'password' => Hash::make($rawPassword),
        ]);

        Notification::shouldReceive('send')
            ->andThrow(new \Exception('SMTP Error'));

        $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $user->email,
        ]);

        // Verificar que el hash de la contraseña en BD es idéntico
        $user->refresh();
        $this->assertTrue(Hash::check($rawPassword, $user->password));

        // Verificar que el usuario puede autenticarse con su contraseña actual
        $loginResponse = $this->post('/login', [
            'email' => $user->email,
            'password' => $rawPassword,
        ]);

        $loginResponse->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Requerimiento 2:
     * La solicitud no debe crear cuentas ni modificar contraseñas.
     */
    public function test_request_does_not_create_accounts_or_alter_user_attributes(): void
    {
        Notification::fake();

        $originalHash = Hash::make('ClaveOriginal123#');
        $user = User::factory()->create([
            'email' => 'permanente@merida.tecnm.mx',
            'name' => 'Usuario Permanente',
            'password' => $originalHash,
        ]);

        $usersCountBefore = User::count();

        $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $user->refresh();
        $this->assertEquals($usersCountBefore, User::count());
        $this->assertEquals($originalHash, $user->password);
        $this->assertEquals('Usuario Permanente', $user->name);
    }
}
