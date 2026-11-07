<?php

namespace Tests\Feature;

use App\Models\Aviso;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas automatizadas para la HU: Avisos — Task 1:
 * Crear el apartado privado de avisos y su presentación.
 */
class AvisosTask1Test extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Un usuario autenticado puede acceder al apartado de avisos.
     */
    public function test_authenticated_user_can_access_avisos_section(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('avisos.index'));

        $response->assertOk();
        $response->assertViewIs('avisos.index');
        $response->assertSee('Avisos');
    }

    /**
     * 2. Un visitante sin sesión no puede acceder (debe redirigir a login).
     */
    public function test_unauthenticated_visitor_cannot_access_avisos_section(): void
    {
        $response = $this->get(route('avisos.index'));

        $response->assertRedirect(route('login'));
    }

    /**
     * 3. Un usuario únicamente visualiza sus propios avisos.
     */
    public function test_user_can_only_view_their_own_notices(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $avisoA = Aviso::factory()->create([
            'user_id' => $userA->id,
            'titulo' => 'Aviso personal del usuario A',
            'mensaje' => 'Mensaje exclusivo para el usuario A',
            'folio_reporte' => 'OBJ-20261106-AAA1',
        ]);

        $avisoB = Aviso::factory()->create([
            'user_id' => $userB->id,
            'titulo' => 'Aviso privado del usuario B',
            'mensaje' => 'Mensaje confidencial para el usuario B',
            'folio_reporte' => 'OBJ-20261106-BBB2',
        ]);

        $responseA = $this->actingAs($userA)->get(route('avisos.index'));
        $responseA->assertOk();
        $responseA->assertSee('Aviso personal del usuario A');
        $responseA->assertSee('OBJ-20261106-AAA1');
        $responseA->assertDontSee('Aviso privado del usuario B');
        $responseA->assertDontSee('OBJ-20261106-BBB2');

        $responseB = $this->actingAs($userB)->get(route('avisos.index'));
        $responseB->assertOk();
        $responseB->assertSee('Aviso privado del usuario B');
        $responseB->assertSee('OBJ-20261106-BBB2');
        $responseB->assertDontSee('Aviso personal del usuario A');
        $responseB->assertDontSee('OBJ-20261106-AAA1');
    }

    /**
     * 4. Los avisos se muestran del más reciente al más antiguo.
     */
    public function test_notices_are_displayed_from_newest_to_oldest(): void
    {
        $user = User::factory()->create();

        $avisoAntiguo = Aviso::factory()->create([
            'user_id' => $user->id,
            'titulo' => 'Aviso 1 Antiguo',
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $avisoMedio = Aviso::factory()->create([
            'user_id' => $user->id,
            'titulo' => 'Aviso 2 Intermedio',
            'created_at' => Carbon::now()->subDays(1),
        ]);

        $avisoReciente = Aviso::factory()->create([
            'user_id' => $user->id,
            'titulo' => 'Aviso 3 Muy Reciente',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($user)->get(route('avisos.index'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Aviso 3 Muy Reciente',
            'Aviso 2 Intermedio',
            'Aviso 1 Antiguo',
        ]);
    }

    /**
     * 5. Se distingue correctamente entre avisos leídos y no leídos.
     */
    public function test_visual_and_textual_differentiation_between_read_and_unread_notices(): void
    {
        $user = User::factory()->create();

        $avisoNoLeido = Aviso::factory()->noLeido()->create([
            'user_id' => $user->id,
            'titulo' => 'Publicación requiere ajustes',
        ]);

        $avisoLeido = Aviso::factory()->leido()->create([
            'user_id' => $user->id,
            'titulo' => 'Publicación anterior atendida',
        ]);

        $response = $this->actingAs($user)->get(route('avisos.index'));

        $response->assertOk();
        $response->assertSee('No leído');
        $response->assertSee('Leído');
        $response->assertSee('Publicación requiere ajustes');
        $response->assertSee('Publicación anterior atendida');
    }

    /**
     * 6. Se muestra «No tienes avisos» cuando no existen registros.
     */
    public function test_empty_state_displays_exact_message_when_no_notices_exist(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('avisos.index'));

        $response->assertOk();
        $response->assertSee('No tienes avisos');
    }

    /**
     * 7. La vista se integra correctamente con la navegación existente para usuario general y administrador.
     */
    public function test_navigation_bar_integrates_avisos_link_for_both_roles(): void
    {
        $generalUser = User::factory()->create(['role' => User::ROLE_USUARIO_GENERAL]);
        $adminUser = User::factory()->admin()->create();

        // Usuario general ve el enlace Avisos en navegación
        $responseUser = $this->actingAs($generalUser)->get(route('muro'));
        $responseUser->assertOk();
        $responseUser->assertSee(route('avisos.index'));
        $responseUser->assertSee('Avisos');

        // Administrador ve el enlace Avisos en navegación
        $responseAdmin = $this->actingAs($adminUser)->get(route('admin.dashboard'));
        $responseAdmin->assertOk();
        $responseAdmin->assertSee(route('avisos.index'));
        $responseAdmin->assertSee('Avisos');
    }

    /**
     * 8. Los avisos muestran folio, mensaje, motivo autorizado y enlace de destino.
     */
    public function test_notice_displays_folio_message_motive_and_destination_link(): void
    {
        $user = User::factory()->create();

        $aviso = Aviso::factory()->create([
            'user_id' => $user->id,
            'tipo_evento' => Aviso::TIPO_PUBLICACION_CORRECCION,
            'titulo' => 'Corrección solicitada por biblioteca',
            'folio_reporte' => 'OBJ-20261106-9999',
            'mensaje' => 'Se requiere corregir las observaciones antes de publicar.',
            'motivo_autorizado' => 'Favor de redactar con más detalle la ubicación exacta.',
            'enlace_destino' => '/reportes/5/editar',
        ]);

        $response = $this->actingAs($user)->get(route('avisos.index'));

        $response->assertOk();
        $response->assertSee('Corrección requerida');
        $response->assertSee('OBJ-20261106-9999');
        $response->assertSee('Se requiere corregir las observaciones antes de publicar.');
        $response->assertSee('Favor de redactar con más detalle la ubicación exacta.');
        $response->assertSee('/reportes/5/editar');
        $response->assertSee('Ver reporte');
    }
}

