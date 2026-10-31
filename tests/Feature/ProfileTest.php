<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_name_and_email_cannot_be_updated_via_profile_route(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@merida.tecnm.mx',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User Modificado',
                'email' => 'modificado@merida.tecnm.mx',
            ]);

        $response->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Original Name', $user->name);
        $this->assertSame('original@merida.tecnm.mx', $user->email);
    }

    public function test_profile_information_is_displayed_as_readonly_without_update_form(): void
    {
        $user = User::factory()->create([
            'name' => 'Estudiante TecNM',
            'email' => 'estudiante@merida.tecnm.mx',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $response->assertSee('value="Estudiante TecNM"', false);
        $response->assertSee('value="estudiante@merida.tecnm.mx"', false);
        $response->assertSee('readonly', false);
        $response->assertSee('disabled', false);

        // No debe existir formulario PATCH para actualización de perfil
        $response->assertDontSee('value="patch"', false);
    }

    public function test_password_update_section_is_not_present_in_profile_view(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $response->assertDontSee('Update Password');
        $response->assertDontSee('update_password_password');
        $response->assertDontSee('update_password_current_password');
    }

    public function test_delete_account_section_is_not_present_in_profile_view(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $response->assertDontSee('Delete Account');
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
