<?php

namespace Tests\Feature;

use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteCancelacionTask065Test extends TestCase
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
     * CA-31, CA-33: La confirmación de cancelación está disponible para estados permitidos (Pendiente y Requiere corrección).
     */
    public function test_author_can_access_cancel_confirmation_view_for_allowed_states(): void
    {
        $user = User::factory()->create();

        // 1. Reporte en estado 'Pendiente de aprobación'
        $repPendiente = Publicacion::factory()->pendiente()->create([
            'user_id' => $user->id,
            'nombre' => 'Laptop HP Gris',
        ]);
        $this->attachPhoto($repPendiente);

        $resPendiente = $this->actingAs($user)->get(route('reportes.cancelar', $repPendiente->id));
        $resPendiente->assertStatus(200);
        $resPendiente->assertSee('¿Estás seguro de cancelar este reporte?');
        $resPendiente->assertSee('el reporte quedará cancelado y fuera del muro institucional');
        $resPendiente->assertSee('Confirmar cancelación');
        $resPendiente->assertSee('Volver');
        $resPendiente->assertSee($repPendiente->folio);

        // 2. Reporte en estado 'Requiere corrección'
        $repCorreccion = Publicacion::factory()->requiereCorreccion()->create([
            'user_id' => $user->id,
            'nombre' => 'Mochila Morada',
        ]);
        $this->attachPhoto($repCorreccion);

        $resCorreccion = $this->actingAs($user)->get(route('reportes.cancelar', $repCorreccion->id));
        $resCorreccion->assertStatus(200);
        $resCorreccion->assertSee('Confirmar cancelación');
    }

    /**
     * CA-31, CA-40: La cancelación está bloqueada para estados no permitidos (ya cancelado, bajo resguardo confirmado o con incidencia).
     */
    public function test_cancel_is_blocked_for_disallowed_states_and_shows_exact_state_change_message(): void
    {
        $user = User::factory()->create();

        // 1. Reporte ya cancelado
        $repCancelado = Publicacion::factory()->cancelado()->create([
            'user_id' => $user->id,
        ]);
        $this->attachPhoto($repCancelado);

        $res1 = $this->actingAs($user)->get(route('reportes.cancelar', $repCancelado->id));
        $res1->assertRedirect(route('reportes.mis-reportes'));
        $res1->assertSessionHas('warning', 'El estado del reporte cambió. Consulta su información actual antes de continuar');

        // 2. Reporte bajo resguardo confirmado de biblioteca
        $repResguardo = Publicacion::factory()->pendiente()->create([
            'user_id' => $user->id,
            'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO,
        ]);
        $this->attachPhoto($repResguardo);

        $res2 = $this->actingAs($user)->get(route('reportes.cancelar', $repResguardo->id));
        $res2->assertRedirect(route('reportes.mis-reportes'));
        $res2->assertSessionHas('warning', 'El estado del reporte cambió. Consulta su información actual antes de continuar');

        // 3. Reporte con incidencia bloqueante
        $repIncidencia = Publicacion::factory()->pendiente()->create([
            'user_id' => $user->id,
            'tiene_incidencia' => true,
        ]);
        $this->attachPhoto($repIncidencia);

        $res3 = $this->actingAs($user)->get(route('reportes.cancelar', $repIncidencia->id));
        $res3->assertRedirect(route('reportes.mis-reportes'));
        $res3->assertSessionHas('warning', 'El estado del reporte cambió. Consulta su información actual antes de continuar');
    }

    /**
     * CT-01, CT-02: Un usuario general no puede acceder ni cancelar reportes de otros usuarios.
     */
    public function test_general_user_cannot_cancel_another_users_report(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $reporte = Publicacion::factory()->pendiente()->create([
            'user_id' => $owner->id,
            'nombre' => 'Tablet Samsung',
        ]);
        $this->attachPhoto($reporte);

        // Intento de GET a pantalla de cancelación
        $resGet = $this->actingAs($intruder)->get(route('reportes.cancelar', $reporte->id));
        $resGet->assertStatus(403);

        // Intento de POST de cancelación
        $resPost = $this->actingAs($intruder)->post(route('reportes.cancel', $reporte->id), [
            'motivo_cancelacion' => 'Intento malicioso de cancelación ajena',
        ]);
        $resPost->assertStatus(403);

        $reporte->refresh();
        $this->assertEquals(Publicacion::ESTADO_PENDIENTE, $reporte->estado);
    }

    /**
     * CA-32: Exige motivo de cancelación y rechaza valores vacíos o compuestos únicamente por espacios.
     */
    public function test_requires_cancellation_motive_and_rejects_empty_or_whitespace_values(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->pendiente()->create([
            'user_id' => $user->id,
        ]);
        $this->attachPhoto($reporte);

        // 1. Motivo vacío
        $res1 = $this->actingAs($user)->post(route('reportes.cancel', $reporte->id), [
            'motivo_cancelacion' => '',
        ]);
        $res1->assertSessionHasErrors('motivo_cancelacion');

        // 2. Motivo con solo espacios en blanco
        $res2 = $this->actingAs($user)->post(route('reportes.cancel', $reporte->id), [
            'motivo_cancelacion' => '     ',
        ]);
        $res2->assertSessionHasErrors('motivo_cancelacion');

        $reporte->refresh();
        $this->assertEquals(Publicacion::ESTADO_PENDIENTE, $reporte->estado);
    }

    /**
     * CA-34: La acción «Volver» descarta la cancelación y conserva el estado y contenido íntegros.
     */
    public function test_volver_action_discards_cancellation_and_preserves_report_intact(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->requiereCorreccion()->create([
            'user_id' => $user->id,
            'nombre' => 'Mochila Negra Intacta',
        ]);
        $this->attachPhoto($reporte);

        // La vista de confirmación contiene el enlace a Mis Reportes
        $response = $this->actingAs($user)->get(route('reportes.cancelar', $reporte->id));
        $response->assertSee(route('reportes.mis-reportes'));

        // El usuario regresa a Mis Reportes sin enviar cancelación
        $resList = $this->actingAs($user)->get(route('reportes.mis-reportes'));
        $resList->assertOk();

        $reporte->refresh();
        $this->assertEquals(Publicacion::ESTADO_REQUIERE_CORRECCION, $reporte->estado);
        $this->assertNull($reporte->motivo_cancelacion);
        $this->assertNull($reporte->fecha_cancelacion);
    }

    /**
     * CA-35, CA-36: Cancelación válida deja el reporte como «Cancelado», registra motivo y fecha, conserva contenido y muestra mensaje exacto.
     */
    public function test_valid_cancellation_sets_cancelado_state_records_motive_and_timestamp_and_displays_exact_message(): void
    {
        $user = User::factory()->create();
        $originalCapture = Carbon::now(Publicacion::TIMEZONE)->subDays(3);
        $originalDesc = 'Descripción original completa del objeto que debe permanecer intacta tras la cancelación.';

        $reporte = Publicacion::factory()->pendiente()->create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261028-0001',
            'nombre' => 'Memoria USB Kingston 32GB',
            'descripcion' => $originalDesc,
            'fecha_captura' => $originalCapture,
            'fecha_hallazgo' => $originalCapture->format('Y-m-d'),
            'area_zona' => 'Edificio A',
            'ubicacion_especifica' => 'Mesa 1',
        ]);
        $this->attachPhoto($reporte);

        $motivoTexto = 'El objeto fue entregado directamente a su legítimo dueño.';

        $response = $this->actingAs($user)->post(route('reportes.cancel', $reporte->id), [
            'motivo_cancelacion' => $motivoTexto,
        ]);

        // Redirección con mensaje exacto (CA-36)
        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('status', 'El reporte se canceló correctamente');

        $reporte->refresh();

        // CA-35: Estado a Cancelado, disponible false, motivo y fecha registrados
        $this->assertEquals(Publicacion::ESTADO_CANCELADO, $reporte->estado);
        $this->assertFalse((bool) $reporte->disponible);
        $this->assertEquals($motivoTexto, $reporte->motivo_cancelacion);
        $this->assertNotNull($reporte->fecha_cancelacion);

        // Conservación del contenido y metadatos protegidos
        $this->assertEquals('OBJ-20261028-0001', $reporte->folio);
        $this->assertEquals($user->id, $reporte->user_id);
        $this->assertEquals($originalDesc, $reporte->descripcion);
        $this->assertEquals($originalCapture->toDateTimeString(), $reporte->fecha_captura->toDateTimeString());

        // En Mis Reportes aparece como 'Cancelado' y sin opciones de edición ni reenvío
        $resList = $this->actingAs($user)->get(route('reportes.mis-reportes'));
        $resList->assertSee('Cancelado');
        $resList->assertSee($motivoTexto);
        $resList->assertDontSee(route('reportes.edit', $reporte->id));
        $resList->assertDontSee(route('reportes.cancelar', $reporte->id));
    }

    /**
     * CA-37: Reportes cancelados quedan completamente excluidos del Muro, búsquedas y detalle general.
     */
    public function test_cancelled_reports_are_excluded_from_muro_and_public_detail(): void
    {
        $user = User::factory()->create();

        $reporte = Publicacion::factory()->cancelado()->create([
            'user_id' => $user->id,
            'nombre' => 'Sombrilla Verde Olivo',
            'disponible' => false,
            'motivo_cancelacion' => 'Objeto reclamado',
            'fecha_cancelacion' => Carbon::now(Publicacion::TIMEZONE),
        ]);
        $this->attachPhoto($reporte);

        // 1. Excluido del Muro público
        $resMuro = $this->get(route('muro'));
        $resMuro->assertDontSee('Sombrilla Verde Olivo');

        // 2. Detalle general público bloqueado con redirección
        $resShow = $this->get(route('publicaciones.show', $reporte->id));
        $resShow->assertRedirect(route('muro'));
        $resShow->assertSessionHas('warning', 'Esta publicación no está disponible para consulta');
    }

    /**
     * CA-38: Una publicación disponible no tiene cancelación directa; muestra orientación hacia biblioteca.
     */
    public function test_available_publication_does_not_have_direct_cancellation_and_shows_library_orientation(): void
    {
        $user = User::factory()->create();
        $approvalDate = Carbon::now(Publicacion::TIMEZONE)->subDays(2);

        $reporte = Publicacion::factory()->create([
            'user_id' => $user->id,
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => $approvalDate,
            'fecha_vencimiento' => $approvalDate->copy()->addDays(14),
            'nombre' => 'Cartera de Cuero Café',
        ]);
        $this->attachPhoto($reporte);

        // En Mis Reportes NO tiene botón de cancelación directa
        $resList = $this->actingAs($user)->get(route('reportes.mis-reportes'));
        $resList->assertDontSee(route('reportes.cancelar', $reporte->id));

        // Intento directo por URL es bloqueado y redirige con la orientación debida
        $resGet = $this->actingAs($user)->get(route('reportes.cancelar', $reporte->id));
        $resGet->assertRedirect(route('reportes.mis-reportes'));
        $resGet->assertSessionHas('warning', 'Para cancelar una publicación disponible en el muro, por favor acude a la biblioteca institucional o contacta al personal administrativo encargado.');

        // Intento de envío POST es igualmente bloqueado
        $resPost = $this->actingAs($user)->post(route('reportes.cancel', $reporte->id), [
            'motivo_cancelacion' => 'Intento de cancelar una aprobada disponible',
        ]);
        $resPost->assertRedirect(route('reportes.mis-reportes'));
        $resPost->assertSessionHas('warning', 'Para cancelar una publicación disponible en el muro, por favor acude a la biblioteca institucional o contacta al personal administrativo encargado.');

        $reporte->refresh();
        $this->assertEquals(Publicacion::ESTADO_APROBADA, $reporte->estado);
        $this->assertTrue((bool) $reporte->disponible);
    }

    /**
     * CA-39: Cancelar no altera devoluciones, incidencias ni evidencias de resguardo.
     */
    public function test_cancel_does_not_alter_returns_incidents_or_custody(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->pendiente()->create([
            'user_id' => $user->id,
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'estado_devolucion' => null,
            'tiene_incidencia' => false,
        ]);
        $this->attachPhoto($reporte);

        $this->actingAs($user)->post(route('reportes.cancel', $reporte->id), [
            'motivo_cancelacion' => 'Cancelación de prueba para verificar integridad de procesos',
        ]);

        $reporte->refresh();
        $this->assertEquals(Publicacion::RESGUARDO_AUTOR, $reporte->situacion_resguardo);
        $this->assertNull($reporte->estado_devolucion);
        $this->assertFalse((bool) $reporte->tiene_incidencia);
    }

    /**
     * CA-40: Si el estado cambia entre la apertura del formulario y el envío, muestra mensaje exacto y no registra cancelación.
     */
    public function test_state_change_between_open_and_submit_shows_exact_message_without_false_operations(): void
    {
        $user = User::factory()->create();

        // 1. Se abre inicialmente en 'Pendiente de aprobación'
        $reporte = Publicacion::factory()->pendiente()->create([
            'user_id' => $user->id,
            'nombre' => 'Cargador Tipo C',
        ]);
        $this->attachPhoto($reporte);

        // Simulamos que antes de que el usuario envíe el formulario, biblioteca registra recepción física bajo resguardo
        $reporte->situacion_resguardo = Publicacion::RESGUARDO_CONFIRMADO;
        $reporte->save();

        // El usuario envía la cancelación
        $response = $this->actingAs($user)->post(route('reportes.cancel', $reporte->id), [
            'motivo_cancelacion' => 'Motivo enviado tras cambio concurrente de estado',
        ]);

        // Redirección con mensaje exacto institucional (CA-40)
        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('warning', 'El estado del reporte cambió. Consulta su información actual antes de continuar');

        $reporte->refresh();
        // El estado NO pasó a Cancelado
        $this->assertEquals(Publicacion::ESTADO_PENDIENTE, $reporte->estado);
        $this->assertNull($reporte->motivo_cancelacion);
    }
}

