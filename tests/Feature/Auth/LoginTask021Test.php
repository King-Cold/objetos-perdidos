<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTask021Test extends TestCase
{
    use RefreshDatabase;

    /**
     * CA-01 y CA-33: Formulario con campos Correo y Contraseña, etiquetas visibles, asteriscos y diseño responsivo.
     */
    public function test_login_screen_renders_with_labels_asterisks_and_inputs(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);

        // Comprobar etiquetas visibles con asterisco de obligatoriedad (CA-01)
        $response->assertSee('Correo electrónico', false);
        $response->assertSee('Contraseña', false);
        $response->assertSee('*', false);
        $response->assertSee('Los campos marcados con', false);

        // Comprobar presencia de inputs y atributos requeridos
        $response->assertSee('id="email"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('id="password"', false);
        $response->assertSee('name="password"', false);

        // Comprobar que no hay contaminación de objetos JS en valores y que el botón tiene texto
        $response->assertDontSee('[object HTMLInputElement]', false);
        $response->assertSee('Iniciar sesión', false);
    }

    /**
     * CA-04: Botón de visibilidad de contraseña con icono, nombre accesible y que no envíe el formulario.
     */
    public function test_login_password_visibility_toggle_is_accessible_and_does_not_submit(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);

        // El input debe iniciar como type="password" (oculta de forma predeterminada)
        $response->assertSee('type="password"', false);

        // El botón debe ser type="button" para no enviar el formulario
        $response->assertSee('type="button"', false);

        // Debe contar con nombre accesible (aria-label y title)
        $response->assertSee('Mostrar contraseña', false);
        $response->assertSee('Ocultar contraseña', false);
    }

    /**
     * CA-02: Impedir envío cuando los campos están vacíos, indicando qué información debe proporcionarse.
     */
    public function test_login_rejects_empty_fields_with_specific_messages(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => '',
            'password' => '',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'email' => 'El correo electrónico es obligatorio.',
            'password' => 'La contraseña es obligatoria.',
        ]);

        $this->assertGuest();
    }

    /**
     * CA-02: Impedir envío cuando los campos contienen únicamente espacios en blanco.
     */
    public function test_login_rejects_whitespace_only_fields(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => '    ',
            'password' => '   ',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'email' => 'El correo electrónico es obligatorio.',
            'password' => 'La contraseña es obligatoria.',
        ]);

        $this->assertGuest();
    }

    /**
     * CA-03: Eliminar espacios externos del correo y compararlo sin distinguir mayúsculas y minúsculas.
     */
    public function test_login_normalizes_email_with_trim_and_case_insensitivity(): void
    {
        $user = User::factory()->create([
            'email' => 'alicia.gonzalez@merida.tecnm.mx',
            'password' => Hash::make('ClaveSegura2026#'),
        ]);

        // Envío con mayúsculas y espacios externos
        $response = $this->post('/login', [
            'email' => '   ALICIA.GONZALEZ@MERIDA.TECNM.MX   ',
            'password' => 'ClaveSegura2026#',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    /**
     * CA-03: La contraseña se comprueba exactamente como fue escrita, distinguiendo mayúsculas, minúsculas y símbolos, sin recortar.
     */
    public function test_login_verifies_password_strictly_without_trimming(): void
    {
        // Contraseña que deliberadamente tiene espacios exteriores y caracteres mixtos
        $passwordWithSpaces = '  Clave Con Espacios 2026!  ';

        $user = User::factory()->create([
            'email' => 'carlos.m@merida.tecnm.mx',
            'password' => Hash::make($passwordWithSpaces),
        ]);

        // Intento 1: Contraseña recortada sin espacios exteriores -> debe fallar
        $trimmedAttempt = $this->from('/login')->post('/login', [
            'email' => 'carlos.m@merida.tecnm.mx',
            'password' => 'Clave Con Espacios 2026!',
        ]);
        $trimmedAttempt->assertSessionHasErrors('email');
        $this->assertGuest();

        // Intento 2: Contraseña con mayúsculas cambiadas -> debe fallar
        $wrongCaseAttempt = $this->from('/login')->post('/login', [
            'email' => 'carlos.m@merida.tecnm.mx',
            'password' => '  clave con espacios 2026!  ',
        ]);
        $wrongCaseAttempt->assertSessionHasErrors('email');
        $this->assertGuest();

        // Intento 3: Contraseña exacta tal cual -> debe autenticar con éxito
        $exactAttempt = $this->post('/login', [
            'email' => 'carlos.m@merida.tecnm.mx',
            'password' => $passwordWithSpaces,
        ]);
        $this->assertAuthenticatedAs($user);
    }

    /**
     * CA-05: Mostrar el mismo mensaje idéntico cuando el correo no existe o la contraseña es incorrecta.
     */
    public function test_login_shows_identical_error_message_for_nonexistent_user_and_wrong_password(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'registrado@merida.tecnm.mx',
            'password' => Hash::make('ClaveValida2026!'),
        ]);

        // Caso A: Correo no registrado
        $responseNonexistent = $this->from('/login')->post('/login', [
            'email' => 'no.existe@merida.tecnm.mx',
            'password' => 'CualquierClave2026!',
        ]);

        $responseNonexistent->assertSessionHasErrors([
            'email' => 'Correo electrónico o contraseña incorrectos.',
        ]);

        // Caso B: Correo registrado pero contraseña incorrecta
        $responseWrongPassword = $this->from('/login')->post('/login', [
            'email' => $existingUser->email,
            'password' => 'ClaveEquivocada999!',
        ]);

        $responseWrongPassword->assertSessionHasErrors([
            'email' => 'Correo electrónico o contraseña incorrectos.',
        ]);

        $this->assertGuest();
    }

    /**
     * CA-06: Conservar el correo capturado y limpiar la contraseña después de un intento fallido.
     */
    public function test_login_preserves_old_email_and_clears_password_on_failure(): void
    {
        $attemptEmail = 'intento.fallido@merida.tecnm.mx';
        $attemptPassword = 'PasswordErroneo123!';

        $response = $this->from('/login')->post('/login', [
            'email' => $attemptEmail,
            'password' => $attemptPassword,
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('_old_input.email', $attemptEmail);
        $response->assertSessionMissing('_old_input.password');

        // Al renderizar la pantalla tras el fallo:
        $followResponse = $this->get('/login');
        $followResponse->assertSee($attemptEmail);
        $followResponse->assertDontSee($attemptPassword);
    }

    /**
     * CA-17: Enlaces visibles y funcionales a «Crear cuenta» y «Recuperar contraseña».
     */
    public function test_login_screen_has_functional_links_to_register_and_password_recovery(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);

        // Enlace visible a Crear cuenta
        $response->assertSee(route('register'));
        $response->assertSee('Crear cuenta');

        // Enlace visible a Recuperar contraseña
        $response->assertSee(route('password.request'));
        $response->assertSee('¿Olvidaste tu contraseña?');

        // Comprobación de que las rutas destino existen y responden 200 (no están rotas ni son ficticias)
        $registerPage = $this->get(route('register'));
        $registerPage->assertStatus(200);

        $recoveryPage = $this->get(route('password.request'));
        $recoveryPage->assertStatus(200);
    }
}

