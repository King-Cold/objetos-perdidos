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

class PasswordResetFormTask034Test extends TestCase
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
     * Requerimientos 1, 3, 4, 5, 6, 7 y 18:
     * Verifica la renderización del formulario con token válido:
     * - Campos nueva contraseña y confirmación con etiquetas visibles e indicadores (*).
     * - Ocultos de forma predeterminada (type="password").
     * - Botones accesibles de visibilidad (showPassword, showConfirmPassword).
     * - Reglas de contraseña visibles antes del envío.
     * - No solicita ni muestra contraseña anterior.
     */
    public function test_reset_password_screen_renders_properly_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'estudiante@merida.tecnm.mx',
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->get(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]));

        $response->assertStatus(200);

        // Campos obligatorios e indicadores
        $response->assertSee('Nueva contraseña');
        $response->assertSee('Confirmar nueva contraseña');
        $response->assertSee('*');

        // Visibilidad toggle accesible
        $response->assertSee('showPassword');
        $response->assertSee('showConfirmPassword');
        $response->assertSee('Mostrar contraseña');
        $response->assertSee('Ocultar contraseña');

        // Reglas de contraseña visibles antes del envío (Requerimiento 7)
        $response->assertSee('8 y 12 caracteres');
        $response->assertSee('una letra mayúscula');
        $response->assertSee('un número');
        $response->assertSee('un carácter especial');
        $response->assertSee('un espacio no cuenta como carácter especial');

        // Botón de acción
        $response->assertSee('Guardar nueva contraseña');

        // No debe solicitar contraseña anterior (Requerimiento 18)
        $response->assertDontSee('contraseña anterior', false);
        $response->assertDontSee('contraseña actual', false);
        $response->assertDontSee('name="current_password"', false);
    }

    /**
     * Requerimientos 3 y 4:
     * Validación de campos obligatorios vacíos.
     */
    public function test_password_fields_are_required(): void
    {
        $user = User::factory()->create([
            'email' => 'requeridos@merida.tecnm.mx',
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => $token,
                'email' => $user->email,
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response->assertRedirect(route('password.reset', ['token' => $token, 'email' => $user->email]));
        $response->assertSessionHasErrors(['password' => 'La contraseña es obligatoria.']);
        $response->assertSessionHasErrors(['password_confirmation' => 'La confirmación de contraseña es obligatoria.']);
    }

    /**
     * Requerimientos 8 y 9:
     * Comprobación de límites de longitud: 7, 8, 12 y 13 caracteres.
     */
    public function test_password_length_boundaries_7_8_12_and_13_characters(): void
    {
        $user = User::factory()->create([
            'email' => 'longitudes@merida.tecnm.mx',
            'password' => Hash::make('ClaveOriginal1!'),
        ]);

        $token = Password::broker()->createToken($user);

        // 1. Longitud 7 caracteres: Rechazado (min: 8)
        $response7 = $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'Abc1!xy', // 7 chars
                'password_confirmation' => 'Abc1!xy',
            ]);
        $response7->assertSessionHasErrors(['password' => 'La contraseña debe contener al menos 8 caracteres.']);

        // 2. Longitud 13 caracteres: Rechazado (max: 12)
        $response13 = $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'Abc1!xyz12345', // 13 chars
                'password_confirmation' => 'Abc1!xyz12345',
            ]);
        $response13->assertSessionHasErrors(['password' => 'La contraseña no debe exceder los 12 caracteres.']);

        // 3. Longitud 8 caracteres exacta: Aceptada
        $token8 = Password::broker()->createToken($user);
        $response8 = $this->from(route('password.reset', ['token' => $token8, 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => $token8,
                'email' => $user->email,
                'password' => 'Abcde1!#', // 8 chars
                'password_confirmation' => 'Abcde1!#',
            ]);
        $response8->assertRedirect(route('login'));
        $response8->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertTrue(Hash::check('Abcde1!#', $user->password));

        // 4. Longitud 12 caracteres exacta: Aceptada
        $token12 = Password::broker()->createToken($user);
        $response12 = $this->from(route('password.reset', ['token' => $token12, 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => $token12,
                'email' => $user->email,
                'password' => 'Abcdefgh12!#', // 12 chars
                'password_confirmation' => 'Abcdefgh12!#',
            ]);
        $response12->assertRedirect(route('login'));
        $response12->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertTrue(Hash::check('Abcdefgh12!#', $user->password));
    }

    /**
     * Requerimiento 7:
     * Reglas de complejidad: mayúscula, número, carácter especial, y espacio no cuenta como especial.
     */
    public function test_password_complexity_rules(): void
    {
        $user = User::factory()->create([
            'email' => 'complejidad@merida.tecnm.mx',
        ]);

        $token = Password::broker()->createToken($user);

        // Sin mayúscula
        $sinMayus = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'abcde123!#',
            'password_confirmation' => 'abcde123!#',
        ]);
        $sinMayus->assertSessionHasErrors(['password' => 'La contraseña debe incluir al menos una letra mayúscula.']);

        // Sin número
        $sinNumero = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Abcdefgh!#',
            'password_confirmation' => 'Abcdefgh!#',
        ]);
        $sinNumero->assertSessionHasErrors(['password' => 'La contraseña debe incluir al menos un número.']);

        // Sin carácter especial
        $sinEspecial = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Abcdefgh12',
            'password_confirmation' => 'Abcdefgh12',
        ]);
        $sinEspecial->assertSessionHasErrors(['password' => 'La contraseña debe incluir al menos un carácter especial.']);

        // Espacio NO cuenta como carácter especial
        $conEspacio = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Abcdef 12',
            'password_confirmation' => 'Abcdef 12',
        ]);
        $conEspacio->assertSessionHasErrors(['password' => 'La contraseña debe incluir al menos un carácter especial.']);
    }

    /**
     * Requerimientos 10 y 11:
     * La confirmación debe coincidir exactamente.
     * Si no coincide, mostrar: «La confirmación de contraseña no coincide».
     */
    public function test_password_confirmation_must_match_exactly(): void
    {
        $user = User::factory()->create([
            'email' => 'confirmacion@merida.tecnm.mx',
        ]);

        $token = Password::broker()->createToken($user);

        // Discrepancia por mayúsculas/minúsculas
        $caseMismatch = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Password123!',
            'password_confirmation' => 'password123!',
        ]);
        $caseMismatch->assertSessionHasErrors(['password_confirmation' => 'La confirmación de contraseña no coincide']);

        // Discrepancia por símbolo
        $symbolMismatch = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123#',
        ]);
        $symbolMismatch->assertSessionHasErrors(['password_confirmation' => 'La confirmación de contraseña no coincide']);
    }

    /**
     * Requerimiento 13:
     * Un intento rechazado por validación no debe modificar la contraseña ni consumir el token.
     */
    public function test_failed_validation_does_not_consume_token_nor_modify_password(): void
    {
        $originalHash = Hash::make('ClaveAntiguaSegura1!');
        $user = User::factory()->create([
            'email' => 'persistente@merida.tecnm.mx',
            'password' => $originalHash,
        ]);

        $token = Password::broker()->createToken($user);

        // Intento con validación fallida (7 caracteres y sin confirmación)
        $failedResponse = $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'Corta1!',
                'password_confirmation' => 'Diferente2#',
            ]);

        $failedResponse->assertRedirect(route('password.reset', ['token' => $token, 'email' => $user->email]));
        $failedResponse->assertSessionHasErrors(['password', 'password_confirmation']);

        // El token sigue existiendo en BD
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);

        // La contraseña original no ha cambiado
        $user->refresh();
        $this->assertEquals($originalHash, $user->password);

        // Intento corregido posterior con el mismo token: tiene éxito
        $successResponse = $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'ClaveOk2026!',
                'password_confirmation' => 'ClaveOk2026!',
            ]);

        $successResponse->assertRedirect(route('login'));
        $successResponse->assertSessionHasNoErrors();

        // Ahora sí se consumió el token y cambió la clave
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $user->refresh();
        $this->assertTrue(Hash::check('ClaveOk2026!', $user->password));
    }

    /**
     * Requerimientos 14, 15 y 16:
     * Si el enlace vence mientras se completa el formulario (>= 15 min),
     * rechazar el envío y mostrar: «El enlace de recuperación no es válido o ha vencido. Solicita uno nuevo».
     */
    public function test_token_expiration_during_form_filling_rejects_and_shows_exact_message(): void
    {
        Carbon::setTestNow('2026-10-30 09:00:00');

        $originalHash = Hash::make('ClaveSinModificar1!');
        $user = User::factory()->create([
            'email' => 'tardanza@merida.tecnm.mx',
            'password' => $originalHash,
        ]);

        $token = Password::broker()->createToken($user);

        // Usuario abre el formulario al minuto 10 (token válido en ese momento)
        Carbon::setTestNow('2026-10-30 09:10:00');
        $viewResponse = $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Nueva contraseña');

        // Usuario tarda en llenar el formulario y envía al minuto 15 exacto (09:15:00)
        Carbon::setTestNow('2026-10-30 09:15:00');

        $postResponse = $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.store'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'Tardio2026!',
                'password_confirmation' => 'Tardio2026!',
            ]);

        $postResponse->assertRedirect(route('password.reset', ['token' => $token, 'email' => $user->email]));

        $expectedMessage = 'El enlace de recuperación no es válido o ha vencido. Solicita uno nuevo';
        $postResponse->assertSessionHas('error', $expectedMessage);
        $postResponse->assertSessionHasErrors(['email' => $expectedMessage]);

        // La contraseña original permanece intacta
        $user->refresh();
        $this->assertEquals($originalHash, $user->password);
    }
}
