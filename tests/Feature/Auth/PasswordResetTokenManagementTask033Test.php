<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordResetTokenManagementTask033Test extends TestCase
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
     * Requerimiento 4 y 5 / Prueba 1:
     * Token válido antes de 15 minutos (ej. a los 14 minutos y 59 segundos).
     */
    public function test_token_is_valid_before_15_minutes(): void
    {
        Carbon::setTestNow('2026-10-30 10:00:00');

        $user = User::factory()->create([
            'email' => 'alumno@merida.tecnm.mx',
            'password' => Hash::make('ClaveAntigua123!'),
        ]);

        $plainToken = Password::broker()->createToken($user);

        // Avanzar el tiempo a 14 minutos y 59 segundos (< 15 minutos)
        Carbon::setTestNow('2026-10-30 10:14:59');

        // 1. La pantalla de reseteo debe renderizarse como válida
        $viewResponse = $this->get(route('password.reset', [
            'token' => $plainToken,
            'email' => $user->email,
        ]));

        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Nueva contraseña');
        $viewResponse->assertSee('name="password"', false);
        $viewResponse->assertDontSee('El enlace de recuperación no es válido o ha vencido. Solicita uno nuevo');

        // 2. El cambio de contraseña debe completarse con éxito
        $postResponse = $this->post(route('password.store'), [
            'token' => $plainToken,
            'email' => $user->email,
            'password' => 'ClaveVal123!',
            'password_confirmation' => 'ClaveVal123!',
        ]);

        $postResponse->assertRedirect(route('login'));
        $postResponse->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('ClaveVal123!', $user->password));

        Carbon::setTestNow();
    }

    /**
     * Requerimiento 4 y 5 / Prueba 2:
     * Token vencido exactamente a los 15 minutos.
     * Al alcanzar exactamente 15 minutos debe considerarse vencido.
     */
    public function test_token_is_expired_at_exactly_15_minutes(): void
    {
        Carbon::setTestNow('2026-10-30 10:00:00');

        $user = User::factory()->create([
            'email' => 'exacto@merida.tecnm.mx',
            'password' => Hash::make('ClaveOriginal123!'),
        ]);

        $plainToken = Password::broker()->createToken($user);

        // Avanzar el tiempo a exactamente 15 minutos (10:15:00)
        Carbon::setTestNow('2026-10-30 10:15:00');

        // 1. Al consultar el formulario, debe rechazar y mostrar el mensaje requerido
        $viewResponse = $this->get(route('password.reset', [
            'token' => $plainToken,
            'email' => $user->email,
        ]));

        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('El enlace de recuperación no es válido o ha vencido. Solicita uno nuevo');
        $viewResponse->assertSee(route('password.request'));
        $viewResponse->assertDontSee('name="password"', false);

        // 2. Al intentar guardar la contraseña, debe ser rechazado
        $postResponse = $this->from(route('password.reset', ['token' => $plainToken, 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => $plainToken,
                'email' => $user->email,
                'password' => 'Expirado123!',
                'password_confirmation' => 'Expirado123!',
            ]);

        $postResponse->assertRedirect();
        $expectedMessage = 'El enlace de recuperación no es válido o ha vencido. Solicita uno nuevo';
        $postResponse->assertSessionHas('error', $expectedMessage);
        $postResponse->assertSessionHasErrors(['email' => $expectedMessage]);

        // La contraseña original permanece intacta
        $user->refresh();
        $this->assertTrue(Hash::check('ClaveOriginal123!', $user->password));

        Carbon::setTestNow();
    }

    /**
     * Requerimiento 4 y 5 / Prueba 3:
     * Token vencido después de 15 minutos (ej. a los 15 minutos y 1 segundo o 20 minutos).
     */
    public function test_token_is_expired_after_15_minutes(): void
    {
        Carbon::setTestNow('2026-10-30 10:00:00');

        $user = User::factory()->create([
            'email' => 'vencido@merida.tecnm.mx',
            'password' => Hash::make('ClaveOriginalSegura1!'),
        ]);

        $plainToken = Password::broker()->createToken($user);

        // Avanzar el tiempo a 20 minutos después
        Carbon::setTestNow('2026-10-30 10:20:00');

        $viewResponse = $this->get(route('password.reset', [
            'token' => $plainToken,
            'email' => $user->email,
        ]));

        $viewResponse->assertSee('El enlace de recuperación no es válido o ha vencido. Solicita uno nuevo');
        $viewResponse->assertSee(route('password.request'));
        $viewResponse->assertDontSee('name="password"', false);

        $postResponse = $this->from(route('password.reset', ['token' => $plainToken, 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => $plainToken,
                'email' => $user->email,
                'password' => 'Vencida123!',
                'password_confirmation' => 'Vencida123!',
            ]);

        $expectedMessage = 'El enlace de recuperación no es válido o ha vencido. Solicita uno nuevo';
        $postResponse->assertSessionHas('error', $expectedMessage);
        $postResponse->assertSessionHasErrors(['email' => $expectedMessage]);

        $user->refresh();
        $this->assertTrue(Hash::check('ClaveOriginalSegura1!', $user->password));

        Carbon::setTestNow();
    }

    /**
     * Requerimientos 9, 10 y 16 / Prueba 4:
     * Token inexistente y token alterado.
     */
    public function test_nonexistent_and_altered_tokens_are_rejected_with_exact_message(): void
    {
        $user = User::factory()->create([
            'email' => 'legitimo@merida.tecnm.mx',
        ]);

        $validToken = Password::broker()->createToken($user);
        $alteredToken = substr($validToken, 0, -4) . 'WXYZ';
        $nonExistentToken = 'token_que_nunca_ha_existido_1234567890';

        $expectedMessage = 'El enlace de recuperación no es válido o ha vencido. Solicita uno nuevo';

        // Caso A: Token alterado en formulario
        $alteredView = $this->get(route('password.reset', [
            'token' => $alteredToken,
            'email' => $user->email,
        ]));
        $alteredView->assertSee($expectedMessage);
        $alteredView->assertSee(route('password.request'));
        $alteredView->assertDontSee('name="password"', false);

        // Caso B: Token alterado en envío POST
        $alteredPost = $this->from(route('password.reset', ['token' => $alteredToken, 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => $alteredToken,
                'email' => $user->email,
                'password' => 'NuevaClave1!',
                'password_confirmation' => 'NuevaClave1!',
            ]);
        $alteredPost->assertSessionHas('error', $expectedMessage);
        $alteredPost->assertSessionHasErrors(['email' => $expectedMessage]);

        // Caso C: Token inexistente en formulario
        $nonexistentView = $this->get(route('password.reset', [
            'token' => $nonExistentToken,
            'email' => $user->email,
        ]));
        $nonexistentView->assertSee($expectedMessage);
        $nonexistentView->assertDontSee('name="password"', false);

        // Caso D: Token inexistente en envío POST
        $nonexistentPost = $this->from(route('password.reset', ['token' => $nonExistentToken, 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => $nonExistentToken,
                'email' => $user->email,
                'password' => 'NuevaClave1!',
                'password_confirmation' => 'NuevaClave1!',
            ]);
        $nonexistentPost->assertSessionHas('error', $expectedMessage);
        $nonexistentPost->assertSessionHasErrors(['email' => $expectedMessage]);
    }

    /**
     * Requerimientos 2, 9 y 15 / Prueba 5:
     * Token asociado a otra cuenta:
     * No permitir que la manipulación del correo o token cambie la contraseña de otra cuenta.
     */
    public function test_token_associated_with_another_account_is_strictly_rejected(): void
    {
        $userVictima = User::factory()->create([
            'email' => 'victima@merida.tecnm.mx',
            'password' => Hash::make('ClaveVictimaIntacta1!'),
        ]);

        $userAtacante = User::factory()->create([
            'email' => 'atacante@merida.tecnm.mx',
            'password' => Hash::make('ClaveAtacante1!'),
        ]);

        // Se genera token legítimo para atacante
        $tokenAtacante = Password::broker()->createToken($userAtacante);

        $expectedMessage = 'El enlace de recuperación no es válido o ha vencido. Solicita uno nuevo';

        // Intento 1: Atacante intenta ver formulario para la víctima usando su token
        $viewResponse = $this->get(route('password.reset', [
            'token' => $tokenAtacante,
            'email' => $userVictima->email,
        ]));
        $viewResponse->assertSee($expectedMessage);
        $viewResponse->assertDontSee('name="password"', false);

        // Intento 2: Atacante envía POST intentando cambiar la clave de la víctima con su token
        $postResponse = $this->from(route('password.reset', ['token' => $tokenAtacante, 'email' => $userVictima->email]))
            ->post(route('password.store'), [
                'token' => $tokenAtacante,
                'email' => $userVictima->email,
                'password' => 'Atacante12!',
                'password_confirmation' => 'Atacante12!',
            ]);

        $postResponse->assertSessionHas('error', $expectedMessage);
        $postResponse->assertSessionHasErrors(['email' => $expectedMessage]);

        // Verificar que la contraseña de la víctima NO fue modificada
        $userVictima->refresh();
        $this->assertTrue(Hash::check('ClaveVictimaIntacta1!', $userVictima->password));
    }

    /**
     * Requerimiento 8 / Prueba 6:
     * Abrir el enlace y consultar el formulario no debe consumir el token ni modificar la contraseña.
     */
    public function test_opening_form_does_not_consume_token_nor_modify_password(): void
    {
        $originalPassword = 'PasswordOriginalSinCambio1!';
        $user = User::factory()->create([
            'email' => 'consulta@merida.tecnm.mx',
            'password' => Hash::make($originalPassword),
        ]);

        $token = Password::broker()->createToken($user);

        // Abrir y consultar el formulario 3 veces consecutivas
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->get(route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]));

            $response->assertStatus(200);
            $response->assertSee('Nueva contraseña');
            $response->assertSee('name="password"', false);
        }

        // Comprobar que el token sigue existiendo en BD intacto
        $record = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        $this->assertNotNull($record);

        // Comprobar que la contraseña del usuario permanece intacta
        $user->refresh();
        $this->assertTrue(Hash::check($originalPassword, $user->password));

        // Comprobar que el token aún puede utilizarse exitosamente para cambiar la clave
        $resetResponse = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Consulta12!',
            'password_confirmation' => 'Consulta12!',
        ]);

        $resetResponse->assertRedirect(route('login'));
        $user->refresh();
        $this->assertTrue(Hash::check('Consulta12!', $user->password));
    }

    /**
     * Requerimiento 12 / Prueba 7:
     * Al generar un nuevo token para una cuenta, invalidar inmediatamente todos los anteriores.
     */
    public function test_generating_second_token_invalidates_the_first(): void
    {
        $user = User::factory()->create([
            'email' => 'dobletoken@merida.tecnm.mx',
            'password' => Hash::make('ClaveInicial2026!'),
        ]);

        // 1. Emitir Token 1
        $token1 = Password::broker()->createToken($user);

        // 2. Emitir Token 2 para la misma cuenta
        $token2 = Password::broker()->createToken($user);

        $expectedMessage = 'El enlace de recuperación no es válido o ha vencido. Solicita uno nuevo';

        // 3. Token 1 debe estar invalidado tanto en visualización como en POST
        $viewToken1 = $this->get(route('password.reset', ['token' => $token1, 'email' => $user->email]));
        $viewToken1->assertSee($expectedMessage);
        $viewToken1->assertDontSee('name="password"', false);

        $postToken1 = $this->from(route('password.reset', ['token' => $token1, 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => $token1,
                'email' => $user->email,
                'password' => 'Token12345!',
                'password_confirmation' => 'Token12345!',
            ]);
        $postToken1->assertSessionHas('error', $expectedMessage);

        // 4. Token 2 debe ser plenamente válido
        $viewToken2 = $this->get(route('password.reset', ['token' => $token2, 'email' => $user->email]));
        $viewToken2->assertStatus(200);
        $viewToken2->assertSee('Nueva contraseña');

        $postToken2 = $this->post(route('password.store'), [
            'token' => $token2,
            'email' => $user->email,
            'password' => 'Exito12345!',
            'password_confirmation' => 'Exito12345!',
        ]);
        $postToken2->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('Exito12345!', $user->password));
    }

    /**
     * Requerimiento 13 / Prueba 8:
     * Reutilización de un token consumido.
     * Permitir un solo restablecimiento exitoso por token.
     */
    public function test_consumed_token_cannot_be_reused(): void
    {
        $user = User::factory()->create([
            'email' => 'unsolouso@merida.tecnm.mx',
            'password' => Hash::make('ClaveVieja1!'),
        ]);

        $token = Password::broker()->createToken($user);

        // Uso 1: Restablecimiento exitoso
        $firstReset = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'PrimeraCl1!',
            'password_confirmation' => 'PrimeraCl1!',
        ]);
        $firstReset->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('PrimeraCl1!', $user->password));

        // Uso 2: Intento de reutilizar el mismo token ya consumido
        $expectedMessage = 'El enlace de recuperación no es válido o ha vencido. Solicita uno nuevo';

        $secondView = $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]));
        $secondView->assertSee($expectedMessage);
        $secondView->assertDontSee('name="password"', false);

        $secondReset = $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'SegundaCl2!',
                'password_confirmation' => 'SegundaCl2!',
            ]);

        $secondReset->assertSessionHas('error', $expectedMessage);
        $secondReset->assertSessionHasErrors(['email' => $expectedMessage]);

        // La clave debe seguir siendo la primera nueva clave establecida
        $user->refresh();
        $this->assertTrue(Hash::check('PrimeraCl1!', $user->password));
        $this->assertFalse(Hash::check('SegundaCl2!', $user->password));
    }

    /**
     * Requerimiento 14 / Prueba 9:
     * Dos solicitudes concurrentes con el mismo token:
     * Únicamente una debe poder completar el cambio de contraseña.
     */
    public function test_concurrent_requests_with_same_token_only_one_succeeds(): void
    {
        $user = User::factory()->create([
            'email' => 'concurrente@merida.tecnm.mx',
            'password' => Hash::make('ClavePreviaConcurrente1!'),
        ]);

        $token = Password::broker()->createToken($user);

        // Simulamos dos llamadas que acceden con el mismo token
        // Solicitud A: llega primero y procesa el restablecimiento
        $responseA = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Ganadora12!',
            'password_confirmation' => 'Ganadora12!',
        ]);

        // Solicitud A tiene éxito
        $responseA->assertRedirect(route('login'));
        $responseA->assertSessionHasNoErrors();

        // Solicitud B: enviada con el mismo token casi en paralelo (al llegar a la base de datos el token ya fue consumido)
        $responseB = $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'Perdedora1!',
                'password_confirmation' => 'Perdedora1!',
            ]);

        // Solicitud B es rechazada
        $expectedMessage = 'El enlace de recuperación no es válido o ha vencido. Solicita uno nuevo';
        $responseB->assertSessionHas('error', $expectedMessage);
        $responseB->assertSessionHasErrors(['email' => $expectedMessage]);

        // La contraseña final en BD debe ser la de la Solicitud A
        $user->refresh();
        $this->assertTrue(Hash::check('Ganadora12!', $user->password));
        $this->assertFalse(Hash::check('Perdedora1!', $user->password));
    }
}
