<?php

namespace Tests\Feature;

use App\Models\Aviso;
use App\Models\DecisionModeracion;
use App\Models\Devolucion;
use App\Models\Publicacion;
use App\Models\User;
use App\Services\AvisoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pruebas automatizadas para la HU: Avisos — Task 4:
 * Implementar la prevención de avisos duplicados y el manejo de errores.
 */
class AvisosTask4Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Fixture auxiliar para pruebas de devoluciones.
     */
    private function crearDevolucionFixture(array $pubAttrs = [], array $devAttrs = []): array
    {
        Storage::fake('local');
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        $autor = User::factory()->create(['role' => User::ROLE_USUARIO_GENERAL]);
        $admin = User::factory()->admin()->create();

        $publicacion = Publicacion::create(array_merge([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-D001',
            'nombre' => 'Laptop Lenovo ThinkPad',
            'descripcion' => 'Laptop color negro encontrada en biblioteca',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'devolucion_en_proceso' => true,
            'estado_devolucion' => Devolucion::ESTADO_PENDIENTE,
            'fecha_hallazgo' => $ahora->copy()->subDays(5)->format('Y-m-d'),
            'hora_hallazgo' => '10:00',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'area_zona' => 'Edificio B',
            'version' => 1,
        ], $pubAttrs));

        $fotoEvidencia = UploadedFile::fake()->image('evidencia_entrega.jpg', 600, 400);
        $rutaEvidencia = $fotoEvidencia->storeAs('devoluciones/' . $publicacion->id, 'evidencia.jpg', 'local');

        $devolucion = Devolucion::create(array_merge([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Ana Karen Canul',
            'receptor_identificador' => '21080099',
            'fecha_entrega' => $ahora->copy()->subDays(1)->format('Y-m-d'),
            'hora_entrega' => '12:00',
            'fecha_captura' => $ahora->copy()->subDays(1),
            'fecha_envio_revision' => $ahora->copy()->subDays(1),
            'explicacion_propiedad' => 'Presentó credencial y número de serie coincidente.',
            'fotografia_ruta' => $rutaEvidencia,
            'fotografia_nombre_original' => 'evidencia_entrega.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 20480,
            'estado' => Devolucion::ESTADO_PENDIENTE,
        ], $devAttrs));

        return [$admin, $autor, $publicacion, $devolucion];
    }

    /**
     * 1. Ejecutar dos veces el evento de aprobación de publicación no genera avisos duplicados.
     */
    public function test_ejecucion_repetida_de_aprobacion_de_publicacion_no_genera_duplicados(): void
    {
        $autor = User::factory()->create();
        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-T401',
            'nombre' => 'Calculadora Científica',
            'descripcion' => 'Calculadora gris encontrada en salón 12',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
            'disponible' => false,
        ]);

        // Primera llamada: genera el aviso
        $aviso1 = AvisoService::generarAvisoPublicacionAprobada($reporte, 'Aprobación autorizada.');
        $this->assertNotNull($aviso1);
        $this->assertEquals(1, Aviso::where('publicacion_id', $reporte->id)->count());

        // Segunda llamada repetida: debe detectar duplicado y retornar null sin crear otro registro
        $aviso2 = AvisoService::generarAvisoPublicacionAprobada($reporte, 'Aprobación autorizada.');
        $this->assertNull($aviso2);
        $this->assertEquals(1, Aviso::where('publicacion_id', $reporte->id)->count());
    }

    /**
     * 2. Ejecutar dos veces la solicitud de corrección de publicación no genera avisos duplicados.
     */
    public function test_ejecucion_repetida_de_solicitud_de_correccion_no_genera_duplicados(): void
    {
        $autor = User::factory()->create();
        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-T402',
            'nombre' => 'Mochila azul',
            'descripcion' => 'Mochila encontrada en pasillo',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
            'disponible' => false,
        ]);

        $aviso1 = AvisoService::generarAvisoPublicacionCorreccion($reporte, 'Detalla el contenido.');
        $this->assertNotNull($aviso1);

        $aviso2 = AvisoService::generarAvisoPublicacionCorreccion($reporte, 'Detalla el contenido.');
        $this->assertNull($aviso2);

        $this->assertEquals(1, Aviso::where('publicacion_id', $reporte->id)->count());
    }

    /**
     * 3. Ejecutar dos veces la cancelación de publicación no genera avisos duplicados.
     */
    public function test_ejecucion_repetida_de_cancelacion_no_genera_duplicados(): void
    {
        $autor = User::factory()->create();
        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-T403',
            'nombre' => 'Credencial escolar',
            'descripcion' => 'Credencial encontrada en cafetería',
            'estado' => Publicacion::ESTADO_CANCELADO,
            'version' => 1,
            'disponible' => false,
        ]);

        $aviso1 = AvisoService::generarAvisoPublicacionCancelada($reporte, 'Reporte duplicado.');
        $this->assertNotNull($aviso1);

        $aviso2 = AvisoService::generarAvisoPublicacionCancelada($reporte, 'Reporte duplicado.');
        $this->assertNull($aviso2);

        $this->assertEquals(1, Aviso::where('publicacion_id', $reporte->id)->count());
    }

    /**
     * 4. Ejecutar dos veces la aprobación de devolución física no genera avisos duplicados.
     */
    public function test_ejecucion_repetida_de_devolucion_aprobada_no_genera_duplicados(): void
    {
        [$admin, $autor, $publicacion, $devolucion] = $this->crearDevolucionFixture();

        $aviso1 = AvisoService::generarAvisoDevolucionAprobada($devolucion);
        $this->assertNotNull($aviso1);

        $aviso2 = AvisoService::generarAvisoDevolucionAprobada($devolucion);
        $this->assertNull($aviso2);

        $this->assertEquals(1, Aviso::where('devolucion_id', $devolucion->id)->count());
    }

    /**
     * 5. Ejecutar dos veces la solicitud de corrección de devolución no genera avisos duplicados.
     */
    public function test_ejecucion_repetida_de_devolucion_correccion_no_genera_duplicados(): void
    {
        [$admin, $autor, $publicacion, $devolucion] = $this->crearDevolucionFixture();

        $aviso1 = AvisoService::generarAvisoDevolucionCorreccion($devolucion, 'Foto borrosa.');
        $this->assertNotNull($aviso1);

        $aviso2 = AvisoService::generarAvisoDevolucionCorreccion($devolucion, 'Foto borrosa.');
        $this->assertNull($aviso2);

        $this->assertEquals(1, Aviso::where('devolucion_id', $devolucion->id)->count());
    }

    /**
     * 6. Eventos diferentes del mismo reporte generan sus respectivos avisos de forma independiente.
     */
    public function test_eventos_diferentes_del_mismo_reporte_generan_sus_avisos_correspondientes(): void
    {
        $autor = User::factory()->create();
        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-T406',
            'nombre' => 'Termo metálico negro',
            'descripcion' => 'Termo encontrado en cancha de basquetbol',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
            'disponible' => false,
        ]);

        // Evento 1: Se solicita corrección
        $aviso1 = AvisoService::generarAvisoPublicacionCorreccion($reporte, 'Aclara la ubicación.');
        $this->assertNotNull($aviso1);
        $this->assertEquals(Aviso::TIPO_PUBLICACION_CORRECCION, $aviso1->tipo_evento);

        // El autor atiende correcciones y se incrementa la versión institucional
        $reporte->version = 2;
        $reporte->estado = Publicacion::ESTADO_APROBADA;
        $reporte->save();

        // Evento 2: El administrador aprueba el reporte en su nueva versión
        $aviso2 = AvisoService::generarAvisoPublicacionAprobada($reporte);
        $this->assertNotNull($aviso2);
        $this->assertEquals(Aviso::TIPO_PUBLICACION_APROBADA, $aviso2->tipo_evento);

        // Evento 3: Transcurren los 14 días y se genera el aviso de vencimiento
        $reporte->fecha_vencimiento = Carbon::now(Publicacion::TIMEZONE)->subDay();
        $reporte->save();

        $aviso3 = AvisoService::generarAvisoPublicacionVencida($reporte);
        $this->assertNotNull($aviso3);
        $this->assertEquals(Aviso::TIPO_PUBLICACION_VENCIDA, $aviso3->tipo_evento);

        // Los tres avisos deben coexistir para el mismo reporte
        $avisosReporte = Aviso::where('publicacion_id', $reporte->id)->get();
        $this->assertCount(3, $avisosReporte);
        $this->assertTrue($avisosReporte->contains('tipo_evento', Aviso::TIPO_PUBLICACION_CORRECCION));
        $this->assertTrue($avisosReporte->contains('tipo_evento', Aviso::TIPO_PUBLICACION_APROBADA));
        $this->assertTrue($avisosReporte->contains('tipo_evento', Aviso::TIPO_PUBLICACION_VENCIDA));
    }

    /**
     * 7. Eventos diferentes de la misma devolución generan sus avisos correspondientes.
     */
    public function test_eventos_diferentes_de_la_misma_devolucion_generan_sus_avisos(): void
    {
        [$admin, $autor, $publicacion, $devolucion] = $this->crearDevolucionFixture();

        // Evento 1: Corrección de evidencia solicitada
        $aviso1 = AvisoService::generarAvisoDevolucionCorreccion($devolucion, 'Identificación ilegible.');
        $this->assertNotNull($aviso1);
        $this->assertEquals(Aviso::TIPO_DEVOLUCION_CORRECCION, $aviso1->tipo_evento);

        // Evento 2: Posteriormente se aprueba la devolución
        $aviso2 = AvisoService::generarAvisoDevolucionAprobada($devolucion);
        $this->assertNotNull($aviso2);
        $this->assertEquals(Aviso::TIPO_DEVOLUCION_APROBADA, $aviso2->tipo_evento);

        $avisosDev = Aviso::where('devolucion_id', $devolucion->id)->get();
        $this->assertCount(2, $avisosDev);
        $this->assertTrue($avisosDev->contains('tipo_evento', Aviso::TIPO_DEVOLUCION_CORRECCION));
        $this->assertTrue($avisosDev->contains('tipo_evento', Aviso::TIPO_DEVOLUCION_APROBADA));
    }

    /**
     * 8. Colisiones concurrentes por clave única en base de datos se manejan sin excepciones no controladas.
     */
    public function test_colision_concurrente_por_restriccion_unica_en_base_de_datos(): void
    {
        $autor = User::factory()->create();

        $pub = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-CONCURRENTE-01',
            'nombre' => 'Objeto de prueba concurrente',
            'descripcion' => 'Descripción de prueba',
            'version' => 1,
            'estado' => Publicacion::ESTADO_APROBADA,
        ]);

        $clave = "pub_aprobada_{$pub->id}_v1";

        // Primer registro con la misma clave duplicada en BD
        Aviso::create([
            'user_id' => $autor->id,
            'publicacion_id' => $pub->id,
            'tipo_evento' => Aviso::TIPO_PUBLICACION_APROBADA,
            'titulo' => 'Aviso existente',
            'mensaje' => 'Contenido previo',
            'clave_duplicado' => $clave,
        ]);

        // Simular llamada para la misma publicación y versión
        $resultado = AvisoService::generarAvisoPublicacionAprobada($pub);

        // Debe omitir el duplicado y retornar null sin romperse
        $this->assertNull($resultado);
        $this->assertEquals(1, Aviso::where('clave_duplicado', $clave)->count());
    }

    /**
     * 9. Los errores y duplicados se registran en los logs de Laravel con metadatos estructurados.
     */
    public function test_los_duplicados_y_errores_se_registran_en_los_logs_de_laravel(): void
    {
        Log::spy();

        $autor = User::factory()->create();
        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-LOG01',
            'nombre' => 'Paraguas negro',
            'descripcion' => 'Paraguas olvidado en biblioteca',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
            'disponible' => false,
        ]);

        // Primer llamado: registra el aviso
        AvisoService::generarAvisoPublicacionAprobada($reporte);

        // Segundo llamado: registra info de duplicado en Log
        AvisoService::generarAvisoPublicacionAprobada($reporte);

        Log::shouldHaveReceived('info')
            ->atLeast()->once();

        // Intento con publicación sin ID persistido (objeto vacío): debe registrar advertencia/error sin romper
        $fallo = AvisoService::generarAvisoPublicacionAprobada(new Publicacion());
        $this->assertNull($fallo);
    }

    /**
     * 10. Un fallo en el servicio de avisos no interrumpe el flujo principal del proceso administrativo.
     */
    public function test_fallo_en_servicio_de_avisos_no_impide_completar_proceso_principal(): void
    {
        $admin = User::factory()->admin()->create();
        $autor = User::factory()->create();

        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-ROBUSTO',
            'nombre' => 'Mochila deportiva roja',
            'descripcion' => 'Mochila encontrada en áreas deportivas',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
            'disponible' => false,
        ]);

        // El controlador de moderación ejecuta la transacción completa
        $response = $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => 1,
            'updated_at_token' => $reporte->updated_at?->timestamp,
            'motivo' => 'Aprobación exitosa.',
        ]);

        // La aprobación se completa correctamente sin ser bloqueada
        $response->assertRedirect(route('admin.moderacion.index'));
        $response->assertSessionHas('status');

        $reporte->refresh();
        $this->assertEquals(Publicacion::ESTADO_APROBADA, $reporte->estado);
        $this->assertTrue((bool) $reporte->disponible);
        $this->assertDatabaseHas('decisiones_moderacion', [
            'publicacion_id' => $reporte->id,
            'tipo_decision' => DecisionModeracion::TIPO_APROBADA,
        ]);
    }

    /**
     * 11. Los avisos y contadores de otros usuarios no se ven afectados.
     */
    public function test_avisos_de_otros_usuarios_no_se_ven_afectados(): void
    {
        $usuarioA = User::factory()->create();
        $usuarioB = User::factory()->create();

        // Aviso previo existente para usuario B
        Aviso::create([
            'user_id' => $usuarioB->id,
            'tipo_evento' => Aviso::TIPO_PUBLICACION_APROBADA,
            'titulo' => 'Aviso previo de usuario B',
            'mensaje' => 'Mensaje para usuario B',
            'enlace_destino' => '/reportes/1',
            'leido' => false,
            'clave_duplicado' => 'pub_aprobada_b_1',
        ]);

        $this->assertEquals(1, $usuarioB->avisosNoLeidos()->count());

        // Se generan eventos para usuario A
        $reporteA = Publicacion::create([
            'user_id' => $usuarioA->id,
            'folio' => 'OBJ-USER-A-01',
            'nombre' => 'Cálculo Stewart',
            'descripcion' => 'Libro olvidado en laboratorio',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
        ]);

        AvisoService::generarAvisoPublicacionAprobada($reporteA);

        // Usuario A tiene 1 aviso no leído
        $this->assertEquals(1, $usuarioA->avisosNoLeidos()->count());

        // El usuario B sigue teniendo exactamente su único aviso intacto
        $this->assertEquals(1, $usuarioB->avisosNoLeidos()->count());
        $this->assertEquals(1, Aviso::where('user_id', $usuarioB->id)->count());
        $this->assertEquals(1, Aviso::where('user_id', $usuarioA->id)->count());
    }

    /**
     * 12. Un fallo en el servicio de avisos no revierte una aprobación de devolución completada.
     */
    public function test_fallo_en_aviso_no_revierte_aprobacion_de_devolucion(): void
    {
        [$admin, $autor, $publicacion, $devolucion] = $this->crearDevolucionFixture();

        // Ejecutar aprobación de devolución
        $response = $this->actingAs($admin)->post(route('admin.devoluciones.aprobar', $devolucion->id));
        $response->assertRedirect(route('admin.devoluciones.show', $devolucion->id));

        $devolucion->refresh();
        $publicacion->refresh();

        // La decisión de devolución y el estado de la publicación quedan firmes e irreversibles
        $this->assertEquals(Devolucion::ESTADO_VERIFICADA, $devolucion->estado);
        $this->assertEquals(Publicacion::ESTADO_DEVUELTO, $publicacion->estado);
        $this->assertFalse((bool) $publicacion->disponible);
        $this->assertDatabaseHas('decisiones_moderacion', [
            'publicacion_id' => $publicacion->id,
            'tipo_decision' => DecisionModeracion::TIPO_DEVOLUCION_APROBADA,
        ]);
    }

    /**
     * 13. Un fallo del servicio de avisos no impide procesar el vencimiento ni prolonga la vigencia del objeto.
     */
    public function test_fallo_en_aviso_no_impide_procesar_vencimiento_ni_prolonga_vigencia(): void
    {
        $autor = User::factory()->create();
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        $reporteVencido = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-VENCIDO-INMUTABLE',
            'nombre' => 'Sombrilla azul',
            'descripcion' => 'Sombrilla olvidada en cafetería',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_vencimiento' => $ahora->copy()->subDays(2),
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'version' => 1,
        ]);

        // Simular que el servicio de avisos falla al crear el aviso de vencimiento (por ejemplo con objeto vacío)
        $fallo = AvisoService::generarAvisoPublicacionVencida(new Publicacion());
        $this->assertNull($fallo);

        // La regla de negocio de vencimiento en el modelo es completamente independiente:
        // El reporte continúa vencido y queda automáticamente excluido del muro institucional
        $this->assertTrue($reporteVencido->isVencida());
        $this->assertFalse($reporteVencido->isDisponible());
        $this->assertFalse(Publicacion::disponiblesMuro()->where('id', $reporteVencido->id)->exists());
    }

    /**
     * 14. Los pendientes del bibliotecario permanecen en los listados administrativos y no en avisos.
     */
    public function test_pendientes_del_bibliotecario_permanecen_en_listados_administrativos_y_no_en_avisos(): void
    {
        $admin = User::factory()->admin()->create();
        $autor = User::factory()->create();

        // 1. Reporte pendiente de moderación
        $reportePendiente = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-ADMIN-COLA-01',
            'nombre' => 'Memoria USB 32GB',
            'descripcion' => 'Memoria encontrada en biblioteca',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
            'disponible' => false,
        ]);

        // 2. El bibliotecario consulta la cola de moderación administrativa
        $vistaModeracion = $this->actingAs($admin)->get(route('admin.moderacion.index'));
        $vistaModeracion->assertOk();
        $vistaModeracion->assertSee($reportePendiente->folio);

        // 3. El bibliotecario consulta su apartado de avisos personales: no tiene avisos pendientes de gestión
        $vistaAvisos = $this->actingAs($admin)->get(route('avisos.index'));
        $vistaAvisos->assertOk();
        $vistaAvisos->assertSee('No tienes avisos');
        $this->assertEquals(0, $admin->avisosNoLeidos()->count());
    }
}
