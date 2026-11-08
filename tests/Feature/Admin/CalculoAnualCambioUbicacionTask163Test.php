<?php

namespace Tests\Feature\Admin;

use App\Models\DecisionModeracion;
use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\Recepcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculoAnualCambioUbicacionTask163Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Crea un escenario base con reporte y recepción en resguardo.
     */
    protected function crearEscenario(array $overridesRecepcion = [], array $overridesReporte = []): array
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin.biblioteca@itmerida.mx',
            'name' => 'Lic. María González (Bibliotecaria)',
        ]);

        $autor = User::factory()->create([
            'email' => 'autor.estudiante@itmerida.mx',
            'name' => 'Carlos Estudiante',
        ]);

        $primeraAprobacion = Carbon::create(2026, 10, 20, 10, 0, 0, Recepcion::TIMEZONE);
        $vencimiento = Carbon::create(2026, 11, 3, 10, 0, 0, Recepcion::TIMEZONE);

        $reporteData = array_merge([
            'user_id' => $autor->id,
            'nombre' => 'Laptop Dell Inspiron 15',
            'descripcion' => 'Laptop plateada con cargador encontrada en biblioteca central.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'area_zona' => 'Edificio D',
            'lugar_hallazgo' => 'Mesa de lectura 4',
            'situacion_resguardo' => 'En biblioteca',
            'fecha_hallazgo' => Carbon::create(2026, 10, 15, 9, 0, 0, Recepcion::TIMEZONE)->toDateString(),
            'hora_hallazgo' => '09:00:00',
            'fecha_captura' => Carbon::create(2026, 10, 15, 10, 0, 0, Recepcion::TIMEZONE),
            'fecha_primera_aprobacion' => $primeraAprobacion,
            'fecha_aprobacion' => $primeraAprobacion,
            'fecha_vencimiento' => $vencimiento,
            'devolucion_en_proceso' => false,
            'estado_devolucion' => null,
            'version' => 1,
        ], $overridesReporte);

        $reporte = Publicacion::create($reporteData);

        $foto = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'fotografias/laptop15.jpg',
            'nombre_original' => 'laptop15.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 150000,
            'es_principal' => true,
        ]);

        $recepcionData = array_merge([
            'publicacion_id' => $reporte->id,
            'user_id' => $admin->id,
            'fecha_recepcion' => '2026-11-10',
            'hora_recepcion' => '10:30',
            'fecha_captura' => Carbon::create(2026, 11, 10, 14, 0, 0, Recepcion::TIMEZONE),
            'condicion_objeto' => 'Laptop en buen estado con funda protectora.',
            'ubicacion_resguardo' => 'Estante Tecnológico A-1, Gaveta 3',
            'observaciones' => 'Sin observaciones extraordinarias.',
            'confirmacion_cotejo' => true,
            'fecha_limite_anual' => '2027-11-10',
        ], $overridesRecepcion);

        $recepcion = Recepcion::create($recepcionData);

        return [$admin, $autor, $reporte, $recepcion, $foto];
    }

    /**
     * 1. Cálculo anual ordinario (10/11/2026 10:30 -> 10/11/2027 10:30).
     */
    public function test_01_calculo_anual_ordinario(): void
    {
        $limite = Recepcion::calcularFechaLimiteAnual('2026-11-10', '10:30');

        $this->assertEquals('2027-11-10 10:30:00', $limite->format('Y-m-d H:i:s'));
        $this->assertEquals('America/Merida', $limite->getTimezone()->getName());

        // Verificación con modelo persistido
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenario([
            'fecha_recepcion' => '2026-11-10',
            'hora_recepcion' => '10:30',
        ]);

        $this->assertEquals('2027-11-10 10:30:00', $recepcion->fecha_limite_anual_completa->format('Y-m-d H:i:s'));
        $this->assertEquals('2027-11-10', $recepcion->fecha_limite_anual->format('Y-m-d'));
    }

    /**
     * 2. Recepción el 29 de febrero (29/02/2024 15:45 -> 28/02/2025 15:45).
     * Aplicar regla de calendario addYearNoOverflow que no desplace a marzo.
     */
    public function test_02_recepcion_el_29_de_febrero(): void
    {
        $limite = Recepcion::calcularFechaLimiteAnual('2024-02-29', '15:45');

        $this->assertEquals('2025-02-28 15:45:00', $limite->format('Y-m-d H:i:s'));
        $this->assertNotEquals('03', $limite->format('m'), 'No debe desplazarse al mes de marzo');

        // Verificación con modelo persistido
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenario([
            'fecha_recepcion' => '2024-02-29',
            'hora_recepcion' => '15:45',
            'fecha_limite_anual' => null, // Dejar que el boot lo calcule automáticamente
        ]);

        $this->assertEquals('2025-02-28', $recepcion->fecha_limite_anual->format('Y-m-d'));
        $this->assertEquals('2025-02-28 15:45:00', $recepcion->fecha_limite_anual_completa->format('Y-m-d H:i:s'));
    }

    /**
     * 3. Recepción el 28 de febrero (28/02/2024 09:00 -> 28/02/2025 09:00).
     */
    public function test_03_recepcion_el_28_de_febrero(): void
    {
        $limite = Recepcion::calcularFechaLimiteAnual('2024-02-28', '09:00');

        $this->assertEquals('2025-02-28 09:00:00', $limite->format('Y-m-d H:i:s'));

        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenario([
            'fecha_recepcion' => '2024-02-28',
            'hora_recepcion' => '09:00',
            'fecha_limite_anual' => null,
        ]);

        $this->assertEquals('2025-02-28', $recepcion->fecha_limite_anual->format('Y-m-d'));
        $this->assertEquals('2025-02-28 09:00:00', $recepcion->fecha_limite_anual_completa->format('Y-m-d H:i:s'));
    }

    /**
     * 4. Fecha de captura diferente de la recepción (límite no afectado).
     */
    public function test_04_fecha_de_captura_diferente_de_la_recepcion(): void
    {
        // Entrega física el 10/11/2026 10:30, pero capturado 5 días después el 15/11/2026 18:00
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenario([
            'fecha_recepcion' => '2026-11-10',
            'hora_recepcion' => '10:30',
            'fecha_captura' => Carbon::create(2026, 11, 15, 18, 0, 0, Recepcion::TIMEZONE),
            'fecha_limite_anual' => null,
        ]);

        // El límite DEBE basarse estrictamente en la fecha física de recepción
        $this->assertEquals('2027-11-10', $recepcion->fecha_limite_anual->format('Y-m-d'));
        $this->assertEquals('2027-11-10 10:30:00', $recepcion->fecha_limite_anual_completa->format('Y-m-d H:i:s'));
        $this->assertNotEquals('2027-11-15', $recepcion->fecha_limite_anual->format('Y-m-d'));
    }

    /**
     * 5. Objeto antes del límite anual («Bajo resguardo»).
     */
    public function test_05_objeto_antes_del_limite_anual_bajo_resguardo(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenario([
            'fecha_recepcion' => '2026-11-10',
            'hora_recepcion' => '10:30',
        ]);

        // Simular 1 segundo antes del límite (10/11/2027 10:29:59)
        Carbon::setTestNow(Carbon::parse('2027-11-10 10:29:59', Recepcion::TIMEZONE));

        $this->assertFalse($recepcion->isAnualVencido());
        $this->assertEquals('Bajo resguardo', $recepcion->estado_resguardo);

        $responseIndex = $this->actingAs($admin)->get(route('admin.inventario.index'));
        $responseIndex->assertStatus(200);
        $responseIndex->assertSee('Bajo resguardo');
        $responseIndex->assertDontSee('Resguardo vencido');

        $responseShow = $this->actingAs($admin)->get(route('admin.inventario.show', $recepcion->id));
        $responseShow->assertStatus(200);
        $responseShow->assertSee('Bajo resguardo');
        $responseShow->assertDontSee('Resguardo vencido');
    }

    /**
     * 6. Objeto exactamente en el límite anual («Resguardo vencido»).
     */
    public function test_06_objeto_exactamente_en_el_limite_anual_resguardo_vencido(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenario([
            'fecha_recepcion' => '2026-11-10',
            'hora_recepcion' => '10:30',
        ]);

        // Simular exactamente en el límite (10/11/2027 10:30:00)
        Carbon::setTestNow(Carbon::parse('2027-11-10 10:30:00', Recepcion::TIMEZONE));

        $this->assertTrue($recepcion->isAnualVencido());
        $this->assertEquals('Resguardo vencido', $recepcion->estado_resguardo);

        $responseIndex = $this->actingAs($admin)->get(route('admin.inventario.index'));
        $responseIndex->assertStatus(200);
        $responseIndex->assertSee('Resguardo vencido');

        $responseShow = $this->actingAs($admin)->get(route('admin.inventario.show', $recepcion->id));
        $responseShow->assertStatus(200);
        $responseShow->assertSee('Resguardo vencido');
    }

    /**
     * 7. Objeto después del límite anual («Resguardo vencido»).
     */
    public function test_07_objeto_despues_del_limite_anual_resguardo_vencido(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenario([
            'fecha_recepcion' => '2026-11-10',
            'hora_recepcion' => '10:30',
        ]);

        // 1 segundo después del límite (10/11/2027 10:30:01)
        Carbon::setTestNow(Carbon::parse('2027-11-10 10:30:01', Recepcion::TIMEZONE));

        $this->assertTrue($recepcion->isAnualVencido());
        $this->assertEquals('Resguardo vencido', $recepcion->estado_resguardo);

        // Meses después (15/12/2027)
        Carbon::setTestNow(Carbon::parse('2027-12-15 12:00:00', Recepcion::TIMEZONE));

        $this->assertTrue($recepcion->isAnualVencido());
        $this->assertEquals('Resguardo vencido', $recepcion->estado_resguardo);

        $response = $this->actingAs($admin)->get(route('admin.inventario.show', $recepcion->id));
        $response->assertStatus(200);
        $response->assertSee('Resguardo vencido');
    }

    /**
     * 8. Objeto vencido permanece en inventario.
     */
    public function test_08_objeto_vencido_permanece_en_inventario(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenario([
            'fecha_recepcion' => '2026-11-10',
            'hora_recepcion' => '10:30',
        ]);

        // Fecha futura donde ya venció el resguardo
        Carbon::setTestNow(Carbon::parse('2028-03-01 10:00:00', Recepcion::TIMEZONE));

        $response = $this->actingAs($admin)->get(route('admin.inventario.index'));
        $response->assertStatus(200);

        // El objeto sigue apareciendo en el inventario con sus datos
        $response->assertSee($reporte->folio);
        $response->assertSee($reporte->nombre);
        $response->assertSee('Resguardo vencido');
        $this->assertDatabaseCount('recepciones', 1);
    }

    /**
     * 9. No ocurre eliminación automática.
     */
    public function test_09_no_ocurre_eliminacion_automatica(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenario([
            'fecha_recepcion' => '2026-11-10',
            'hora_recepcion' => '10:30',
        ]);

        // Simular 3 años después
        Carbon::setTestNow(Carbon::parse('2029-11-10 10:30:00', Recepcion::TIMEZONE));

        // Consultar el inventario no debe disparar ninguna eliminación
        $this->actingAs($admin)->get(route('admin.inventario.index'));
        $this->actingAs($admin)->get(route('admin.inventario.show', $recepcion->id));

        $this->assertDatabaseHas('recepciones', [
            'id' => $recepcion->id,
            'publicacion_id' => $reporte->id,
        ]);

        $this->assertDatabaseHas('publicaciones', [
            'id' => $reporte->id,
            'folio' => $reporte->folio,
        ]);
    }

    /**
     * 10. No ocurre salida automática.
     */
    public function test_10_no_ocurre_salida_automatica(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenario([
            'fecha_recepcion' => '2026-11-10',
            'hora_recepcion' => '10:30',
        ]);

        // Simular fecha posterior al vencimiento
        Carbon::setTestNow(Carbon::parse('2028-01-01 10:00:00', Recepcion::TIMEZONE));

        $this->actingAs($admin)->get(route('admin.inventario.show', $recepcion->id));

        $reporteFresh = $reporte->fresh();
        // Mantiene la condición de resguardo físico en biblioteca y no devuelto ni salido
        $this->assertEquals('En biblioteca', $reporteFresh->situacion_resguardo);
        $this->assertFalse((bool) $reporteFresh->disponible);
        $this->assertNull($reporteFresh->estado_devolucion);
        $this->assertFalse((bool) $reporteFresh->devolucion_en_proceso);
    }

    /**
     * 11. Administrador cambia ubicación.
     */
    public function test_11_administrador_cambia_ubicacion(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenario();

        $nuevaUbicacion = 'Estante Confidencial B-2, Gaveta 9, Nivel 3';

        $response = $this->actingAs($admin)->put(route('admin.inventario.update-ubicacion', $recepcion->id), [
            'ubicacion_resguardo' => $nuevaUbicacion,
        ]);

        $response->assertRedirect(route('admin.inventario.show', $recepcion->id));
        $response->assertSessionHas('status', 'La ubicación interna del objeto se actualizó correctamente.');

        $this->assertDatabaseHas('recepciones', [
            'id' => $recepcion->id,
            'ubicacion_resguardo' => $nuevaUbicacion,
        ]);

        $recepcionFresh = $recepcion->fresh();
        $this->assertEquals($nuevaUbicacion, $recepcionFresh->ubicacion_resguardo);
    }

    /**
     * 12. Usuario general no puede cambiar ubicación (403).
     */
    public function test_12_usuario_general_no_puede_cambiar_ubicacion_403(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenario();

        $ubicacionOriginal = $recepcion->ubicacion_resguardo;

        $response = $this->actingAs($autor)->put(route('admin.inventario.update-ubicacion', $recepcion->id), [
            'ubicacion_resguardo' => 'Ubicación no autorizada',
        ]);

        $response->assertStatus(403);

        $this->assertEquals($ubicacionOriginal, $recepcion->fresh()->ubicacion_resguardo);
    }

    /**
     * 13. Ubicación vacía rechazada.
     */
    public function test_13_ubicacion_vacia_rechazada(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenario();
        $ubicacionOriginal = $recepcion->ubicacion_resguardo;

        $response = $this->actingAs($admin)->put(route('admin.inventario.update-ubicacion', $recepcion->id), [
            'ubicacion_resguardo' => '',
        ]);

        $response->assertSessionHasErrors(['ubicacion_resguardo']);
        $this->assertEquals($ubicacionOriginal, $recepcion->fresh()->ubicacion_resguardo);
    }

    /**
     * 14. Ubicación con espacios rechazada.
     */
    public function test_14_ubicacion_con_espacios_rechazada(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenario();
        $ubicacionOriginal = $recepcion->ubicacion_resguardo;

        $response = $this->actingAs($admin)->put(route('admin.inventario.update-ubicacion', $recepcion->id), [
            'ubicacion_resguardo' => '     ',
        ]);

        $response->assertSessionHasErrors(['ubicacion_resguardo']);
        $this->assertEquals($ubicacionOriginal, $recepcion->fresh()->ubicacion_resguardo);
    }

    /**
     * 15. Registro de fecha y responsable del cambio.
     */
    public function test_15_registro_de_fecha_y_responsable_del_cambio(): void
    {
        [$admin1, $autor, $reporte, $recepcion] = $this->crearEscenario();

        $admin2 = User::factory()->admin()->create([
            'email' => 'segundo.admin@itmerida.mx',
            'name' => 'Ing. Roberto Méndez',
        ]);

        $momentoCambio = Carbon::create(2026, 11, 25, 16, 20, 0, Recepcion::TIMEZONE);
        Carbon::setTestNow($momentoCambio);

        $this->actingAs($admin2)->put(route('admin.inventario.update-ubicacion', $recepcion->id), [
            'ubicacion_resguardo' => 'Módulo Central, Casillero 14',
        ]);

        $recepcionFresh = $recepcion->fresh();

        $this->assertEquals($admin2->id, $recepcionFresh->actualizado_por_id);
        $this->assertEquals('Ing. Roberto Méndez', $recepcionFresh->actualizador->name);
        $this->assertNotNull($recepcionFresh->fecha_cambio_ubicacion);
        $this->assertEquals('2026-11-25 16:20:00', $recepcionFresh->fecha_cambio_ubicacion->format('Y-m-d H:i:s'));
    }

    /**
     * 16. Fecha de recepción original sin alteraciones.
     */
    public function test_16_fecha_de_recepcion_original_sin_alteraciones(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenario([
            'fecha_recepcion' => '2026-11-10',
            'hora_recepcion' => '10:30',
            'fecha_captura' => Carbon::create(2026, 11, 10, 14, 0, 0, Recepcion::TIMEZONE),
            'condicion_objeto' => 'Condición original intacta.',
        ]);

        $fechaRecepcionOriginal = $recepcion->fecha_recepcion->toDateString();
        $horaRecepcionOriginal = $recepcion->hora_recepcion;
        $fechaCapturaOriginal = $recepcion->getRawOriginal('fecha_captura');
        $condicionOriginal = $recepcion->condicion_objeto;
        $autorOriginalId = $reporte->user_id;
        $folioOriginal = $reporte->folio;

        $this->actingAs($admin)->put(route('admin.inventario.update-ubicacion', $recepcion->id), [
            'ubicacion_resguardo' => 'Nueva ubicación 2026',
        ]);

        $recepcionFresh = $recepcion->fresh();
        $reporteFresh = $reporte->fresh();

        $this->assertEquals($fechaRecepcionOriginal, $recepcionFresh->fecha_recepcion->toDateString());
        $this->assertEquals($horaRecepcionOriginal, $recepcionFresh->hora_recepcion);
        $this->assertEquals($fechaCapturaOriginal, $recepcionFresh->getRawOriginal('fecha_captura'));
        $this->assertEquals($condicionOriginal, $recepcionFresh->condicion_objeto);
        $this->assertEquals($autorOriginalId, $reporteFresh->user_id);
        $this->assertEquals($folioOriginal, $reporteFresh->folio);
    }

    /**
     * 17. Límite anual sin alteraciones.
     */
    public function test_17_limite_anual_sin_alteraciones(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenario([
            'fecha_recepcion' => '2026-11-10',
            'hora_recepcion' => '10:30',
            'fecha_limite_anual' => '2027-11-10',
        ]);

        $limiteOriginal = $recepcion->fecha_limite_anual->toDateString();
        $limiteCompletoOriginal = $recepcion->fecha_limite_anual_completa->toDateTimeString();

        $this->actingAs($admin)->put(route('admin.inventario.update-ubicacion', $recepcion->id), [
            'ubicacion_resguardo' => 'Nueva ubicación de prueba',
        ]);

        $recepcionFresh = $recepcion->fresh();
        $this->assertEquals($limiteOriginal, $recepcionFresh->fecha_limite_anual->toDateString());
        $this->assertEquals($limiteCompletoOriginal, $recepcionFresh->fecha_limite_anual_completa->toDateTimeString());
    }

    /**
     * 18. Historial de eventos conservado (evento registrado).
     */
    public function test_18_historial_de_eventos_conservado(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenario([
            'ubicacion_resguardo' => 'Estante Alfa-1',
        ]);

        $ahora = Carbon::create(2026, 11, 22, 12, 0, 0, Recepcion::TIMEZONE);
        Carbon::setTestNow($ahora);

        $this->actingAs($admin)->put(route('admin.inventario.update-ubicacion', $recepcion->id), [
            'ubicacion_resguardo' => 'Estante Beta-2',
        ]);

        $this->assertDatabaseHas('decisiones_moderacion', [
            'publicacion_id' => $reporte->id,
            'user_id' => $admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_CAMBIO_UBICACION_BIBLIOTECA,
        ]);

        $decision = DecisionModeracion::where('publicacion_id', $reporte->id)
            ->where('tipo_decision', DecisionModeracion::TIPO_CAMBIO_UBICACION_BIBLIOTECA)
            ->first();

        $this->assertNotNull($decision);
        $this->assertStringContainsString('Estante Alfa-1', $decision->motivo);
        $this->assertStringContainsString('Estante Beta-2', $decision->motivo);

        // Comprobar que aparece en el timeline unificado de getEventosHistorial
        $eventos = $reporte->fresh()->getEventosHistorial();
        $eventoCambio = $eventos->firstWhere('tipo_slug', 'cambio_ubicacion');

        $this->assertNotNull($eventoCambio);
        $this->assertEquals('Cambio de ubicación en biblioteca', $eventoCambio['tipo']);
    }

    /**
     * 19. Errores sin actualizaciones parciales (transacción).
     */
    public function test_19_errores_sin_actualizaciones_parciales_transaccion(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenario([
            'ubicacion_resguardo' => 'Estante Seguro 1',
        ]);

        // Simular que el objeto ya no está bajo resguardo (e.g. devuelto)
        $reporte->situacion_resguardo = 'Devuelto';
        $reporte->save();

        $response = $this->actingAs($admin)->put(route('admin.inventario.update-ubicacion', $recepcion->id), [
            'ubicacion_resguardo' => 'Estante Nuevo 2',
        ]);

        // Debe ser rechazado
        $response->assertSessionHasErrors(['ubicacion_resguardo']);

        // Ni la ubicación ni ninguna decisión de moderación debieron registrarse
        $this->assertEquals('Estante Seguro 1', $recepcion->fresh()->ubicacion_resguardo);
        $this->assertDatabaseMissing('decisiones_moderacion', [
            'publicacion_id' => $reporte->id,
            'tipo_decision' => DecisionModeracion::TIPO_CAMBIO_UBICACION_BIBLIOTECA,
        ]);
    }
}

