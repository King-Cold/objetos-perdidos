<?php

namespace Tests\Feature;

use App\Models\Aviso;
use App\Models\Devolucion;
use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use App\Services\AvisoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pruebas automatizadas para la HU: Avisos — Task 3:
 * Integrar eventos de reportes y devoluciones.
 */
class AvisosTask3Test extends TestCase
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
        $tercero = User::factory()->create(['role' => User::ROLE_USUARIO_GENERAL]);

        $publicacion = Publicacion::create(array_merge([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-0001',
            'nombre' => 'Calculadora Casio fx-991LAX',
            'descripcion' => 'Calculadora científica color negro',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'devolucion_en_proceso' => true,
            'estado_devolucion' => Devolucion::ESTADO_PENDIENTE,
            'fecha_hallazgo' => $ahora->copy()->subDays(5)->format('Y-m-d'),
            'hora_hallazgo' => '09:30',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'area_zona' => 'Edificio F',
            'version' => 1,
        ], $pubAttrs));

        $fotoEvidencia = UploadedFile::fake()->image('evidencia_entrega.jpg', 600, 400);
        $rutaEvidencia = $fotoEvidencia->storeAs('devoluciones/' . $publicacion->id, 'evidencia.jpg', 'local');

        $devolucion = Devolucion::create(array_merge([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Eduardo Pech Canul',
            'receptor_identificador' => '20080123',
            'fecha_entrega' => $ahora->copy()->subDays(2)->format('Y-m-d'),
            'hora_entrega' => '11:15',
            'fecha_captura' => $ahora->copy()->subDays(1),
            'fecha_envio_revision' => $ahora->copy()->subDays(1),
            'explicacion_propiedad' => 'El receptor presentó su credencial vigente.',
            'fotografia_ruta' => $rutaEvidencia,
            'fotografia_nombre_original' => 'evidencia_entrega.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 20480,
            'estado' => Devolucion::ESTADO_PENDIENTE,
        ], $devAttrs));

        return [$admin, $autor, $tercero, $publicacion, $devolucion];
    }

    /**
     * 1. Aprobación de una publicación genera aviso al autor con destino /reportes/{id}.
     */
    public function test_publicacion_aprobada_genera_aviso_al_autor_con_destino_reporte(): void
    {
        $admin = User::factory()->admin()->create();
        $autor = User::factory()->create();

        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-1001',
            'nombre' => 'Mochila negra SwissGear',
            'descripcion' => 'Mochila encontrada en cafetería',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
            'disponible' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => 1,
            'updated_at_token' => $reporte->updated_at?->timestamp,
            'motivo' => 'Publicación autorizada para el muro.',
        ]);

        $response->assertRedirect(route('admin.moderacion.index'));

        // Verificar que se haya creado el aviso para el autor
        $aviso = Aviso::where('publicacion_id', $reporte->id)
            ->where('tipo_evento', Aviso::TIPO_PUBLICACION_APROBADA)
            ->first();

        $this->assertNotNull($aviso);
        $this->assertEquals($autor->id, $aviso->user_id);
        $this->assertEquals('OBJ-20261106-1001', $aviso->folio_reporte);
        $this->assertEquals("/reportes/{$reporte->id}", $aviso->enlace_destino);
        $this->assertFalse($aviso->leido);
        $this->assertStringContainsString('Mochila negra SwissGear', $aviso->mensaje);
        $this->assertStringContainsString('OBJ-20261106-1001', $aviso->mensaje);

        // Comprobar que en la vista del usuario se presente la acción «Ver reporte»
        $vista = $this->actingAs($autor)->get(route('avisos.index'));
        $vista->assertOk();
        $vista->assertSee('Ver reporte');
        $vista->assertSee($aviso->titulo);
    }

    /**
     * 2. Solicitud de corrección de publicación genera aviso con motivo autorizado.
     */
    public function test_solicitud_correccion_publicacion_genera_aviso_con_motivo_autorizado(): void
    {
        $admin = User::factory()->admin()->create();
        $autor = User::factory()->create();

        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-1002',
            'nombre' => 'Audífonos Sony WH-1000XM4',
            'descripcion' => 'Audífonos inalámbricos negros',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
            'disponible' => false,
        ]);

        $motivoCorreccion = 'Por favor especifica el número de casillero o sala donde fueron hallados.';

        $response = $this->actingAs($admin)->post(route('admin.moderacion.solicitar-correccion', $reporte->id), [
            'motivo' => $motivoCorreccion,
            'lock_version' => 1,
            'updated_at_token' => $reporte->updated_at?->timestamp,
        ]);

        $response->assertRedirect(route('admin.moderacion.index'));

        $aviso = Aviso::where('publicacion_id', $reporte->id)
            ->where('tipo_evento', Aviso::TIPO_PUBLICACION_CORRECCION)
            ->first();

        $this->assertNotNull($aviso);
        $this->assertEquals($autor->id, $aviso->user_id);
        $this->assertEquals($motivoCorreccion, $aviso->motivo_autorizado);
        $this->assertEquals("/reportes/{$reporte->id}", $aviso->enlace_destino);
        $this->assertStringContainsString('Audífonos Sony WH-1000XM4', $aviso->mensaje);
    }

    /**
     * 3. Cancelación administrativa de publicación genera aviso con motivo documentado.
     */
    public function test_cancelacion_administrativa_genera_aviso_con_motivo(): void
    {
        $admin = User::factory()->admin()->create();
        $autor = User::factory()->create();

        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-1003',
            'nombre' => 'Memoria USB Kingston 64GB',
            'descripcion' => 'Memoria USB encontrada en laboratorio',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
            'disponible' => false,
        ]);

        $motivoCancelacion = 'Reporte duplicado con respecto al registro OBJ-20261106-0005.';

        $response = $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporte->id), [
            'motivo' => $motivoCancelacion,
            'lock_version' => 1,
            'updated_at_token' => $reporte->updated_at?->timestamp,
        ]);

        $response->assertRedirect(route('admin.moderacion.show', $reporte->id));

        $aviso = Aviso::where('publicacion_id', $reporte->id)
            ->where('tipo_evento', Aviso::TIPO_PUBLICACION_CANCELADA)
            ->first();

        $this->assertNotNull($aviso);
        $this->assertEquals($autor->id, $aviso->user_id);
        $this->assertStringContainsString($motivoCancelacion, $aviso->motivo_autorizado);
        $this->assertEquals("/reportes/{$reporte->id}", $aviso->enlace_destino);
        $this->assertStringContainsString('Memoria USB Kingston 64GB', $aviso->mensaje);
    }

    /**
     * 4. Aprobación de devolución física genera aviso al autor con destino /devoluciones/{id}.
     */
    public function test_devolucion_aprobada_genera_aviso_al_autor_con_destino_devolucion(): void
    {
        [$admin, $autor, $tercero, $publicacion, $devolucion] = $this->crearDevolucionFixture();

        $response = $this->actingAs($admin)->post(route('admin.devoluciones.aprobar', $devolucion->id));
        $response->assertRedirect(route('admin.devoluciones.show', $devolucion->id));

        $aviso = Aviso::where('devolucion_id', $devolucion->id)
            ->where('tipo_evento', Aviso::TIPO_DEVOLUCION_APROBADA)
            ->first();

        $this->assertNotNull($aviso);
        $this->assertEquals($autor->id, $aviso->user_id);
        $this->assertEquals($publicacion->id, $aviso->publicacion_id);
        $this->assertEquals("/devoluciones/{$devolucion->id}", $aviso->enlace_destino);
        $this->assertStringContainsString($publicacion->nombre, $aviso->mensaje);

        // En la interfaz del autor se presenta «Ver devolución»
        $vista = $this->actingAs($autor)->get(route('avisos.index'));
        $vista->assertOk();
        $vista->assertSee('Ver devolución');
    }

    /**
     * 5. Solicitud de corrección en devolución física genera aviso con motivo documentado.
     */
    public function test_solicitud_correccion_devolucion_genera_aviso_con_motivo(): void
    {
        [$admin, $autor, $tercero, $publicacion, $devolucion] = $this->crearDevolucionFixture();

        $motivo = 'La foto de la entrega no permite observar con claridad el objeto devuelto.';

        $response = $this->actingAs($admin)->post(route('admin.devoluciones.solicitar-correccion', $devolucion->id), [
            'motivo' => $motivo,
        ]);
        $response->assertRedirect(route('admin.devoluciones.show', $devolucion->id));

        $aviso = Aviso::where('devolucion_id', $devolucion->id)
            ->where('tipo_evento', Aviso::TIPO_DEVOLUCION_CORRECCION)
            ->first();

        $this->assertNotNull($aviso);
        $this->assertEquals($autor->id, $aviso->user_id);
        $this->assertEquals($motivo, $aviso->motivo_autorizado);
        $this->assertEquals("/devoluciones/{$devolucion->id}", $aviso->enlace_destino);
        $this->assertStringContainsString($publicacion->nombre, $aviso->mensaje);
    }

    /**
     * 6. Vencimiento de publicación genera aviso para entrega en biblioteca.
     */
    public function test_vencimiento_publicacion_genera_aviso_para_entrega_en_biblioteca(): void
    {
        $autor = User::factory()->create();
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        $publicacionVencida = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-V001',
            'nombre' => 'Termo Stanley verde',
            'descripcion' => 'Termo olvidado en gimnasio',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => $ahora->copy()->subDays(15),
            'fecha_aprobacion' => $ahora->copy()->subDays(15),
            'fecha_vencimiento' => $ahora->copy()->subDays(1),
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'version' => 1,
        ]);

        $exitCode = Artisan::call('reportes:verificar-vencimientos');
        $this->assertEquals(0, $exitCode);

        $aviso = Aviso::where('publicacion_id', $publicacionVencida->id)
            ->where('tipo_evento', Aviso::TIPO_PUBLICACION_VENCIDA)
            ->first();

        $this->assertNotNull($aviso);
        $this->assertEquals($autor->id, $aviso->user_id);
        $this->assertEquals("/reportes/{$publicacionVencida->id}", $aviso->enlace_destino);
        $this->assertStringContainsString('biblioteca', mb_strtolower($aviso->mensaje));
        $this->assertStringContainsString('14 días', $aviso->mensaje);
    }

    /**
     * 7. El comando de vencimientos no genera avisos duplicados ante ejecuciones repetidas.
     */
    public function test_vencimiento_no_genera_avisos_duplicados(): void
    {
        $autor = User::factory()->create();
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        $publicacionVencida = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-V002',
            'nombre' => 'Suéter azul marino',
            'descripcion' => 'Suéter olvidado en salón de clases',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_vencimiento' => $ahora->copy()->subDays(2),
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'version' => 1,
        ]);

        // Primera ejecución: genera 1 aviso
        $generados1 = AvisoService::procesarVencimientosPendientes();
        $this->assertEquals(1, $generados1);

        // Segunda ejecución: debe omitir la ya procesada y retornar 0
        $generados2 = AvisoService::procesarVencimientosPendientes();
        $this->assertEquals(0, $generados2);

        $totalAvisos = Aviso::where('publicacion_id', $publicacionVencida->id)
            ->where('tipo_evento', Aviso::TIPO_PUBLICACION_VENCIDA)
            ->count();

        $this->assertEquals(1, $totalAvisos);
    }

    /**
     * 8. Los avisos llegan únicamente al autor del reporte original y no a terceros ni al receptor.
     */
    public function test_avisos_llegan_unicamente_al_autor_y_no_a_terceros_ni_receptor(): void
    {
        [$admin, $autor, $tercero, $publicacion, $devolucion] = $this->crearDevolucionFixture();

        // Aprobar devolución
        $this->actingAs($admin)->post(route('admin.devoluciones.aprobar', $devolucion->id));

        // El autor tiene el aviso
        $this->assertEquals(1, Aviso::where('user_id', $autor->id)->count());

        // El usuario tercero (o cualquier otro usuario del sistema) tiene 0 avisos
        $this->assertEquals(0, Aviso::where('user_id', $tercero->id)->count());
        $this->assertEquals(0, Aviso::where('user_id', $admin->id)->count());
    }

    /**
     * 9. Los avisos no divulgan identificadores privados del receptor.
     */
    public function test_avisos_no_exponen_datos_privados_del_receptor(): void
    {
        [$admin, $autor, $tercero, $publicacion, $devolucion] = $this->crearDevolucionFixture([], [
            'receptor_identificador' => 'MATRICULA-CONFIDENCIAL-7788',
        ]);

        $this->actingAs($admin)->post(route('admin.devoluciones.aprobar', $devolucion->id));

        $aviso = Aviso::where('devolucion_id', $devolucion->id)->first();
        $this->assertNotNull($aviso);

        $this->assertStringNotContainsString('MATRICULA-CONFIDENCIAL-7788', $aviso->mensaje);
        $this->assertStringNotContainsString('MATRICULA-CONFIDENCIAL-7788', (string) $aviso->motivo_autorizado);
    }

    /**
     * 10. Inmutabilidad histórica: cambios posteriores en la publicación no mutan avisos previos.
     */
    public function test_inmutabilidad_historica_de_avisos_ante_cambios_posteriores(): void
    {
        $admin = User::factory()->admin()->create();
        $autor = User::factory()->create();

        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-9999',
            'nombre' => 'Carpeta con apuntes',
            'descripcion' => 'Carpeta blanca',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
            'disponible' => false,
        ]);

        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => 1,
            'updated_at_token' => $reporte->updated_at?->timestamp,
            'motivo' => 'Aprobación inicial válida.',
        ]);

        $avisoAprobacion = Aviso::where('publicacion_id', $reporte->id)->first();
        $this->assertNotNull($avisoAprobacion);
        $mensajeOriginal = $avisoAprobacion->mensaje;
        $motivoOriginal = $avisoAprobacion->motivo_autorizado;

        // Posteriormente, el reporte es cancelado o modificado
        $reporte->update([
            'estado' => Publicacion::ESTADO_CANCELADO,
            'nombre' => 'Carpeta modificada posteriormente',
        ]);

        $avisoAprobacion->refresh();

        // El aviso previo de aprobación conserva su registro histórico original
        $this->assertEquals(Aviso::TIPO_PUBLICACION_APROBADA, $avisoAprobacion->tipo_evento);
        $this->assertEquals($mensajeOriginal, $avisoAprobacion->mensaje);
        $this->assertEquals($motivoOriginal, $avisoAprobacion->motivo_autorizado);
    }

    /**
     * 11. Desacoplamiento de fallas: una excepción imprevista en avisos no rompe el flujo principal.
     */
    public function test_fallo_en_aviso_no_interrumpe_moderacion_principal(): void
    {
        $admin = User::factory()->admin()->create();
        $autor = User::factory()->create();

        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-9988',
            'nombre' => 'Llaves con llavero de peluche',
            'descripcion' => 'Llaves encontradas en pasillo',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
            'disponible' => false,
        ]);

        // Simular un reporte cuyo user_id se vuelve temporalmente inaccesible
        $resultado = AvisoService::generarAvisoPublicacionAprobada(new Publicacion());
        $this->assertNull($resultado); // No lanza excepción no controlada, retorna null de forma segura
    }
}

