<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verifica que la pantalla de registro se renderice correctamente con todos sus elementos.
     */
    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Nombre completo');
        $response->assertSee('Correo electrónico institucional');
        $response->assertSee('Teléfono');
        $response->assertSee('Contraseña');
        $response->assertSee('Confirmación de contraseña');
        $response->assertSeeText('Los campos marcados con * son obligatorios');
        $response->assertSee('@merida.tecnm.mx');
        $response->assertSee('8 y 12 caracteres');
        $response->assertSee('una letra mayúscula');
        $response->assertSee('un número');
        $response->assertSee('un carácter especial');
        $response->assertSee('Crear cuenta');
        $response->assertSee('showPassword');
        $response->assertSee('showConfirmPassword');
    }

    /**
     * HU-01 / Subtarea 4:
     * Verifica que un usuario válido sea creado exitosamente en la base de datos,
     * inicie sesión automáticamente, se redirija al muro principal y se muestre «Registro exitoso».
     */
    public function test_user_account_is_successfully_created_authenticated_and_redirected_to_dashboard_with_notification(): void
    {
        $response = $this->post('/register', [
            'name' => " María José Peña-O'Connor ",
            'email' => ' ESTUDIANTE.1@MERIDA.TECNM.MX ',
            'phone' => ' 0991234567 ',
            'password' => 'TecNM#2026',
            'password_confirmation' => 'TecNM#2026',
        ]);

        $response->assertSessionHasNoErrors();

        // 1. Redirección al muro principal de la plataforma
        $response->assertRedirect(route('dashboard', absolute: false));

        // 2. Notificación en sesión «Registro exitoso»
        $response->assertSessionHas('status', 'Registro exitoso');

        // 3. Verifica persistencia en la tabla users
        $this->assertDatabaseHas('users', [
            'name' => "María José Peña-O'Connor",
            'email' => 'estudiante.1@merida.tecnm.mx',
            'phone' => '0991234567',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $user = User::where('email', 'estudiante.1@merida.tecnm.mx')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('TecNM#2026', $user->password));
        $this->assertNotEquals('TecNM#2026', $user->password);
        $this->assertTrue($user->isUsuarioGeneral());

        // 4. Verifica que el usuario recién registrado haya iniciado sesión automáticamente
        $this->assertAuthenticated();
        $this->assertAuthenticatedAs($user);

        // 5. Verifica que la notificación aparezca en el muro principal
        $dashboardResponse = $this->get(route('dashboard'));
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Registro exitoso');

        // 6. Verifica que el usuario autenticado no pueda volver al formulario de registro (guest middleware)
        $registerResponse = $this->get('/register');
        $registerResponse->assertRedirect(route('dashboard', absolute: false));
    }

    /**
     * Verifica el manejo de errores si ocurre una falla al iniciar sesión automáticamente después de haber creado la cuenta.
     */
    public function test_gracefully_handles_error_during_post_registration_auto_login(): void
    {
        // Forzamos una excepción durante el evento de login simulando falla de sesión
        Event::listen(Login::class, function () {
            throw new \RuntimeException('Falla simulada de sesión');
        });

        $response = $this->post('/register', [
            'name' => 'Usuario Contingencia',
            'email' => 'contingencia@merida.tecnm.mx',
            'phone' => '9998881122',
            'password' => 'TecNM#2026',
            'password_confirmation' => 'TecNM#2026',
        ]);

        // Debe redirigir a la pantalla de login institucional
        $response->assertRedirect(route('login'));
        $response->assertSessionHas(
            'status',
            'Tu cuenta institucional fue creada correctamente. Por favor, inicia sesión con tus credenciales.'
        );

        // La cuenta NO debe ser eliminada y debe existir intacta en la BD
        $this->assertDatabaseHas('users', [
            'email' => 'contingencia@merida.tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);
    }

    /**
     * Verifica que se rechacen correos duplicados de forma insensible a mayúsculas y minúsculas sin modificar la cuenta existente.
     */
    public function test_registration_rejects_duplicate_email_case_insensitively(): void
    {
        // Crear usuario existente
        $originalUser = User::factory()->create([
            'name' => 'Profesor Existente',
            'email' => 'profesor.titular@merida.tecnm.mx',
            'phone' => '9991112233',
        ]);

        // Intento de registro con variaciones en mayúsculas y espacios externos
        $response = $this->post('/register', [
            'name' => 'Intento Duplicado',
            'email' => '  PROFESOR.TITULAR@MERIDA.TECNM.MX  ',
            'phone' => '9999998888',
            'password' => 'TecNM#2026',
            'password_confirmation' => 'TecNM#2026',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Este correo electrónico ya se encuentra registrado',
        ]);

        // Comprobar que no se creó una segunda cuenta
        $this->assertEquals(1, User::where('email', 'profesor.titular@merida.tecnm.mx')->count());

        // Comprobar que la información de la cuenta existente NO fue modificada
        $originalUser->refresh();
        $this->assertEquals('Profesor Existente', $originalUser->name);
        $this->assertEquals('9991112233', $originalUser->phone);
    }

    /**
     * Verifica que una condición de carrera o excepción de unicidad en la BD se capture y maneje de forma controlada.
     */
    public function test_concurrent_unique_constraint_violation_is_gracefully_handled(): void
    {
        // Simulamos condición de carrera: una inserción concurrente antes de que termine de guardarse
        User::creating(function ($user) {
            if ($user->email === 'concurrente@merida.tecnm.mx') {
                User::flushEventListeners();
                User::create([
                    'name' => 'Usuario Concurrente Previsto',
                    'email' => 'concurrente@merida.tecnm.mx',
                    'phone' => '9993332211',
                    'password' => Hash::make('TecNM#2026'),
                    'role' => User::ROLE_USUARIO_GENERAL,
                ]);
            }
        });

        $response = $this->post('/register', [
            'name' => 'Segundo Intento Simultáneo',
            'email' => 'concurrente@merida.tecnm.mx',
            'phone' => '9995556677',
            'password' => 'TecNM#2026',
            'password_confirmation' => 'TecNM#2026',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Este correo electrónico ya se encuentra registrado',
        ]);
    }

    /**
     * Verifica que el rol se asigne obligatoriamente desde el servidor y se ignoren intentos de manipulación de roles privilegiados.
     */
    public function test_registration_always_assigns_usuario_general_and_ignores_tampered_role(): void
    {
        $response = $this->post('/register', [
            'name' => 'Usuario Con Rol Manipulado',
            'email' => 'aspirante.admin@merida.tecnm.mx',
            'phone' => '9997776655',
            'password' => 'TecNM#2026',
            'password_confirmation' => 'TecNM#2026',
            'role' => 'Administrador', // Intento malicioso de escalamiento de privilegios
        ]);

        $response->assertSessionHasNoErrors();

        $user = User::where('email', 'aspirante.admin@merida.tecnm.mx')->first();
        $this->assertNotNull($user);
        $this->assertEquals(User::ROLE_USUARIO_GENERAL, $user->role);
        $this->assertNotEquals('Administrador', $user->role);
    }

    /**
     * Verifica que el teléfono se conserve exactamente como texto preservando ceros iniciales.
     */
    public function test_registration_preserves_leading_zero_in_phone_number(): void
    {
        $response = $this->post('/register', [
            'name' => 'Cero Inicial',
            'email' => 'cero.inicial@merida.tecnm.mx',
            'phone' => '0123456789',
            'password' => 'TecNM#2026',
            'password_confirmation' => 'TecNM#2026',
        ]);

        $response->assertSessionHasNoErrors();

        $user = User::where('email', 'cero.inicial@merida.tecnm.mx')->first();
        $this->assertNotNull($user);
        $this->assertSame('0123456789', $user->phone);
    }

    /**
     * Verifica la validación del nombre completo (caracteres válidos, trim, rechazo de números/símbolos).
     */
    public function test_name_validation_rules(): void
    {
        // Nombre vacío / solo espacios
        $response = $this->post('/register', ['name' => '   ']);
        $response->assertSessionHasErrors(['name']);

        // Nombre compuesto únicamente por números
        $response = $this->post('/register', ['name' => '123456789']);
        $response->assertSessionHasErrors(['name']);

        // Nombre compuesto únicamente por símbolos
        $response = $this->post('/register', ['name' => '!@#$%^&*()']);
        $response->assertSessionHasErrors(['name']);

        // Nombre con números intercalados
        $response = $this->post('/register', ['name' => 'Juan Carlos 123']);
        $response->assertSessionHasErrors(['name']);

        // Nombre válido simple (un solo nombre)
        $response = $this->post('/register', [
            'name' => 'Carlos',
            'email' => 'carlos.simple@merida.tecnm.mx',
            'phone' => '9991234567',
            'password' => 'TecNM#2026',
            'password_confirmation' => 'TecNM#2026',
        ]);
        $response->assertSessionHasNoErrors();
    }

    /**
     * Verifica la validación del correo institucional y su mensaje exacto.
     */
    public function test_email_institutional_domain_validation(): void
    {
        $exactMessage = 'Ingresa un correo institucional válido con dominio @merida.tecnm.mx';

        // Dominio personal (@gmail.com)
        $response = $this->post('/register', ['email' => 'usuario@gmail.com']);
        $response->assertSessionHasErrors([
            'email' => $exactMessage,
        ]);

        // Subdominio no autorizado (@alumnos.merida.tecnm.mx)
        $response = $this->post('/register', ['email' => 'usuario@alumnos.merida.tecnm.mx']);
        $response->assertSessionHasErrors([
            'email' => $exactMessage,
        ]);

        // Otro campus (@conkal.tecnm.mx)
        $response = $this->post('/register', ['email' => 'usuario@conkal.tecnm.mx']);
        $response->assertSessionHasErrors([
            'email' => $exactMessage,
        ]);

        // Dominio genérico (@tecnm.mx)
        $response = $this->post('/register', ['email' => 'usuario@tecnm.mx']);
        $response->assertSessionHasErrors([
            'email' => $exactMessage,
        ]);

        // Correo con espacios internos
        $response = $this->post('/register', ['email' => 'usuario @merida.tecnm.mx']);
        $response->assertSessionHasErrors([
            'email' => $exactMessage,
        ]);

        // Correo sin parte local
        $response = $this->post('/register', ['email' => '@merida.tecnm.mx']);
        $response->assertSessionHasErrors([
            'email' => $exactMessage,
        ]);
    }

    /**
     * Verifica la validación del teléfono a 10 dígitos y su mensaje exacto.
     */
    public function test_phone_ten_digits_validation(): void
    {
        $exactMessage = 'El teléfono debe contener exactamente 10 dígitos, sin espacios ni otros caracteres';

        // Menos de 10 dígitos
        $response = $this->post('/register', ['phone' => '999123456']);
        $response->assertSessionHasErrors(['phone' => $exactMessage]);

        // Más de 10 dígitos
        $response = $this->post('/register', ['phone' => '99912345678']);
        $response->assertSessionHasErrors(['phone' => $exactMessage]);

        // Contiene letras
        $response = $this->post('/register', ['phone' => '999123456a']);
        $response->assertSessionHasErrors(['phone' => $exactMessage]);

        // Con guiones
        $response = $this->post('/register', ['phone' => '999-123-4567']);
        $response->assertSessionHasErrors(['phone' => $exactMessage]);

        // Con espacios
        $response = $this->post('/register', ['phone' => '999 123 4567']);
        $response->assertSessionHasErrors(['phone' => $exactMessage]);

        // Con prefijo internacional +52
        $response = $this->post('/register', ['phone' => '+529991234567']);
        $response->assertSessionHasErrors(['phone' => $exactMessage]);

        // Válido con cero inicial conservado
        $response = $this->post('/register', [
            'name' => 'Ana López',
            'email' => 'ana.telefono@merida.tecnm.mx',
            'phone' => '0991234567',
            'password' => 'TecNM#2026',
            'password_confirmation' => 'TecNM#2026',
        ]);
        $response->assertSessionHasNoErrors();
    }

    /**
     * Verifica la validación de los requisitos de la contraseña.
     */
    public function test_password_complexity_rules(): void
    {
        // Menos de 8 caracteres
        $response = $this->post('/register', ['password' => 'Tec#1']);
        $response->assertSessionHasErrors(['password']);

        // Más de 12 caracteres
        $response = $this->post('/register', ['password' => 'TecNM#2026Largo']);
        $response->assertSessionHasErrors(['password']);

        // Sin letra mayúscula
        $response = $this->post('/register', ['password' => 'tecnm#2026']);
        $response->assertSessionHasErrors(['password' => 'La contraseña debe incluir al menos una letra mayúscula.']);

        // Sin número
        $response = $this->post('/register', ['password' => 'TecNM#Clave']);
        $response->assertSessionHasErrors(['password' => 'La contraseña debe incluir al menos un número.']);

        // Sin carácter especial
        $response = $this->post('/register', ['password' => 'TecNM2026abc']);
        $response->assertSessionHasErrors(['password' => 'La contraseña debe incluir al menos un carácter especial.']);

        // Espacio NO cuenta como carácter especial
        $response = $this->post('/register', ['password' => 'TecNM 2026a']);
        $response->assertSessionHasErrors(['password' => 'La contraseña debe incluir al menos un carácter especial.']);
    }

    /**
     * Verifica que cuando la contraseña incumpla múltiples requisitos simultáneos, se reporten todos juntos.
     */
    public function test_multiple_password_requirement_failures_are_all_reported_together(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Usuario Múltiples Errores',
            'email' => 'multiples@merida.tecnm.mx',
            'phone' => '9991234567',
            'password' => 'abc', // Falla longitud (< 8), sin mayúscula, sin número y sin carácter especial
            'password_confirmation' => 'abc',
        ]);

        $response->assertSessionHasErrors([
            'password' => 'La contraseña debe contener al menos 8 caracteres.',
        ]);
        $response->assertSessionHasErrors([
            'password' => 'La contraseña debe incluir al menos una letra mayúscula.',
        ]);
        $response->assertSessionHasErrors([
            'password' => 'La contraseña debe incluir al menos un número.',
        ]);
        $response->assertSessionHasErrors([
            'password' => 'La contraseña debe incluir al menos un carácter especial.',
        ]);
    }

    /**
     * Verifica la validación de la confirmación de la contraseña y su mensaje exacto.
     */
    public function test_password_confirmation_validation(): void
    {
        $exactMessage = 'La confirmación de contraseña no coincide';

        // Contraseñas distintas
        $response = $this->post('/register', [
            'password' => 'TecNM#2026',
            'password_confirmation' => 'TecNM#9999',
        ]);
        $response->assertSessionHasErrors(['password_confirmation' => $exactMessage]);

        // Diferencia de mayúsculas/minúsculas
        $response = $this->post('/register', [
            'password' => 'TecNM#2026',
            'password_confirmation' => 'tecnm#2026',
        ]);
        $response->assertSessionHasErrors(['password_confirmation' => $exactMessage]);
    }

    /**
     * Verifica que en caso de error se conserven nombre, correo y teléfono, pero no las contraseñas.
     */
    public function test_failed_validation_preserves_old_inputs_except_passwords(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Pedro Sánchez',
            'email' => 'pedro@merida.tecnm.mx',
            'phone' => '9991234567',
            'password' => 'invalida', // Menos de 8 caracteres y sin requisitos
            'password_confirmation' => 'invalida',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['password']);

        // Comprobar que old() tiene los campos seguros
        $this->assertEquals('Pedro Sánchez', session('_old_input.name'));
        $this->assertEquals('pedro@merida.tecnm.mx', session('_old_input.email'));
        $this->assertEquals('9991234567', session('_old_input.phone'));

        // Las contraseñas no deben permanecer
        $this->assertNull(session('_old_input.password'));
        $this->assertNull(session('_old_input.password_confirmation'));
    }

    /**
     * Verifica que el usuario pueda corregir los datos tras un error, reenviar el formulario y completar el registro exitosamente.
     */
    public function test_form_resubmission_after_validation_error_succeeds_and_creates_account(): void
    {
        // 1. Intento inicial con correo no institucional
        $failedResponse = $this->from('/register')->post('/register', [
            'name' => 'Luis Mendoza',
            'email' => 'luis@gmail.com', // Dominio no institucional
            'phone' => '9995554433',
            'password' => 'TecNM#2026',
            'password_confirmation' => 'TecNM#2026',
        ]);

        $failedResponse->assertRedirect('/register');
        $failedResponse->assertSessionHasErrors(['email']);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['name' => 'Luis Mendoza']);

        // Comprobar que los datos seguros se conservan en old()
        $this->assertEquals('Luis Mendoza', session('_old_input.name'));
        $this->assertEquals('luis@gmail.com', session('_old_input.email'));
        $this->assertEquals('9995554433', session('_old_input.phone'));
        $this->assertNull(session('_old_input.password'));

        // 2. Reenvío del formulario con los datos corregidos
        $successResponse = $this->from('/register')->post('/register', [
            'name' => 'Luis Mendoza',
            'email' => 'luis.mendoza@merida.tecnm.mx', // Corregido
            'phone' => '9995554433',
            'password' => 'TecNM#2026',
            'password_confirmation' => 'TecNM#2026',
        ]);

        $successResponse->assertSessionHasNoErrors();
        $successResponse->assertRedirect(route('dashboard', absolute: false));
        $successResponse->assertSessionHas('status', 'Registro exitoso');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'luis.mendoza@merida.tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);
    }

    /**
     * Verifica que ante un fallo en el registro jamás se muestre el mensaje «Registro exitoso» ni se inicie sesión.
     */
    public function test_failed_registration_never_shows_registration_success_message(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Usuario Invalido',
            'email' => 'invalido@gmail.com',
            'phone' => '123', // Incompleto
            'password' => 'corta',
            'password_confirmation' => 'diferente',
        ]);

        $response->assertRedirect('/register');
        $this->assertNotEquals('Registro exitoso', session('status'));
        $this->assertGuest();
    }

    /**
     * Verifica que un error imprevisto en base de datos informe al usuario amigablemente sin exponer detalles técnicos.
     */
    public function test_unexpected_database_error_is_handled_gracefully_without_exposing_technical_details(): void
    {
        // Simulamos un error no controlado durante el evento de creación
        User::creating(function ($user) {
            if ($user->email === 'error.bd@merida.tecnm.mx') {
                throw new \Illuminate\Database\QueryException(
                    'mysql',
                    'INSERT INTO users ...',
                    [],
                    new \Exception('Error de conexión simulado')
                );
            }
        });

        $response = $this->from('/register')->post('/register', [
            'name' => 'Usuario Error BD',
            'email' => 'error.bd@merida.tecnm.mx',
            'phone' => '9991234567',
            'password' => 'TecNM#2026',
            'password_confirmation' => 'TecNM#2026',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHas('error', 'No fue posible completar el registro de tu cuenta en este momento. Por favor, verifica tus datos e inténtalo nuevamente.');
        $this->assertNotEquals('Registro exitoso', session('status'));
        $this->assertGuest();

        // Los campos seguros deben conservarse
        $this->assertEquals('Usuario Error BD', session('_old_input.name'));
        $this->assertEquals('error.bd@merida.tecnm.mx', session('_old_input.email'));
        $this->assertEquals('9991234567', session('_old_input.phone'));
        $this->assertNull(session('_old_input.password'));
    }
}
