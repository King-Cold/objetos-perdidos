<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordResetRequestFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('password_reset|127.0.0.1');
    }

    /**
     * Requerimiento 1:
     * Incorporar en la pantalla de inicio de sesión un enlace visible con el texto exacto «Olvidé mi contraseña».
     */
    public function test_login_screen_has_visible_link_with_exact_text_olvide_mi_contrasena(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee(route('password.request'));
        $response->assertSee('Olvidé mi contraseña');
    }

    /**
     * Requerimiento 2:
     * El enlace debe dirigir a un formulario de recuperación accesible sin iniciar sesión.
     */
    public function test_password_reset_form_is_accessible_without_authentication(): void
    {
        // 1. Visitante no autenticado tiene acceso directo
        $response = $this->get(route('password.request'));
        $response->assertStatus(200);
        $response->assertSee('Recuperar contraseña');

        // 2. Usuario ya autenticado es redirigido (middleware guest)
        $user = User::factory()->create();
        $authResponse = $this->actingAs($user)->get(route('password.request'));
        $authResponse->assertRedirect();
    }

    /**
     * Requerimientos 3, 4 y 12:
     * El formulario debe solicitar únicamente el correo electrónico institucional,
     * con etiqueta visible, indicador de obligatorio (*) e opción para regresar al login.
     */
    public function test_password_reset_form_renders_only_email_field_with_label_and_asterisk(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertStatus(200);
        $response->assertSee('Correo electrónico institucional');
        $response->assertSee('*');
        $response->assertSee('usuario@merida.tecnm.mx');
        $response->assertSee('name="email"', false);
        $response->assertSee('Enviar enlace de recuperación');
        $response->assertSee(route('login'));
        $response->assertSee('Regresar al inicio de sesión');

        // Verifica que no existan campos ajenos a la solicitud (como password o teléfono)
        $response->assertDontSee('name="password"', false);
        $response->assertDontSee('name="phone"', false);
    }

    /**
     * Requerimiento 5:
     * Si el correo está vacío o contiene únicamente espacios, mostrar:
     * «Ingresa tu correo electrónico institucional».
     */
    public function test_validation_rejects_empty_email_and_whitespace_only_email_with_exact_message(): void
    {
        $expectedError = 'Ingresa tu correo electrónico institucional';

        // Intento con campo vacío
        $emptyResponse = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => '',
        ]);
        $emptyResponse->assertRedirect(route('password.request'));
        $emptyResponse->assertSessionHasErrors(['email' => $expectedError]);

        // Intento con únicamente espacios
        $spacesResponse = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => '     ',
        ]);
        $spacesResponse->assertRedirect(route('password.request'));
        $spacesResponse->assertSessionHasErrors(['email' => $expectedError]);

        // Intento con campo nulo/ausente
        $nullResponse = $this->from(route('password.request'))->post(route('password.email'), []);
        $nullResponse->assertRedirect(route('password.request'));
        $nullResponse->assertSessionHasErrors(['email' => $expectedError]);
    }

    /**
     * Requerimientos 7 y 8:
     * Validar el formato del correo y exigir el dominio exacto @merida.tecnm.mx.
     * Si el correo no cumple el formato o dominio, mostrar:
     * «Ingresa un correo institucional válido con dominio @merida.tecnm.mx».
     */
    public function test_validation_rejects_invalid_format_and_non_institutional_domain_with_exact_message(): void
    {
        $expectedError = 'Ingresa un correo institucional válido con dominio @merida.tecnm.mx';

        $invalidEmails = [
            'usuario@gmail.com',
            'usuario@tecnm.mx',
            'usuario@cenidet.tecnm.mx',
            'usuario@otra.merida.tecnm.mx',
            'usuario@merida.tecnm.mx.com',
            'sin-arroba-ni-dominio',
            '@merida.tecnm.mx',
            'correo..invalido@merida.tecnm.mx',
        ];

        foreach ($invalidEmails as $invalidEmail) {
            $response = $this->from(route('password.request'))->post(route('password.email'), [
                'email' => $invalidEmail,
            ]);

            $response->assertRedirect(route('password.request'));
            $response->assertSessionHasErrors(['email' => $expectedError]);
        }
    }

    /**
     * Requerimiento 6:
     * Antes de validar y consultar la base de datos, eliminar los espacios externos y convertir el correo a minúsculas.
     */
    public function test_email_normalization_trims_spaces_and_converts_to_lowercase(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'roberto.c@merida.tecnm.mx',
        ]);

        $rawInput = '   ROBERTO.C@MERIDA.TECNM.MX   ';

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $rawInput,
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    /**
     * Requerimientos 9 y 10:
     * Cuando el correo tenga formato válido, mostrar exactamente el mismo mensaje, exista o no una cuenta:
     * «Si el correo está registrado, recibirás un enlace para restablecer tu contraseña».
     * No revelar públicamente si una cuenta existe.
     */
    public function test_identical_generic_response_for_existing_and_nonexistent_accounts(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'existe@merida.tecnm.mx',
        ]);

        $genericMessage = 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña';

        // Caso 1: Correo existente
        $existingResponse = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'existe@merida.tecnm.mx',
        ]);
        $existingResponse->assertRedirect(route('password.request'));
        $existingResponse->assertSessionHasNoErrors();
        $existingResponse->assertSessionHas('status', $genericMessage);
        Notification::assertSentTo($user, ResetPassword::class);

        // Caso 2: Correo inexistente
        $nonexistentResponse = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'no_existe_en_bd@merida.tecnm.mx',
        ]);
        $nonexistentResponse->assertRedirect(route('password.request'));
        $nonexistentResponse->assertSessionHasNoErrors();
        $nonexistentResponse->assertSessionHas('status', $genericMessage);

        // Ambas respuestas son exactamente idénticas en redirección, sesión y mensaje
        $this->assertEquals(
            $existingResponse->getSession()->get('status'),
            $nonexistentResponse->getSession()->get('status')
        );
    }

    /**
     * Requerimiento 11:
     * No crear cuentas, modificar contraseñas ni alterar datos del usuario durante esta solicitud.
     */
    public function test_request_does_not_create_accounts_nor_alter_user_passwords_or_data(): void
    {
        Notification::fake();

        $originalPasswordHash = Hash::make('ClaveOriginalSegura1!');
        $user = User::factory()->create([
            'email' => 'seguro@merida.tecnm.mx',
            'name' => 'Usuario Seguro',
            'phone' => '9998887766',
            'password' => $originalPasswordHash,
        ]);

        $initialUsersCount = User::count();

        // 1. Solicitud para usuario existente
        $this->post(route('password.email'), ['email' => 'seguro@merida.tecnm.mx']);

        $user->refresh();
        $this->assertEquals($initialUsersCount, User::count());
        $this->assertEquals($originalPasswordHash, $user->password);
        $this->assertEquals('Usuario Seguro', $user->name);
        $this->assertEquals('9998887766', $user->phone);

        // 2. Solicitud para usuario no existente
        $this->post(route('password.email'), ['email' => 'nuevo_desconocido@merida.tecnm.mx']);

        $this->assertEquals($initialUsersCount, User::count());
        $this->assertDatabaseMissing('users', ['email' => 'nuevo_desconocido@merida.tecnm.mx']);
    }

    /**
     * Consideración técnica:
     * Protege el endpoint contra abuso mediante limitación de solicitudes sin revelar la existencia de cuentas.
     */
    public function test_rate_limiter_protects_endpoint_against_abuse_without_revealing_accounts(): void
    {
        RateLimiter::clear('password_reset|127.0.0.1');

        // Realizamos 5 solicitudes permitidas
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post(route('password.email'), [
                'email' => "intento{$i}@merida.tecnm.mx",
            ]);
            $response->assertSessionHasNoErrors();
        }

        // El 6to intento consecutivo debe ser bloqueado por limitador de tasa
        $blockedResponse = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'intento6@merida.tecnm.mx',
        ]);

        $blockedResponse->assertRedirect(route('password.request'));
        $blockedResponse->assertSessionHasErrors('email');
    }
}

