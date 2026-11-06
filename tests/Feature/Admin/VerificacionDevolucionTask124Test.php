<?php

namespace Tests\Feature\Admin;

use App\Events\DevolucionAprobada;
use App\Events\DevolucionCorreccionSolicitada;
use App\Events\DevolucionReenviadaRevision;
use App\Models\DecisionModeracion;
use App\Models\Devolucion;
use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pruebas automatizadas para HU-12 / Tarea 12.4:
 * Integrar vencimiento, privacidad e incidencias (HU-14, HU-15, HU-17, HU-18).
 */
class VerificacionDevolucionTask124Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * Helper para crear una publicación vencida con devolución registrada antes del vencimiento.
     */
    private function crearDevolucionConPublicacionVencida(array $pubAttrs = [], array $devAttrs = []): array
    {
        $autor = User::factory()->create(['role' => User::ROLE_USUARIO_GENERAL]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $otroUsuario = User::factory()->create(['role' => User::ROLE_USUARIO_GENERAL]);

        $ahoraMerida = Carbon::now(Publicacion::TIMEZONE);

        // Vigencia ordinaria de 14 días: Aprobada hace 18 días, vencida hace 4 días
        $fechaAprobacion = $ahoraMerida->copy()->subDays(18);
        $fechaVencimiento = $fechaAprobacion->copy()->addDays(14); // Hace 4 días

        $publicacion = Publicacion::factory()->create(array_merge([
            'user_id' => $autor->id,
            'nombre' => 'Laptop Lenovo ThinkPad Negra',
            'descripcion' => 'Laptop encontrada en biblioteca central con cargador.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'devolucion_en_proceso' => true,
            'estado_devolucion' => Devolucion::ESTADO_PENDIENTE,
            'fecha_primera_aprobacion' => $fechaAprobacion,
            'fecha_aprobacion' => $fechaAprobacion,
            'fecha_vencimiento' => $fechaVencimiento,
            'fecha_hallazgo' => $ahoraMerida->copy()->subDays(20)->format('Y-m-d'),
            'hora_hallazgo' => '10:00',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'area_zona' => 'Biblioteca Campus Poniente',
            'incidencia_abierta' => false,
            'tiene_incidencia' => false,
        ], $pubAttrs));

        // Fotografía del hallazgo
        Fotografia::create([
            'publicacion_id' => $publicacion->id,
            'ruta' => 'fotografias/laptop_' . $publicacion->id . '.jpg',
            'nombre_original' => 'laptop.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 20480,
            'es_principal' => true,
        ]);

        // Fotografía de evidencia privada de entrega
        $fotoEvidencia = UploadedFile::fake()->image('evidencia_entrega.jpg', 600, 400);
        $rutaEvidencia = $fotoEvidencia->storeAs('devoluciones/' . $publicacion->id, 'evidencia_entrega.jpg', 'local');

        // Devolución registrada ANTES del vencimiento (hace 6 días, cuando vencía en 2 días)
        $devolucion = Devolucion::create(array_merge([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Carlos Chan Medina',
            'receptor_identificador' => '19080999',
            'fecha_entrega' => $ahoraMerida->copy()->subDays(7)->format('Y-m-d'),
            'hora_entrega' => '11:30',
            'fecha_captura' => $ahoraMerida->copy()->subDays(6),
            'explicacion_propiedad' => 'El receptor presentó factura de compra y credencial de estudiante vigente.',
            'fotografia_ruta' => $rutaEvidencia,
            'fotografia_nombre_original' => 'evidencia_entrega.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 18000,
            'estado' => Devolucion::ESTADO_PENDIENTE,
        ], $devAttrs));

        return [$autor, $admin, $otroUsuario, $publicacion, $devolucion, $fechaVencimiento];
    }

    // =========================================================================
    // 1. REVISIÓN Y DECISIONES TRAS EL VENCIMIENTO DE LA PUBLICACIÓN
    // =========================================================================

    /**
     * 01. La devolución registrada antes del vencimiento puede consultarse en la cola
     * administrativa tras los 14 días ordinarios de vigencia.
     */
    public function test_01_devolucion_registrada_antes_de_vencer_puede_revisarse_tras_vencimiento_de_14_dias(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionConPublicacionVencida();

        $this->assertTrue($publicacion->isVencida(), 'La publicación debe encontrarse vencida.');

        // Administrador consulta la cola
        $responseIndex = $this->actingAs($admin)->get(route('admin.devoluciones.index'));
        $responseIndex->assertOk();
        $responseIndex->assertSee($publicacion->folio);
        $responseIndex->assertSee('Laptop Lenovo ThinkPad Negra');
        $responseIndex->assertSee($autor->name);

        // Administrador consulta el detalle privado
        $responseShow = $this->actingAs($admin)->get(route('admin.devoluciones.show', $devolucion->id));
        $responseShow->assertOk();
        $responseShow->assertSee('Laptop Lenovo ThinkPad Negra');
        $responseShow->assertSee('Carlos Chan Medina');
        $responseShow->assertSee('19080999');
        $responseShow->assertSee('Aprobar devolución');
        $responseShow->assertSee('Solicitar corrección');
    }

    /**
     * 02. El administrador puede aprobar la devolución aunque la publicación haya vencido,
     * marcándola como 'Devuelto' y conservándola fuera del muro.
     */
    public function test_02_administrador_puede_aprobar_devolucion_de_publicacion_vencida(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionConPublicacionVencida();

        $response = $this->actingAs($admin)->post(route('admin.devoluciones.aprobar', $devolucion->id));
        $response->assertRedirect(route('admin.devoluciones.show', $devolucion->id));
        $response->assertSessionHas('status');

        $devolucion->refresh();
        $publicacion->refresh();

        $this->assertEquals(Devolucion::ESTADO_VERIFICADA, $devolucion->estado);
        $this->assertEquals($admin->id, $devolucion->admin_id);
        $this->assertNotNull($devolucion->fecha_resolucion);

        $this->assertEquals(Publicacion::ESTADO_DEVUELTO, $publicacion->estado);
        $this->assertFalse((bool) $publicacion->disponible);
        $this->assertFalse((bool) $publicacion->devolucion_en_proceso);
        $this->assertEquals(Devolucion::ESTADO_VERIFICADA, $publicacion->estado_devolucion);

        // Registro de decisión en historial
        $this->assertDatabaseHas('decisiones_moderacion', [
            'publicacion_id' => $publicacion->id,
            'user_id' => $admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_DEVOLUCION_APROBADA,
        ]);
    }

    /**
     * 03. El administrador puede solicitar corrección de una publicación vencida.
     */
    public function test_03_administrador_puede_solicitar_correccion_de_publicacion_vencida(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionConPublicacionVencida();

        $motivo = 'La credencial del receptor no muestra con claridad el número de matrícula.';

        $response = $this->actingAs($admin)->post(route('admin.devoluciones.solicitar-correccion', $devolucion->id), [
            'motivo' => $motivo,
        ]);
        $response->assertRedirect(route('admin.devoluciones.show', $devolucion->id));
        $response->assertSessionHas('status');

        $devolucion->refresh();
        $publicacion->refresh();

        $this->assertEquals(Devolucion::ESTADO_REQUIERE_CORRECCION, $devolucion->estado);
        $this->assertEquals($motivo, $devolucion->motivo_correccion);
        $this->assertEquals($admin->id, $devolucion->admin_id);

        $this->assertEquals(Devolucion::ESTADO_REQUIERE_CORRECCION, $publicacion->estado_devolucion);
        $this->assertFalse((bool) $publicacion->disponible);
    }

    /**
     * 04. El autor original puede corregir y reenviar su devolución después de que venció la publicación.
     */
    public function test_04_autor_puede_corregir_y_reenviar_devolucion_tras_vencimiento_de_la_publicacion(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionConPublicacionVencida();
        $devolucion->update([
            'estado' => Devolucion::ESTADO_REQUIERE_CORRECCION,
            'motivo_correccion' => 'Por favor actualiza la explicación de la comprobación.',
            'admin_id' => $admin->id,
        ]);
        $publicacion->update(['estado_devolucion' => Devolucion::ESTADO_REQUIERE_CORRECCION]);

        // Autor puede ver el formulario de edición
        $responseEdit = $this->actingAs($autor)->get(route('devoluciones.edit', $devolucion->id));
        $responseEdit->assertOk();
        $responseEdit->assertSee('Corregir devolución');
        $responseEdit->assertSee('Por favor actualiza la explicación de la comprobación.');

        // Autor envía la corrección
        $responseUpdate = $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => 'Carlos Chan Medina',
            'receptor_identificador' => '19080999',
            'fecha_entrega' => $devolucion->fecha_entrega->format('Y-m-d'),
            'hora_entrega' => '11:30',
            'explicacion_propiedad' => 'Explicación ampliada: El receptor mostró credencial institucional vigente y desbloqueó la laptop con su contraseña.',
        ]);

        $responseUpdate->assertRedirect(route('devoluciones.show', $devolucion->id));
        $responseUpdate->assertSessionHas('status');

        $devolucion->refresh();
        $publicacion->refresh();

        $this->assertEquals(Devolucion::ESTADO_PENDIENTE, $devolucion->estado);
        $this->assertNull($devolucion->motivo_correccion);
        $this->assertNotNull($devolucion->fecha_envio_revision);

        $this->assertEquals(Devolucion::ESTADO_PENDIENTE, $publicacion->estado_devolucion);
        $this->assertFalse((bool) $publicacion->disponible);
    }

    /**
     * 05. El reenvío de la corrección NO renueva la vigencia de 14 días ni modifica la fecha de vencimiento.
     */
    public function test_05_reenvio_de_devolucion_vencida_no_renueva_los_14_dias_ni_la_fecha_de_vencimiento(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion, $fechaVencimientoOriginal] = $this->crearDevolucionConPublicacionVencida();
        $devolucion->update([
            'estado' => Devolucion::ESTADO_REQUIERE_CORRECCION,
            'motivo_correccion' => 'Requiere aclaración.',
            'admin_id' => $admin->id,
        ]);
        $publicacion->update(['estado_devolucion' => Devolucion::ESTADO_REQUIERE_CORRECCION]);

        $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => 'Carlos Chan Medina',
            'receptor_identificador' => '19080999',
            'fecha_entrega' => $devolucion->fecha_entrega->format('Y-m-d'),
            'hora_entrega' => '11:30',
            'explicacion_propiedad' => 'Se corrigió la explicación conforme a la observación administrativa.',
        ]);

        $publicacion->refresh();

        // La fecha de vencimiento permanece intacta (no se agregaron 14 días adicionales)
        $this->assertEquals(
            $fechaVencimientoOriginal->format('Y-m-d H:i:s'),
            $publicacion->fecha_vencimiento->format('Y-m-d H:i:s'),
            'La fecha de vencimiento no debe ser alterada tras un reenvío.'
        );
        $this->assertTrue($publicacion->isVencida());
    }

    /**
     * 06. La publicación en proceso de verificación o corrección permanece siempre fuera del muro y búsquedas.
     */
    public function test_06_publicacion_en_devolucion_permanece_siempre_fuera_del_muro_y_busquedas(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionConPublicacionVencida();

        // 1. En el muro público general
        $resMuro = $this->get(route('muro'));
        $resMuro->assertOk();
        $resMuro->assertDontSee($publicacion->folio);
        $resMuro->assertDontSee('Laptop Lenovo ThinkPad Negra');

        // 2. En la búsqueda de publicaciones
        $resBusqueda = $this->get(route('muro', ['buscar' => 'ThinkPad']));
        $resBusqueda->assertOk();
        $resBusqueda->assertDontSee($publicacion->folio);

        // 3. En el detalle público directo
        $resDetallePublico = $this->get('/publicaciones/' . $publicacion->id);
        $resDetallePublico->assertRedirect();
        $this->assertFalse($publicacion->isDisponible());
    }

    /**
     * 07. La fecha y hora de entrega física no cambian automáticamente si no fueron editadas.
     */
    public function test_07_fecha_de_entrega_fisica_no_cambia_automaticamente(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionConPublicacionVencida([], [
            'estado' => Devolucion::ESTADO_REQUIERE_CORRECCION,
            'motivo_correccion' => 'Observación menor.',
        ]);

        $fechaOriginal = $devolucion->fecha_entrega->format('Y-m-d');
        $horaOriginal = substr((string) $devolucion->hora_entrega, 0, 5);

        $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => 'Carlos Chan Medina Corregido',
            'receptor_identificador' => '19080999',
            'fecha_entrega' => $fechaOriginal,
            'hora_entrega' => $horaOriginal,
            'explicacion_propiedad' => 'Nueva explicación.',
        ]);

        $devolucion->refresh();

        $this->assertEquals($fechaOriginal, $devolucion->fecha_entrega->format('Y-m-d'));
        $this->assertEquals($horaOriginal, substr((string) $devolucion->hora_entrega, 0, 5));
    }

    // =========================================================================
    // 2. PRIVACIDAD DE DATOS Y EVIDENCIAS FOTOGRÁFICAS
    // =========================================================================

    /**
     * 08. Los datos privados del receptor y de entrega no se exponen en respuestas públicas.
     */
    public function test_08_datos_privados_del_receptor_no_se_exponen_en_respuestas_publicas(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionConPublicacionVencida();

        $resMuro = $this->get(route('muro'));
        $resMuro->assertDontSee('Carlos Chan Medina');
        $resMuro->assertDontSee('19080999');
        $resMuro->assertDontSee('factura de compra');
        $resMuro->assertDontSee('evidencia_entrega.jpg');
    }

    /**
     * 09. Usuario ajeno autenticado recibe 403 Forbidden al consultar seguimiento y evidencia.
     */
    public function test_09_usuario_ajeno_recibe_403_al_consultar_seguimiento_y_evidencia(): void
    {
        [$autor, $admin, $otroUsuario, $publicacion, $devolucion] = $this->crearDevolucionConPublicacionVencida();

        $resShow = $this->actingAs($otroUsuario)->get(route('devoluciones.show', $devolucion->id));
        $resShow->assertStatus(403);

        $resEvidencia = $this->actingAs($otroUsuario)->get(route('devoluciones.evidencia', $devolucion->id));
        $resEvidencia->assertStatus(403);

        $resAdminShow = $this->actingAs($otroUsuario)->get(route('admin.devoluciones.show', $devolucion->id));
        $resAdminShow->assertStatus(403);
    }

    /**
     * 10. Visitante no autenticado es redirigido al login.
     */
    public function test_10_visitante_no_autenticado_es_redirigido_al_login(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionConPublicacionVencida();

        $this->get(route('devoluciones.show', $devolucion->id))->assertRedirect(route('login'));
        $this->get(route('devoluciones.evidencia', $devolucion->id))->assertRedirect(route('login'));
        $this->get(route('admin.devoluciones.index'))->assertRedirect(route('login'));
        $this->get(route('admin.devoluciones.show', $devolucion->id))->assertRedirect(route('login'));
    }

    /**
     * 11. El receptor con cuenta de usuario NO obtiene acceso por ese simple hecho.
     */
    public function test_11_receptor_con_cuenta_no_obtiene_acceso_al_seguimiento_ni_evidencia(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionConPublicacionVencida();

        $usuarioReceptor = User::factory()->create([
            'name' => 'Carlos Chan Medina',
            'email' => 'carlos.chan@campus.tecnm.mx',
        ]);

        $resShow = $this->actingAs($usuarioReceptor)->get(route('devoluciones.show', $devolucion->id));
        $resShow->assertStatus(403);

        $resFoto = $this->actingAs($usuarioReceptor)->get(route('devoluciones.evidencia', $devolucion->id));
        $resFoto->assertStatus(403);
    }

    /**
     * 12. La fotografía sustituida es físicamente eliminada y su archivo deja de ser accesible.
     */
    public function test_12_fotografia_de_evidencia_sustituida_es_fisicamente_eliminada(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionConPublicacionVencida([], [
            'estado' => Devolucion::ESTADO_REQUIERE_CORRECCION,
            'motivo_correccion' => 'Sustituye la fotografía por una más nítida.',
        ]);

        $rutaOriginal = $devolucion->fotografia_ruta;
        $this->assertTrue(Storage::disk('local')->exists($rutaOriginal));

        $nuevaFoto = UploadedFile::fake()->image('nueva_evidencia_nitida.png', 800, 600);

        $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => 'Carlos Chan Medina',
            'receptor_identificador' => '19080999',
            'fecha_entrega' => $devolucion->fecha_entrega->format('Y-m-d'),
            'hora_entrega' => '11:30',
            'explicacion_propiedad' => 'Nueva foto adjuntada.',
            'fotografia' => $nuevaFoto,
        ]);

        $devolucion->refresh();

        // El archivo anterior fue eliminado físicamente
        $this->assertFalse(Storage::disk('local')->exists($rutaOriginal), 'El archivo de imagen anterior debe ser eliminado.');
        $this->assertTrue(Storage::disk('local')->exists($devolucion->fotografia_ruta));
    }

    // =========================================================================
    // 3. COMPATIBILIDAD CON INCIDENCIAS (HU-17), HU-18 Y AVISOS (HU-14)
    // =========================================================================

    /**
     * 13. Se impide aprobar si existe una incidencia activa en el reporte (HU-17).
     */
    public function test_13_aprobacion_bloqueada_si_existe_incidencia_activa(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionConPublicacionVencida([
            'incidencia_abierta' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.devoluciones.aprobar', $devolucion->id));
        $response->assertRedirect(route('admin.devoluciones.show', $devolucion->id));
        $response->assertSessionHas('error');

        $devolucion->refresh();
        $this->assertEquals(Devolucion::ESTADO_PENDIENTE, $devolucion->estado, 'La devolución no debe aprobarse con incidencia activa.');

        // Caso B: Reporte marcado en estado_devolucion 'En incidencia'
        $publicacion->update(['incidencia_abierta' => false, 'estado_devolucion' => 'En incidencia']);
        $response2 = $this->actingAs($admin)->post(route('admin.devoluciones.aprobar', $devolucion->id));
        $response2->assertRedirect(route('admin.devoluciones.show', $devolucion->id));
        $response2->assertSessionHas('error');

        $devolucion->refresh();
        $this->assertEquals(Devolucion::ESTADO_PENDIENTE, $devolucion->estado);
    }

    /**
     * 14. Las devoluciones clasificadas como cierre directo desde biblioteca quedan excluidas
     * de la cola administrativa (HU-18).
     */
    public function test_14_exclusion_de_devoluciones_clasificadas_como_cierre_directo_biblioteca(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionConPublicacionVencida([
            'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.devoluciones.index'));
        $response->assertOk();
        $response->assertDontSee($publicacion->folio);
    }

    /**
     * 15. Integración desacoplada con HU-14: Despacha eventos de negocio para aprobación,
     * solicitud de corrección y reenvío.
     */
    public function test_15_integracion_desacoplada_con_avisos_despacha_eventos_de_negocio(): void
    {
        Event::fake([
            DevolucionAprobada::class,
            DevolucionCorreccionSolicitada::class,
            DevolucionReenviadaRevision::class,
        ]);

        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionConPublicacionVencida();

        // 1. Evento de corrección solicitada
        $this->actingAs($admin)->post(route('admin.devoluciones.solicitar-correccion', $devolucion->id), [
            'motivo' => 'Revisar datos del receptor.',
        ]);
        Event::assertDispatched(DevolucionCorreccionSolicitada::class);

        // 2. Evento de reenvío por el autor
        $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => 'Carlos Chan Medina',
            'receptor_identificador' => '19080999',
            'fecha_entrega' => $devolucion->fecha_entrega->format('Y-m-d'),
            'hora_entrega' => '11:30',
            'explicacion_propiedad' => 'Reenviado con aclaración.',
        ]);
        Event::assertDispatched(DevolucionReenviadaRevision::class);

        // 3. Evento de aprobación
        $this->actingAs($admin)->post(route('admin.devoluciones.aprobar', $devolucion->id));
        Event::assertDispatched(DevolucionAprobada::class);
    }
}
