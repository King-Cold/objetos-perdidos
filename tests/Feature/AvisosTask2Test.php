<?php

namespace Tests\Feature;

use App\Models\Aviso;
use App\Models\Devolucion;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas automatizadas para la HU: Avisos — Task 2:
 * Implementar el estado de lectura, su persistencia y la navegación segura de los avisos.
 */
class AvisosTask2Test extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Un usuario puede marcar como leído uno de sus avisos.
     */
    public function test_user_can_mark_their_own_notice_as_read(): void
    {
        $user = User::factory()->create();
        $aviso = Aviso::factory()->noLeido()->create([
            'user_id' => $user->id,
            'titulo' => 'Corrección requerida en publicación',
        ]);

        $this->assertFalse($aviso->leido);
        $this->assertNull($aviso->leido_at);

        // Petición PATCH para marcar como leído
        $response = $this->actingAs($user)->patch(route('avisos.marcar-leido', $aviso->id));

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $aviso->refresh();
        $this->assertTrue($aviso->leido);
    }

    /**
     * 2. El estado de lectura y la fecha leido_at se guardan correctamente.
     */
    public function test_read_state_and_read_at_timestamp_are_stored_correctly(): void
    {
        $user = User::factory()->create();
        $aviso = Aviso::factory()->noLeido()->create([
            'user_id' => $user->id,
        ]);

        $momentoAntes = now()->subSeconds(2);

        $this->actingAs($user)->patch(route('avisos.marcar-leido', $aviso->id));

        $aviso->refresh();
        $this->assertTrue($aviso->leido);
        $this->assertNotNull($aviso->leido_at);
        $this->assertTrue($aviso->leido_at->greaterThanOrEqualTo($momentoAntes));
    }

    /**
     * 3. Un usuario puede marcar todos sus avisos pendientes como leídos.
     */
    public function test_user_can_mark_all_pending_notices_as_read(): void
    {
        $user = User::factory()->create();

        $aviso1 = Aviso::factory()->noLeido()->create(['user_id' => $user->id]);
        $aviso2 = Aviso::factory()->noLeido()->create(['user_id' => $user->id]);
        $aviso3 = Aviso::factory()->noLeido()->create(['user_id' => $user->id]);

        $this->assertEquals(3, $user->avisosNoLeidos()->count());

        $response = $this->actingAs($user)->patch(route('avisos.marcar-todos-leidos'));

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertTrue($aviso1->fresh()->leido);
        $this->assertTrue($aviso2->fresh()->leido);
        $this->assertTrue($aviso3->fresh()->leido);

        $this->assertNotNull($aviso1->fresh()->leido_at);
        $this->assertNotNull($aviso2->fresh()->leido_at);
        $this->assertNotNull($aviso3->fresh()->leido_at);

        $this->assertEquals(0, $user->fresh()->avisosNoLeidos()->count());
    }

    /**
     * 4. Los avisos que ya estaban leídos no generan modificaciones innecesarias.
     */
    public function test_already_read_notices_do_not_generate_unnecessary_modifications(): void
    {
        $user = User::factory()->create();

        $fechaOriginalLectura = now()->subDays(3)->startOfSecond();
        $fechaOriginalActualizacion = now()->subDays(3)->startOfSecond();

        $avisoYaLeido = Aviso::factory()->create([
            'user_id' => $user->id,
            'leido' => true,
            'leido_at' => $fechaOriginalLectura,
            'updated_at' => $fechaOriginalActualizacion,
        ]);

        // Intento individual sobre aviso ya leído
        $this->actingAs($user)->patch(route('avisos.marcar-leido', $avisoYaLeido->id));

        $avisoYaLeido->refresh();
        $this->assertEquals($fechaOriginalLectura->timestamp, $avisoYaLeido->leido_at->timestamp);
        $this->assertEquals($fechaOriginalActualizacion->timestamp, $avisoYaLeido->updated_at->timestamp);

        // Intento general marcar todos como leídos
        $this->actingAs($user)->patch(route('avisos.marcar-todos-leidos'));

        $avisoYaLeido->refresh();
        $this->assertEquals($fechaOriginalLectura->timestamp, $avisoYaLeido->leido_at->timestamp);
        $this->assertEquals($fechaOriginalActualizacion->timestamp, $avisoYaLeido->updated_at->timestamp);
    }

    /**
     * 5. Un usuario no puede marcar como leído un aviso de otra cuenta.
     */
    public function test_user_cannot_mark_as_read_a_notice_from_another_account(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $avisoB = Aviso::factory()->noLeido()->create([
            'user_id' => $userB->id,
            'titulo' => 'Aviso confidencial de B',
        ]);

        $response = $this->actingAs($userA)->patch(route('avisos.marcar-leido', $avisoB->id));

        $response->assertForbidden();

        $avisoB->refresh();
        $this->assertFalse($avisoB->leido);
        $this->assertNull($avisoB->leido_at);
    }

    /**
     * 6. Un usuario no autenticado no puede acceder a las acciones.
     */
    public function test_unauthenticated_user_cannot_access_actions(): void
    {
        $user = User::factory()->create();
        $aviso = Aviso::factory()->noLeido()->create(['user_id' => $user->id]);

        $responseIndividual = $this->patch(route('avisos.marcar-leido', $aviso->id));
        $responseIndividual->assertRedirect(route('login'));

        $responseGeneral = $this->patch(route('avisos.marcar-todos-leidos'));
        $responseGeneral->assertRedirect(route('login'));

        $responseIr = $this->get(route('avisos.ir', $aviso->id));
        $responseIr->assertRedirect(route('login'));

        $this->assertFalse($aviso->fresh()->leido);
    }

    /**
     * 7. El contador de avisos no leídos se actualiza correctamente.
     */
    public function test_unread_notices_counter_updates_correctly(): void
    {
        $user = User::factory()->create();

        $aviso1 = Aviso::factory()->noLeido()->create(['user_id' => $user->id]);
        $aviso2 = Aviso::factory()->noLeido()->create(['user_id' => $user->id]);

        $this->assertEquals(2, $user->avisos_no_leidos_count);

        // Vista muestra botón 'Marcar todos como leídos' y conteo 2
        $responseIndex = $this->actingAs($user)->get(route('avisos.index'));
        $responseIndex->assertOk();
        $responseIndex->assertSee('Marcar todos como leídos');
        $responseIndex->assertSee('2 pendientes');

        // Marcar 1 aviso
        $this->actingAs($user)->patch(route('avisos.marcar-leido', $aviso1->id));
        $this->assertEquals(1, $user->fresh()->avisos_no_leidos_count);

        // Marcar el segundo aviso
        $this->actingAs($user)->patch(route('avisos.marcar-leido', $aviso2->id));
        $this->assertEquals(0, $user->fresh()->avisos_no_leidos_count);

        // Al no haber pendientes, el botón de 'Marcar todos' no debe aparecer
        $responseIndexLimpio = $this->actingAs($user)->get(route('avisos.index'));
        $responseIndexLimpio->assertOk();
        $responseIndexLimpio->assertDontSee('Marcar todos como leídos');
    }

    /**
     * 8. Los enlaces de los avisos respetan las autorizaciones existentes.
     */
    public function test_notice_links_respect_existing_authorizations(): void
    {
        $user = User::factory()->create();
        $publicacionPropia = Publicacion::factory()->create([
            'user_id' => $user->id,
            'estado' => 'Aprobada',
        ]);

        $aviso = Aviso::factory()->noLeido()->create([
            'user_id' => $user->id,
            'publicacion_id' => $publicacionPropia->id,
            'enlace_destino' => '/reportes/' . $publicacionPropia->id,
        ]);

        // Usuario consulta el proceso a través del enlace del aviso
        $response = $this->actingAs($user)->get(route('avisos.ir', $aviso->id));

        $response->assertRedirect('/reportes/' . $publicacionPropia->id);

        // Al consultar el proceso, el aviso se marca automáticamente como leído
        $this->assertTrue($aviso->fresh()->leido);
        $this->assertNotNull($aviso->fresh()->leido_at);

        // Siguiendo la redirección al detalle propio, el acceso es autorizado (200 OK)
        $responseDetalle = $this->actingAs($user)->get('/reportes/' . $publicacionPropia->id);
        $responseDetalle->assertOk();
    }

    /**
     * 9. Intentar acceder a un proceso ajeno no permite consultar ni modificar información.
     */
    public function test_attempting_to_access_foreign_process_prevents_consulting_or_modifying(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $publicacionB = Publicacion::factory()->create([
            'user_id' => $userB->id,
            'estado' => 'Pendiente',
        ]);

        $avisoB = Aviso::factory()->noLeido()->create([
            'user_id' => $userB->id,
            'publicacion_id' => $publicacionB->id,
            'enlace_destino' => '/reportes/' . $publicacionB->id,
        ]);

        // Usuario A intenta acceder al aviso de Usuario B mediante route('avisos.ir') -> 403
        $responseIr = $this->actingAs($userA)->get(route('avisos.ir', $avisoB->id));
        $responseIr->assertForbidden();

        // Usuario A intenta acceder directamente a la ruta del reporte ajeno -> 403
        $responseShow = $this->actingAs($userA)->get('/reportes/' . $publicacionB->id);
        $responseShow->assertForbidden();

        // Usuario A intenta acceder a editar el reporte ajeno -> 403
        $responseEdit = $this->actingAs($userA)->get('/reportes/' . $publicacionB->id . '/editar');
        $responseEdit->assertForbidden();

        // Usuario A intenta modificar mediante PUT el reporte ajeno -> 403
        $responseUpdate = $this->actingAs($userA)->put('/reportes/' . $publicacionB->id, [
            'titulo' => 'Intento de hackeo de título',
        ]);
        $responseUpdate->assertForbidden();

        // El reporte ajeno permanece intacto
        $this->assertNotEquals('Intento de hackeo de título', $publicacionB->fresh()->titulo);
    }

    /**
     * 10. Marcar un aviso como leído no modifica el reporte, la publicación ni la devolución relacionada.
     */
    public function test_marking_notice_as_read_does_not_modify_publication_or_return(): void
    {
        $user = User::factory()->create();
        $publicacion = Publicacion::factory()->create([
            'user_id' => $user->id,
            'estado' => 'Aprobada',
            'disponible' => true,
        ]);

        $devolucion = Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $user->id,
            'receptor_nombre' => 'María López',
            'receptor_identificador' => '12345678',
            'fecha_entrega' => now()->format('Y-m-d'),
            'hora_entrega' => '10:00:00',
            'fecha_captura' => now(),
            'explicacion_propiedad' => 'Entrega presencial verificada.',
            'fotografia_ruta' => 'devoluciones/' . $publicacion->id . '/evidencia.jpg',
            'estado' => Devolucion::ESTADO_PENDIENTE,
        ]);

        $aviso = Aviso::factory()->noLeido()->create([
            'user_id' => $user->id,
            'publicacion_id' => $publicacion->id,
            'devolucion_id' => $devolucion->id,
            'enlace_destino' => '/devoluciones/' . $devolucion->id,
        ]);

        $pubTimestampOriginal = $publicacion->fresh()->updated_at;
        $pubEstadoOriginal = $publicacion->fresh()->estado;
        $devTimestampOriginal = $devolucion->fresh()->updated_at;
        $devEstadoOriginal = $devolucion->fresh()->estado;

        // Marcar aviso como leído
        $this->actingAs($user)->patch(route('avisos.marcar-leido', $aviso->id));

        $this->assertTrue($aviso->fresh()->leido);

        // La publicación no se modificó
        $this->assertEquals($pubEstadoOriginal, $publicacion->fresh()->estado);
        $this->assertEquals($pubTimestampOriginal->timestamp, $publicacion->fresh()->updated_at->timestamp);

        // La devolución no se modificó
        $this->assertEquals($devEstadoOriginal, $devolucion->fresh()->estado);
        $this->assertEquals($devTimestampOriginal->timestamp, $devolucion->fresh()->updated_at->timestamp);
    }

    /**
     * 11. Marcar todos como leídos cuando no hay avisos pendientes responde adecuadamente sin errores.
     */
    public function test_marking_all_as_read_when_empty_handles_gracefully(): void
    {
        $user = User::factory()->create();

        // El usuario no tiene avisos pendientes
        $this->assertEquals(0, $user->avisosNoLeidos()->count());

        $response = $this->actingAs($user)->patch(route('avisos.marcar-todos-leidos'));

        $response->assertRedirect();
        $response->assertSessionHas('info');
    }

    /**
     * 12. Navegar a un aviso cuyo registro vinculado ya fue eliminado informa de manera segura y comprensible.
     */
    public function test_navigating_to_notice_with_deleted_resource_displays_safe_warning(): void
    {
        $user = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $user->id,
            'estado' => 'Cancelado',
        ]);

        $aviso = Aviso::factory()->noLeido()->create([
            'user_id' => $user->id,
            'publicacion_id' => $publicacion->id,
            'enlace_destino' => '/reportes/' . $publicacion->id,
        ]);

        // Simular que el reporte fue eliminado definitivamente
        $reporteId = $publicacion->id;
        $publicacion->delete();

        // Enlace conserva el ID eliminado en su destino
        $aviso->update(['enlace_destino' => '/reportes/' . $reporteId]);

        $response = $this->actingAs($user)->get(route('avisos.ir', $aviso->id));

        $response->assertRedirect(route('avisos.index'));
        $response->assertSessionHas('warning', 'El reporte o publicación relacionado ya no se encuentra disponible.');
    }

    /**
     * 13. Las tarjetas muestran «Ver reporte» o «Ver devolución» según el proceso relacionado.
     */
    public function test_notice_displays_ver_reporte_for_publication_and_ver_devolucion_for_return(): void
    {
        $user = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $user->id,
            'estado' => 'Aprobada',
        ]);

        $devolucion = Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $user->id,
            'receptor_nombre' => 'Carlos Pech',
            'receptor_identificador' => '87654321',
            'fecha_entrega' => now()->format('Y-m-d'),
            'hora_entrega' => '11:00:00',
            'fecha_captura' => now(),
            'explicacion_propiedad' => 'Entrega presencial en biblioteca.',
            'fotografia_ruta' => 'devoluciones/' . $publicacion->id . '/evidencia2.jpg',
            'estado' => Devolucion::ESTADO_PENDIENTE,
        ]);

        $avisoReporte = Aviso::factory()->create([
            'user_id' => $user->id,
            'publicacion_id' => $publicacion->id,
            'tipo_evento' => Aviso::TIPO_PUBLICACION_CORRECCION,
            'titulo' => 'Corrección requerida',
            'enlace_destino' => '/reportes/' . $publicacion->id . '/editar',
        ]);

        $avisoDevolucion = Aviso::factory()->create([
            'user_id' => $user->id,
            'devolucion_id' => $devolucion->id,
            'tipo_evento' => Aviso::TIPO_DEVOLUCION_CORRECCION,
            'titulo' => 'Corrección de devolución requerida',
            'enlace_destino' => '/devoluciones/' . $devolucion->id . '/editar',
        ]);

        $this->assertEquals('Ver reporte', $avisoReporte->texto_accion);
        $this->assertEquals('Ver devolución', $avisoDevolucion->texto_accion);

        $response = $this->actingAs($user)->get(route('avisos.index'));
        $response->assertOk();
        $response->assertSee('Ver reporte');
        $response->assertSee('Ver devolución');
    }

    /**
     * 14. Navegación hacia prefijos /publicaciones/ valida propiedad y redirige correctamente.
     */
    public function test_navigating_to_publicaciones_prefix_validates_and_redirects(): void
    {
        $user = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $user->id,
            'estado' => 'Aprobada',
            'disponible' => true,
        ]);

        $aviso = Aviso::factory()->noLeido()->create([
            'user_id' => $user->id,
            'publicacion_id' => null, // Validar extracción vía regex del path
            'tipo_evento' => Aviso::TIPO_PUBLICACION_APROBADA,
            'enlace_destino' => '/publicaciones/' . $publicacion->id,
        ]);

        $response = $this->actingAs($user)->get(route('avisos.ir', $aviso->id));

        $response->assertRedirect('/publicaciones/' . $publicacion->id);
        $this->assertTrue($aviso->fresh()->leido);
    }
}
