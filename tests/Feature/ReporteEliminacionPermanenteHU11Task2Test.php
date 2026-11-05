<?php

namespace Tests\Feature;

use App\Models\DecisionModeracion;
use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteEliminacionPermanenteHU11Task2Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper para asociar una fotografía base a la publicación.
     */
    protected function attachPhoto(Publicacion $publicacion): Fotografia
    {
        return Fotografia::create([
            'publicacion_id' => $publicacion->id,
            'ruta' => 'hallazgos/test_' . uniqid() . '.jpg',
            'nombre_original' => 'foto.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
            'es_principal' => true,
        ]);
    }

    /**
     * 1. Requerimiento: El usuario debe tener una sesión autenticada.
     */
    public function test_unauthenticated_visitor_cannot_attempt_deletion(): void
    {
        $owner = User::factory()->create();
        $reporte = Publicacion::factory()->cancelado()->create([
            'user_id' => $owner->id,
        ]);
        $this->attachPhoto($reporte);

        $response = $this->delete(route('reportes.destroy', $reporte->id));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('publicaciones', ['id' => $reporte->id]);
    }

    /**
     * Escenario 1: El autor intenta eliminar su propia publicación cancelada y elegible.
     * En la Subtarea 2, el servidor valida la autorización y elegibilidad; la eliminación física corresponde a la Subtarea 3.
     */
    public function test_author_can_validate_deletion_for_own_cancelled_and_eligible_report(): void
    {
        $author = User::factory()->create();

        $reporte = Publicacion::factory()->cancelado()->create([
            'user_id' => $author->id,
            'nombre' => 'Mochila Nike Elegible',
            'folio' => 'OBJ-20261104-0010',
            'motivo_cancelacion' => 'Cancelado por el autor sin procesos pendientes',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'devolucion_en_proceso' => false,
            'incidencia_abierta' => false,
            'tiene_incidencia' => false,
            'estado_devolucion' => null,
        ]);
        $this->attachPhoto($reporte);

        $response = $this->actingAs($author)->delete(route('reportes.destroy', $reporte->id));

        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('status');
        $response->assertSessionMissing('warning');

        // Al avanzar a la Subtarea 3, la publicación se elimina permanentemente de la base de datos
        $response->assertSessionHas('status', 'La publicación se eliminó permanentemente');
        $this->assertDatabaseMissing('publicaciones', [
            'id' => $reporte->id,
        ]);
    }

    /**
     * Escenario 2: Un usuario intenta eliminar una publicación ajena.
     * Debe impedirse con 403 Forbidden incluso si se accede directamente a la ruta.
     */
    public function test_user_cannot_delete_another_users_publication(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $reporte = Publicacion::factory()->cancelado()->create([
            'user_id' => $owner->id,
            'nombre' => 'Laptop HP Ajena',
        ]);
        $this->attachPhoto($reporte);

        // Intento por DELETE
        $resDelete = $this->actingAs($intruder)->delete(route('reportes.destroy', $reporte->id));
        $resDelete->assertStatus(403);

        // Intento directo por POST al alias eliminar
        $resPost = $this->actingAs($intruder)->post(route('reportes.eliminar', $reporte->id));
        $resPost->assertStatus(403);

        // La publicación en la base de datos permanece intacta
        $this->assertDatabaseHas('publicaciones', [
            'id' => $reporte->id,
            'user_id' => $owner->id,
            'estado' => Publicacion::ESTADO_CANCELADO,
        ]);
    }

    /**
     * Escenario 2b: Un administrador tampoco puede eliminar publicaciones ajenas desde el flujo de usuario.
     */
    public function test_admin_cannot_delete_another_users_publication_via_author_route(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $reporte = Publicacion::factory()->cancelado()->create([
            'user_id' => $owner->id,
            'nombre' => 'Cartera Ajena',
        ]);
        $this->attachPhoto($reporte);

        $response = $this->actingAs($admin)->delete(route('reportes.destroy', $reporte->id));
        $response->assertStatus(403);

        $this->assertDatabaseHas('publicaciones', ['id' => $reporte->id]);
    }

    /**
     * Escenario 3: Se intenta eliminar una publicación con estado distinto de «Cancelado».
     * Debe bloquearse la operación y mostrar un mensaje claro de que el estado cambió.
     */
    public function test_cannot_delete_publication_with_state_other_than_cancelado(): void
    {
        $user = User::factory()->create();

        // 1. Estado Pendiente de aprobación
        $repPendiente = Publicacion::factory()->pendiente()->create([
            'user_id' => $user->id,
            'nombre' => 'Calculadora Pendiente',
        ]);
        $this->attachPhoto($repPendiente);

        $res1 = $this->actingAs($user)->delete(route('reportes.destroy', $repPendiente->id));
        $res1->assertRedirect(route('reportes.mis-reportes'));
        $res1->assertSessionHas('warning', 'El estado del reporte cambió. Consulta su información actual antes de continuar');
        $this->assertDatabaseHas('publicaciones', ['id' => $repPendiente->id, 'estado' => Publicacion::ESTADO_PENDIENTE]);

        // 2. Estado Aprobada
        $repAprobada = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Reloj Aprobado',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
        ]);
        $this->attachPhoto($repAprobada);

        $res2 = $this->actingAs($user)->delete(route('reportes.destroy', $repAprobada->id));
        $res2->assertRedirect(route('reportes.mis-reportes'));
        $res2->assertSessionHas('warning', 'El estado del reporte cambió. Consulta su información actual antes de continuar');
        $this->assertDatabaseHas('publicaciones', ['id' => $repAprobada->id, 'estado' => Publicacion::ESTADO_APROBADA]);

        // 3. Estado Requiere corrección
        $repCorreccion = Publicacion::factory()->requiereCorreccion()->create([
            'user_id' => $user->id,
            'nombre' => 'Audífonos en Corrección',
        ]);
        $this->attachPhoto($repCorreccion);

        $res3 = $this->actingAs($user)->delete(route('reportes.destroy', $repCorreccion->id));
        $res3->assertRedirect(route('reportes.mis-reportes'));
        $res3->assertSessionHas('warning', 'El estado del reporte cambió. Consulta su información actual antes de continuar');
        $this->assertDatabaseHas('publicaciones', ['id' => $repCorreccion->id, 'estado' => Publicacion::ESTADO_REQUIERE_CORRECCION]);

        // 4. Estado Devuelto
        $repDevuelto = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Objeto Devuelto',
            'estado' => Publicacion::ESTADO_DEVUELTO,
        ]);
        $this->attachPhoto($repDevuelto);

        $res4 = $this->actingAs($user)->delete(route('reportes.destroy', $repDevuelto->id));
        $res4->assertRedirect(route('reportes.mis-reportes'));
        $res4->assertSessionHas('warning', 'El estado del reporte cambió. Consulta su información actual antes de continuar');
        $this->assertDatabaseHas('publicaciones', ['id' => $repDevuelto->id, 'estado' => Publicacion::ESTADO_DEVUELTO]);
    }

    /**
     * Escenario 4A: La publicación tiene devoluciones asociadas o en proceso.
     */
    public function test_cannot_delete_publication_with_return_process(): void
    {
        $user = User::factory()->create();

        // 1. Devolución en proceso
        $repDevolucionEnProceso = Publicacion::factory()->cancelado()->create([
            'user_id' => $user->id,
            'nombre' => 'Celular con Devolución',
            'devolucion_en_proceso' => true,
        ]);
        $this->attachPhoto($repDevolucionEnProceso);

        $res1 = $this->actingAs($user)->delete(route('reportes.destroy', $repDevolucionEnProceso->id));
        $res1->assertRedirect(route('reportes.mis-reportes'));
        $res1->assertSessionHas('warning');
        $this->assertStringContainsString('devolución', (string) session('warning'));
        $this->assertDatabaseHas('publicaciones', ['id' => $repDevolucionEnProceso->id]);

        // 2. Registro de estado de devolución
        $repEstadoDevolucion = Publicacion::factory()->cancelado()->create([
            'user_id' => $user->id,
            'nombre' => 'Llaves Verificadas',
            'estado_devolucion' => 'Pendiente de verificación',
        ]);
        $this->attachPhoto($repEstadoDevolucion);

        $res2 = $this->actingAs($user)->delete(route('reportes.destroy', $repEstadoDevolucion->id));
        $res2->assertRedirect(route('reportes.mis-reportes'));
        $res2->assertSessionHas('warning');
        $this->assertStringContainsString('devolución', (string) session('warning'));
        $this->assertDatabaseHas('publicaciones', ['id' => $repEstadoDevolucion->id]);
    }

    /**
     * Escenario 4B: La publicación tiene incidencias abiertas o registradas.
     */
    public function test_cannot_delete_publication_with_incidents(): void
    {
        $user = User::factory()->create();

        // 1. Incidencia abierta
        $repIncidenciaAbierta = Publicacion::factory()->cancelado()->create([
            'user_id' => $user->id,
            'nombre' => 'Tableta con Disputa',
            'incidencia_abierta' => true,
        ]);
        $this->attachPhoto($repIncidenciaAbierta);

        $res1 = $this->actingAs($user)->delete(route('reportes.destroy', $repIncidenciaAbierta->id));
        $res1->assertRedirect(route('reportes.mis-reportes'));
        $res1->assertSessionHas('warning');
        $this->assertStringContainsString('incidencia', (string) session('warning'));
        $this->assertDatabaseHas('publicaciones', ['id' => $repIncidenciaAbierta->id]);

        // 2. Tiene incidencia
        $repTieneIncidencia = Publicacion::factory()->cancelado()->create([
            'user_id' => $user->id,
            'nombre' => 'Mochila en Incidencia',
            'tiene_incidencia' => true,
        ]);
        $this->attachPhoto($repTieneIncidencia);

        $res2 = $this->actingAs($user)->delete(route('reportes.destroy', $repTieneIncidencia->id));
        $res2->assertRedirect(route('reportes.mis-reportes'));
        $res2->assertSessionHas('warning');
        $this->assertStringContainsString('incidencia', (string) session('warning'));
        $this->assertDatabaseHas('publicaciones', ['id' => $repTieneIncidencia->id]);
    }

    /**
     * Escenario 4C: La publicación tiene registros de resguardo físico en biblioteca.
     */
    public function test_cannot_delete_publication_with_physical_custody_in_library(): void
    {
        $user = User::factory()->create();

        $repResguardo = Publicacion::factory()->cancelado()->create([
            'user_id' => $user->id,
            'nombre' => 'Libro Bajo Resguardo',
            'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO,
        ]);
        $this->attachPhoto($repResguardo);

        $response = $this->actingAs($user)->delete(route('reportes.destroy', $repResguardo->id));

        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('warning');
        $this->assertStringContainsString('resguardo físico en biblioteca', (string) session('warning'));
        $this->assertDatabaseHas('publicaciones', ['id' => $repResguardo->id]);
    }

    /**
     * Escenario 5: La publicación fue cancelada administrativamente y requiere conservación.
     */
    public function test_cannot_delete_publication_cancelled_administratively(): void
    {
        $author = User::factory()->create();
        $admin = User::factory()->admin()->create();

        // 1. Cancelada por moderación administrativa (registrada en decisiones de moderación)
        $repAdminCancel = Publicacion::factory()->cancelado()->create([
            'user_id' => $author->id,
            'nombre' => 'Cámara Fotográfica Duplicada',
            'folio' => 'OBJ-20261104-0020',
        ]);
        $this->attachPhoto($repAdminCancel);

        DecisionModeracion::create([
            'publicacion_id' => $repAdminCancel->id,
            'user_id' => $admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_CANCELADA,
            'motivo' => 'Cancelado administrativamente por reporte duplicado con entrega física',
            'fecha_decision' => Carbon::now(Publicacion::TIMEZONE),
        ]);

        $res1 = $this->actingAs($author)->delete(route('reportes.destroy', $repAdminCancel->id));

        $res1->assertRedirect(route('reportes.mis-reportes'));
        $res1->assertSessionHas('warning');
        $this->assertStringContainsString('cancelada administrativamente', (string) session('warning'));
        $this->assertDatabaseHas('publicaciones', ['id' => $repAdminCancel->id]);

        // 2. Registro con folio de referencia duplicado
        $repDuplicado = Publicacion::factory()->cancelado()->create([
            'user_id' => $author->id,
            'nombre' => 'Reloj Duplicado',
            'folio_referencia_duplicado' => 'OBJ-20261101-0001',
        ]);
        $this->attachPhoto($repDuplicado);

        $res2 = $this->actingAs($author)->delete(route('reportes.destroy', $repDuplicado->id));

        $res2->assertRedirect(route('reportes.mis-reportes'));
        $res2->assertSessionHas('warning');
        $this->assertStringContainsString('cancelada administrativamente', (string) session('warning'));
        $this->assertDatabaseHas('publicaciones', ['id' => $repDuplicado->id]);
    }

    /**
     * Escenario 5b: La publicación es referenciada por otra como duplicado y debe conservarse como antecedente.
     */
    public function test_cannot_delete_publication_referenced_by_another_as_duplicate(): void
    {
        $author = User::factory()->create();

        $repOriginal = Publicacion::factory()->cancelado()->create([
            'user_id' => $author->id,
            'folio' => 'OBJ-20261104-ORIG',
            'nombre' => 'Mochila Original',
        ]);
        $this->attachPhoto($repOriginal);

        // Otra publicación documenta a este folio como referencia de duplicado
        Publicacion::factory()->cancelado()->create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261104-DUP',
            'folio_referencia_duplicado' => $repOriginal->folio,
        ]);

        $response = $this->actingAs($author)->delete(route('reportes.destroy', $repOriginal->id));

        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('warning');
        $this->assertDatabaseHas('publicaciones', ['id' => $repOriginal->id]);
    }

    /**
     * Verificación del modelo Publicacion: métodos de regla de negocio aislados.
     */
    public function test_model_elegibility_methods_work_accurately(): void
    {
        $author = User::factory()->create();

        $repElegible = Publicacion::factory()->cancelado()->create([
            'user_id' => $author->id,
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'devolucion_en_proceso' => false,
            'incidencia_abierta' => false,
            'tiene_incidencia' => false,
            'estado_devolucion' => null,
        ]);

        $this->assertTrue($repElegible->isCancelado());
        $this->assertFalse($repElegible->hasProcesosOAntecedentesProtegidos());
        $this->assertTrue($repElegible->puedeEliminarsePermanentemente());
        $this->assertNull($repElegible->getMotivoRestriccionEliminacion());

        // Con incidencia
        $repElegible->tiene_incidencia = true;
        $this->assertTrue($repElegible->hasProcesosOAntecedentesProtegidos());
        $this->assertFalse($repElegible->puedeEliminarsePermanentemente());
        $this->assertNotNull($repElegible->getMotivoRestriccionEliminacion());
    }
}

