<?php

namespace Tests\Feature\Admin;

use App\Models\DecisionModeracion;
use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ModeracionAprobacionVigenciaTask074Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Restaurar el reloj del sistema al finalizar
        parent::tearDown();
    }

    /**
     * Prueba 1: Primera aprobación correcta.
     * Requerimiento: Al aprobar por primera vez, cambia estado a 'Aprobada' y disponible a true.
     */
    public function test_primera_aprobacion_correcta(): void
    {
        $admin = User::factory()->admin()->create();
        $autor = User::factory()->create();

        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261101-0001',
            'nombre' => 'Laptop HP Pavilion',
            'descripcion' => 'Laptop azul marino encontrada en sala de cómputo B',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
            'disponible' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => 1,
            'updated_at_token' => $reporte->updated_at?->timestamp,
        ]);

        $response->assertRedirect(route('admin.moderacion.index'));
        $response->assertSessionHas('status');

        $reporteActualizado = $reporte->fresh();
        $this->assertEquals(Publicacion::ESTADO_APROBADA, $reporteActualizado->estado);
        $this->assertTrue((bool) $reporteActualizado->disponible);
        $this->assertNull($reporteActualizado->observaciones);
        $this->assertEquals(2, $reporteActualizado->version);
    }

    /**
     * Prueba 2: Registro de responsable y fecha.
     * Requerimiento: Registrar el administrador responsable y la fecha/hora en America/Merida.
     */
    public function test_registro_de_responsable_y_fecha(): void
    {
        $fechaFijada = Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($fechaFijada);

        $admin = User::factory()->admin()->create([
            'name' => 'Lic. Sofia Navarrete',
            'email' => 'sofia.navarrete@merida.tecnm.mx',
        ]);

        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0002',
            'nombre' => 'Calculadora Casio fx-991',
            'descripcion' => 'Calculadora gris en salón C-3',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => 1,
        ]);

        $reporteActualizado = $reporte->fresh();

        $this->assertNotNull($reporteActualizado->fecha_aprobacion);
        $this->assertEquals($fechaFijada->format('Y-m-d H:i:s'), $reporteActualizado->fecha_aprobacion->format('Y-m-d H:i:s'));

        $decision = DecisionModeracion::where('publicacion_id', $reporte->id)->latest()->first();
        $this->assertNotNull($decision);
        $this->assertEquals($admin->id, $decision->user_id);
        $this->assertEquals(DecisionModeracion::TIPO_APROBADA, $decision->tipo_decision);
        $this->assertEquals($fechaFijada->format('Y-m-d H:i:s'), $decision->fecha_decision->format('Y-m-d H:i:s'));
    }

    /**
     * Prueba 3: Cálculo exacto de 14 días naturales.
     * Requerimiento:
     * - Primera aprobación: 1 de noviembre, 10:00.
     * - Vencimiento: exactamente 15 de noviembre, 10:00.
     */
    public function test_calculo_exacto_de_14_dias_naturales(): void
    {
        $fechaInicio = Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($fechaInicio);

        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0003',
            'nombre' => 'Mochila Negra',
            'descripcion' => 'Mochila con libros de texto',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => 1,
        ]);

        $reporteActualizado = $reporte->fresh();

        $vencimientoEsperado = Carbon::create(2026, 11, 15, 10, 0, 0, Publicacion::TIMEZONE);

        $this->assertEquals($fechaInicio->format('Y-m-d H:i:s'), $reporteActualizado->fecha_primera_aprobacion->format('Y-m-d H:i:s'));
        $this->assertEquals($vencimientoEsperado->format('Y-m-d H:i:s'), $reporteActualizado->fecha_vencimiento->format('Y-m-d H:i:s'));
        $this->assertEquals(14, $reporteActualizado->fecha_primera_aprobacion->diffInDays($reporteActualizado->fecha_vencimiento));
    }

    /**
     * Prueba 4: Publicación en el muro cuando esté disponible y consulta con vigencia real.
     * Requerimiento: Mostrar el reporte en el muro como «Disponible» y verificar vigencia real en consulta.
     */
    public function test_publicacion_en_el_muro_cuando_este_disponible(): void
    {
        $ahora = Carbon::create(2026, 11, 1, 12, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($ahora);

        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0004',
            'nombre' => 'Termo Stanley Verde',
            'descripcion' => 'Termo verde oliva en cafetería',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'disponible' => false,
            'version' => 1,
        ]);

        // Antes de aprobar no aparece en muro
        $muroAntes = $this->get(route('muro'));
        $muroAntes->assertDontSee('Termo Stanley Verde');

        // Administrador aprueba
        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => 1,
        ]);

        // Después de aprobar aparece en el muro institucional
        $muroDespues = $this->get(route('muro'));
        $muroDespues->assertStatus(200);
        $muroDespues->assertSee('Termo Stanley Verde');
        $this->assertTrue($reporte->fresh()->isDisponible());
    }

    /**
     * Prueba 5: Conservación del custodio y situación de resguardo.
     * Requerimiento:
     * - No marcar como recibido.
     * - No marcar como devuelto.
     * - No modificar quién tiene el objeto (user_id o custodio original).
     * - No alterar evidencias o procesos de resguardo.
     */
    public function test_conservacion_del_custodio_y_situacion_de_resguardo(): void
    {
        $admin = User::factory()->admin()->create();
        $autorCustodio = User::factory()->create([
            'name' => 'Alumno Custodio Original',
            'email' => 'alumno.custodio@merida.tecnm.mx',
        ]);

        $reporte = Publicacion::create([
            'user_id' => $autorCustodio->id,
            'folio' => 'OBJ-20261101-0005',
            'nombre' => 'Smartwatch Xiaomi',
            'descripcion' => 'Reloj inteligente con extensible negro',
            'lugar_hallazgo' => 'Laboratorio de Química',
            'area_zona' => 'Edificio Q',
            'ubicacion_especifica' => 'Mesa 4',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
        ]);

        $foto = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/smartwatch.jpg',
            'nombre_original' => 'smartwatch.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 45000,
        ]);

        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => 1,
        ]);

        $reporteActualizado = $reporte->fresh();

        // El autor/custodio permanece intacto
        $this->assertEquals($autorCustodio->id, $reporteActualizado->user_id);
        $this->assertEquals('Alumno Custodio Original', $reporteActualizado->user->name);

        // Los datos de ubicación y hallazgo permanecen intactos
        $this->assertEquals('Laboratorio de Química', $reporteActualizado->lugar_hallazgo);
        $this->assertEquals('Edificio Q', $reporteActualizado->area_zona);
        $this->assertEquals('Mesa 4', $reporteActualizado->ubicacion_especifica);

        // Fotografías y evidencias intactas
        $this->assertCount(1, $reporteActualizado->fotografias);
        $this->assertEquals($foto->id, $reporteActualizado->fotografias->first()->id);

        // El estado es Aprobada, no "Devuelto" ni "Recibido"
        $this->assertEquals(Publicacion::ESTADO_APROBADA, $reporteActualizado->estado);
    }

    /**
     * Prueba 6: Conservación de la primera fecha de aprobación.
     * Requerimiento: Cuando un reporte previamente aprobado sea corregido y reenviado, conservar fecha de primera aprobación.
     */
    public function test_conservacion_de_la_primera_fecha_de_aprobacion(): void
    {
        $fechaPrimera = Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($fechaPrimera);

        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0006',
            'nombre' => 'Carpeta de Dibujo',
            'descripcion' => 'Carpeta negra tamaño tabloide',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
        ]);

        // 1. Primera aprobación el 1 de nov
        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => 1,
        ]);

        $reporte->refresh();
        $this->assertEquals($fechaPrimera->format('Y-m-d H:i:s'), $reporte->fecha_primera_aprobacion->format('Y-m-d H:i:s'));

        // 2. Simular ciclo de edición y reenvío el 8 de nov
        $fechaSegunda = Carbon::create(2026, 11, 8, 14, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($fechaSegunda);
        $this->flushSession();
        $this->actingAs($admin);
        session(['last_activity_time' => Carbon::now()->timestamp]);

        $reporte->reenviarARevision();
        $this->assertEquals(Publicacion::ESTADO_PENDIENTE, $reporte->fresh()->estado);

        // 3. Segunda aprobación el 8 de nov
        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => $reporte->fresh()->version,
        ]);

        $reporteActualizado = $reporte->fresh();

        // Conserva estrictamente la fecha de primera aprobación original
        $this->assertEquals($fechaPrimera->format('Y-m-d H:i:s'), $reporteActualizado->fecha_primera_aprobacion->format('Y-m-d H:i:s'));
        $this->assertEquals($fechaSegunda->format('Y-m-d H:i:s'), $reporteActualizado->fecha_aprobacion->format('Y-m-d H:i:s'));
    }

    /**
     * Prueba 7: Conservación del vencimiento después de una segunda aprobación.
     * Requerimiento:
     * - Primera aprobación: 1 de nov 10:00.
     * - Vencimiento: 15 de nov 10:00.
     * - Nueva aprobación: 8 de nov.
     * - El vencimiento deberá continuar siendo el 15 de noviembre a las 10:00 (no reiniciar los 14 días).
     */
    public function test_conservacion_del_vencimiento_despues_de_una_segunda_aprobacion(): void
    {
        $fecha1Nov = Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($fecha1Nov);

        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0007',
            'nombre' => 'Audífonos Sony Diadema',
            'descripcion' => 'Audífonos inalámbricos',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
        ]);

        // Primera aprobación el 1 de noviembre a las 10:00
        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), ['lock_version' => 1]);

        $vencimientoFijado = $reporte->fresh()->fecha_vencimiento;
        $this->assertEquals('2026-11-15 10:00:00', $vencimientoFijado->format('Y-m-d H:i:s'));

        // Avanzar el tiempo al 8 de noviembre
        $fecha8Nov = Carbon::create(2026, 11, 8, 10, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($fecha8Nov);
        $this->flushSession();
        $this->actingAs($admin);
        session(['last_activity_time' => Carbon::now()->timestamp]);

        $reporte->refresh();
        $reporte->reenviarARevision();

        // Segunda aprobación el 8 de noviembre
        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => $reporte->fresh()->version,
        ]);

        $reporteActualizado = $reporte->fresh();

        // El vencimiento NO se reinicia a 14 días desde el 8 de nov (22 de nov); sigue siendo 15 de nov a las 10:00
        $this->assertEquals('2026-11-15 10:00:00', $reporteActualizado->fecha_vencimiento->format('Y-m-d H:i:s'));
    }

    /**
     * Prueba 8: Reaprobación con tiempo restante permite regresar al muro.
     * Requerimiento: Permitir que regrese al muro únicamente durante el tiempo restante.
     */
    public function test_reaprobacion_con_tiempo_restante(): void
    {
        $fechaInicio = Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($fechaInicio);

        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0008',
            'nombre' => 'Gafas de Sol Ray-Ban',
            'descripcion' => 'Gafas oscuras en estuche',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), ['lock_version' => 1]);

        // Simular que el 5 de nov se solicita corrección y se reenvía el 6 de nov (aún quedan 9 días de vigencia)
        $fecha6Nov = Carbon::create(2026, 11, 6, 10, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($fecha6Nov);
        $this->flushSession();
        $this->actingAs($admin);
        session(['last_activity_time' => Carbon::now()->timestamp]);

        $reporte->refresh();
        $reporte->reenviarARevision();

        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => $reporte->fresh()->version,
        ]);

        $reporteActualizado = $reporte->fresh();

        $this->assertTrue((bool) $reporteActualizado->disponible);
        $this->assertTrue($reporteActualizado->isDisponible());
        $this->assertTrue(Publicacion::disponiblesMuro()->where('id', $reporte->id)->exists());
    }

    /**
     * Prueba 9: Imposibilidad de reactivar reportes vencidos en el muro.
     * Requerimiento:
     * - No reactivar una publicación cuando ya venció.
     * - Conservar la decisión administrativa sin volver a mostrar el reporte en el muro.
     */
    public function test_imposibilidad_de_reactivar_reportes_vencidos(): void
    {
        $fechaInicio = Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($fechaInicio);

        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0009',
            'nombre' => 'Libro de Cálculo Diferencial',
            'descripcion' => 'Libro pasta dura',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), ['lock_version' => 1]);

        // Avanzar el tiempo más allá de los 14 días (ej. 16 de noviembre, ya vencido)
        $fechaVencido = Carbon::create(2026, 11, 16, 12, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($fechaVencido);
        $this->flushSession();
        $this->actingAs($admin);
        session(['last_activity_time' => Carbon::now()->timestamp]);

        // El reporte ya venció
        $this->assertTrue($reporte->fresh()->isVencida());

        // Simular que el reporte pasa por moderación
        $reporte->refresh();
        $reporte->reenviarARevision();

        // Administrador aprueba el contenido pero el reporte ya está vencido
        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => $reporte->fresh()->version,
        ]);

        $reporteActualizado = $reporte->fresh();

        // Se aprueba administrativamente
        $this->assertEquals(Publicacion::ESTADO_APROBADA, $reporteActualizado->estado);
        // Pero NO se reactiva en el muro (disponible = false)
        $this->assertFalse((bool) $reporteActualizado->disponible);
        $this->assertFalse($reporteActualizado->isDisponible());
        $this->assertFalse(Publicacion::disponiblesMuro()->where('id', $reporte->id)->exists());
    }

    /**
     * Prueba 10: Restricción por devolución en proceso.
     * Requerimiento: No reactivar una publicación cuando existe una devolución en proceso que impide su disponibilidad.
     */
    public function test_restriccion_por_devolucion_en_proceso(): void
    {
        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0010',
            'nombre' => 'Cartera de Cuero',
            'descripcion' => 'Cartera café con credencial',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
            'devolucion_en_proceso' => true, // Devolución en proceso activa
        ]);

        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => 1,
        ]);

        $reporteActualizado = $reporte->fresh();

        // Se registra la aprobación de contenido
        $this->assertEquals(Publicacion::ESTADO_APROBADA, $reporteActualizado->estado);
        // Pero no se muestra en el muro por la restricción de devolución
        $this->assertFalse((bool) $reporteActualizado->disponible);
        $this->assertFalse($reporteActualizado->isDisponible());
        $this->assertFalse(Publicacion::disponiblesMuro()->where('id', $reporte->id)->exists());
    }

    /**
     * Prueba 11: Restricción por incidencia abierta.
     * Requerimiento: No reactivar publicación cuando existe una incidencia abierta.
     */
    public function test_restriccion_por_incidencia(): void
    {
        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0011',
            'nombre' => 'Celular Motorola',
            'descripcion' => 'Teléfono celular con pantalla rota',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
            'incidencia_abierta' => true, // Incidencia abierta
        ]);

        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => 1,
        ]);

        $reporteActualizado = $reporte->fresh();

        $this->assertEquals(Publicacion::ESTADO_APROBADA, $reporteActualizado->estado);
        $this->assertFalse((bool) $reporteActualizado->disponible);
        $this->assertFalse($reporteActualizado->isDisponible());
        $this->assertFalse(Publicacion::disponiblesMuro()->where('id', $reporte->id)->exists());
    }

    /**
     * Prueba 12: Detección de contenido modificado durante la revisión.
     * Requerimiento: Si el reporte cambia mientras el administrador lo revisa, rechazar la aprobación y mostrar:
     * «El reporte cambió. Revisa su información actual antes de continuar».
     */
    public function test_deteccion_de_contenido_modificado_durante_la_revision(): void
    {
        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0012',
            'nombre' => 'Llaves de Casa',
            'descripcion' => 'Llavero con figuras',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 2,
        ]);

        // Simular intento con versión vieja (versión 1)
        $response = $this->actingAs($admin)->from(route('admin.moderacion.show', $reporte->id))->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => 1, // Desactualizado
        ]);

        $response->assertRedirect(route('admin.moderacion.show', $reporte->id));
        $response->assertSessionHas('error', 'El reporte cambió. Revisa su información actual antes de continuar.');

        // Comprobar que en base de datos no se alteró el estado
        $this->assertEquals(Publicacion::ESTADO_PENDIENTE, $reporte->fresh()->estado);
    }

    /**
     * Prueba 13: Protección contra aprobaciones simultáneas.
     * Requerimiento: Si dos administradores aprueban simultáneamente, la segunda solicitud se rechaza por cambio de estado.
     */
    public function test_proteccion_contra_aprobaciones_simultaneas(): void
    {
        $admin1 = User::factory()->admin()->create();
        $admin2 = User::factory()->admin()->create();

        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0013',
            'nombre' => 'Cargador Tipo C',
            'descripcion' => 'Cargador blanco de 65W',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
        ]);

        // Primer administrador aprueba
        $response1 = $this->actingAs($admin1)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => 1,
        ]);
        $response1->assertRedirect(route('admin.moderacion.index'));

        // Segundo administrador intenta aprobar con la misma versión 1 previa
        $response2 = $this->actingAs($admin2)->from(route('admin.moderacion.show', $reporte->id))->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => 1,
        ]);

        $response2->assertRedirect(route('admin.moderacion.show', $reporte->id));
        $response2->assertSessionHas('error', 'El reporte cambió. Revisa su información actual antes de continuar.');
    }

    /**
     * Prueba 14: Error de guardado sin cambios parciales (Atomicidad).
     * Requerimiento: Si ocurre un error, conservar el estado anterior sin publicaciones parciales.
     */
    public function test_error_de_guardado_sin_cambios_parciales(): void
    {
        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0014',
            'nombre' => 'USB 128GB SanDisk',
            'descripcion' => 'Memoria USB metálica',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'disponible' => false,
            'version' => 1,
        ]);

        // Simular falla interceptando el evento de DecisionModeracion
        DecisionModeracion::saving(function () {
            throw new \RuntimeException('Error simulado en base de datos');
        });

        try {
            $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
                'lock_version' => 1,
            ]);
        } catch (\RuntimeException $e) {
            // Excepción forzada
        }

        $reporteActualizado = $reporte->fresh();

        // Sin cambios parciales
        $this->assertEquals(Publicacion::ESTADO_PENDIENTE, $reporteActualizado->estado);
        $this->assertFalse((bool) $reporteActualizado->disponible);
        $this->assertNull($reporteActualizado->fecha_primera_aprobacion);
        $this->assertNull($reporteActualizado->fecha_vencimiento);
        $this->assertEquals(1, $reporteActualizado->version);
        $this->assertEquals(0, DecisionModeracion::count());
    }

    /**
     * Prueba 15: Registro correcto en el historial de moderación.
     * Requerimiento: Registrar cada decisión, su responsable y conservar el historial.
     */
    public function test_registro_correcto_en_el_historial(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Prof. Carlos Mendoza',
        ]);

        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0015',
            'nombre' => 'Mochila Deportiva Puma',
            'descripcion' => 'Mochila roja con tenis de fútbol',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => 1,
            'motivo' => 'Revisión exhaustiva completada, cumple con todos los lineamientos.',
        ]);

        $decision = DecisionModeracion::where('publicacion_id', $reporte->id)->first();

        $this->assertNotNull($decision);
        $this->assertEquals($admin->id, $decision->user_id);
        $this->assertEquals(DecisionModeracion::TIPO_APROBADA, $decision->tipo_decision);
        $this->assertEquals('Revisión exhaustiva completada, cumple con todos los lineamientos.', $decision->motivo);
        $this->assertNotNull($decision->fecha_decision);

        // Visible en el detalle del reporte
        $responseShow = $this->actingAs($admin)->get(route('admin.moderacion.show', $reporte->id));
        $responseShow->assertSee('Prof. Carlos Mendoza');
        $responseShow->assertSee('Revisión exhaustiva completada, cumple con todos los lineamientos.');
    }
}
