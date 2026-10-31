<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePhoneSubtaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_visitor_cannot_access_profile_page(): void
    {
        $response = $this->get('/profile');

        $response->assertRedirect('/login');
    }

    public function test_profile_page_displays_phone_section_with_authenticated_users_current_phone(): void
    {
        $user = User::factory()->create([
            'phone' => '9991234567',
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();

        // 1. Título
        $response->assertSee('Teléfono de contacto');

        // 2. Etiqueta del campo
        $response->assertSee('Teléfono de contacto');

        // 3. Mostrar automáticamente el número actualmente almacenado
        $response->assertSee('value="9991234567"', false);

        // 4. Indicación visual de obligatorio y atributo required
        $response->assertSee('Obligatorio');
        $response->assertSee('required', false);

        // 5. Texto exacto de instrucciones/formato
        $response->assertSee('Ingresa 10 dígitos, sin espacios ni otros caracteres');

        // 6. Botones Guardar cambios y Cancelar
        $response->assertSee('Guardar cambios');
        $response->assertSee('Cancelar');

        // Formato apropiado de campo (no type="number")
        $response->assertSee('type="tel"', false);
        $response->assertSee('inputmode="numeric"', false);
        $response->assertDontSee('type="number"', false);
    }

    public function test_phone_is_treated_as_string_and_preserves_leading_zeros(): void
    {
        $user = User::factory()->create([
            'phone' => '0123456789',
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertSee('value="0123456789"', false);
    }

    public function test_existing_profile_forms_remain_present_and_unaltered(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario TecNM',
            'email' => 'usuario@merida.tecnm.mx',
            'phone' => '9998887766',
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();

        // Profile Information
        $response->assertSee('Profile Information');
        $response->assertSee('value="Usuario TecNM"', false);
        $response->assertSee('value="usuario@merida.tecnm.mx"', false);

        // Update Password ya no debe estar presente en Mi perfil
        $response->assertDontSee('Update Password');

        // Delete Account no debe estar presente en Mi perfil
        $response->assertDontSee('Delete Account');
    }

    public function test_user_phone_in_database_is_not_altered_by_navigating_or_viewing_profile(): void
    {
        $originalPhone = '0987654321';
        $user = User::factory()->create([
            'phone' => $originalPhone,
        ]);

        $this->actingAs($user)->get('/profile');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => $originalPhone,
        ]);
    }

    public function test_authenticated_user_can_update_phone_number_with_ten_digits(): void
    {
        $user = User::factory()->create([
            'phone' => '9991112233',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'phone' => '9994445566',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile')
            ->assertSessionHas('status', 'Teléfono de contacto actualizado correctamente.');

        $user->refresh();
        $this->assertSame('9994445566', $user->phone);
    }

    public function test_phone_update_preserves_leading_zeros(): void
    {
        $user = User::factory()->create([
            'phone' => '9991112233',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'phone' => '0123456789',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('0123456789', $user->phone);
    }

    public function test_phone_update_rejects_invalid_formats(): void
    {
        $user = User::factory()->create([
            'phone' => '9991112233',
        ]);

        $invalidPhones = [
            '12345',         // menos de 10 dígitos
            '12345678901',   // más de 10 dígitos
            '999-123-4567',  // con guiones
            '999 123 4567',  // con espacios
            '999abcdef0',    // letras
            '999#123*45',    // caracteres especiales
            '',              // vacío
        ];

        foreach ($invalidPhones as $invalidPhone) {
            $response = $this
                ->actingAs($user)
                ->from('/profile')
                ->patch('/profile', [
                    'phone' => $invalidPhone,
                ]);

            $response->assertSessionHasErrors('phone');
            $this->assertSame('9991112233', $user->fresh()->phone);
        }
    }

    public function test_phone_update_only_updates_authenticated_user_and_does_not_affect_others(): void
    {
        $userA = User::factory()->create(['phone' => '9991112233']);
        $userB = User::factory()->create(['phone' => '9998887766']);

        $response = $this
            ->actingAs($userA)
            ->patch('/profile', [
                'phone' => '9995556677',
            ]);

        $response->assertSessionHasNoErrors();

        $this->assertSame('9995556677', $userA->fresh()->phone);
        $this->assertSame('9998887766', $userB->fresh()->phone);
    }

    public function test_phone_update_cannot_tamper_name_email_password_or_role(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Seguro',
            'email' => 'seguro@merida.tecnm.mx',
            'phone' => '9991112233',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $originalPasswordHash = $user->password;

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'phone' => '9999998888',
                'name' => 'Hacker Name',
                'email' => 'hacked@example.com',
                'role' => User::ROLE_ADMIN,
                'password' => 'newpassword123',
            ]);

        $response->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('9999998888', $user->phone);
        $this->assertSame('Usuario Seguro', $user->name);
        $this->assertSame('seguro@merida.tecnm.mx', $user->email);
        $this->assertSame(User::ROLE_USUARIO_GENERAL, $user->role);
        $this->assertSame($originalPasswordHash, $user->password);
    }

    public function test_status_alert_popup_is_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['status' => 'Teléfono de contacto actualizado correctamente.'])
            ->get('/profile');

        $response->assertOk();
        $response->assertSee('Teléfono de contacto actualizado correctamente.');
        $response->assertSee('Swal.fire', false);
    }

    public function test_error_alert_popup_and_previous_phone_are_retained_on_failure(): void
    {
        $originalPhone = '9991112233';
        $user = User::factory()->create([
            'phone' => $originalPhone,
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'phone' => '123',
            ]);

        $response->assertRedirect('/profile');
        $response->assertSessionHasErrors('phone');

        // Comprobar que en la base de datos se conserva intacto el teléfono anterior
        $this->assertSame($originalPhone, $user->fresh()->phone);

        // Al consultar la vista con los errores en sesión, se renderiza la ventana emergente de error
        $viewResponse = $this->actingAs($user)
            ->withSession(['errors' => session('errors')])
            ->get('/profile');

        $viewResponse->assertOk();
        $viewResponse->assertSee('showPhoneErrorNotification', false);
        $viewResponse->assertSee('No se pudo actualizar el teléfono');
    }
}
