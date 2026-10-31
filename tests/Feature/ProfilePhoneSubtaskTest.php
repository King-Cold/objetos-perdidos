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
}
