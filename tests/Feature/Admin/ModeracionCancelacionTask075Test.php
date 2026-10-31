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

class ModeracionCancelacionTask075Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Restaurar el reloj del sistema al finalizar
        parent::tearDown();
    }

    /**
     * Prueba 1: Cancelación válida realizada por Administrador.
     */
    public function test_cancelacion_valida_realizada_por_administrador(): void
    {
        $admin = User::factory()->admin()->create();
        $autor = User::factory()->create();

        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261101-0001',
            'nombre' => 'Mochila Nike Negra',
            'descripcion' => 'Mochila encontrada en bancas del edificio A',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
            'disponible' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporte->id), [
            'motivo' => 'Publicación repetida con objeto ya devuelto',
            'lock_version' => 1,
            'updated_at_token' => $reporte->updated_at?->timestamp,
        ]);

        $response->assertRedirect(route('admin.moderacion.show', $reporte->id));
        $response->assertSessionHas('status');

        $reporteActualizado = $reporte->fresh();
        $this->assertEquals(Publicacion::ESTADO_CANCELADO, $reporteActualizado->estado);
        $this->assertFalse((bool) $reporteActualizado->disponible);
        $this->assertEquals(2, $reporteActualizado->version);
    }

    /**
     * Prueba 2: Acceso denegado a usuario general.
     */
    public function test_acceso_denegado_a_usuario_general(): void
    {
        $usuarioGeneral = User::factory()->create(); // No admin
        $reporte = Publicacion::create([
            'user_id' => $usuarioGeneral->id,
            'folio' => 'OBJ-20261101-0002',
            'nombre' => 'Termo Stanley Azul',
            'descripcion' => 'Termo azul con calcomanías',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
            'disponible' => true,
        ]);

        // Intento de cancelación por usuario general debe ser prohibido (403)
        $response = $this->actingAs($usuarioGeneral)->post(route('admin.moderacion.cancelar', $reporte->id), [
            'motivo' => 'Intento no autorizado de cancelación',
            'lock_version' => 1,
        ]);

        $response->assertForbidden();

        $this->assertEquals(Publicacion::ESTADO_APROBADA, $reporte->fresh()->estado);
    }

    /**
     * Prueba 3: Motivo vacío es rechazado.
     */
    public function test_motivo_vacio_es_rechazado(): void
    {
        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0003',
            'nombre' => 'Suéter Gris',
            'descripcion' => 'Suéter en cafetería',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
            'disponible' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporte->id), [
            'motivo' => '',
            'lock_version' => 1,
        ]);

        $response->assertRedirect(route('admin.moderacion.show', $reporte->id));
        $response->assertSessionHasErrors('motivo');

        $this->assertEquals(Publicacion::ESTADO_APROBADA, $reporte->fresh()->estado);
        $this->assertTrue((bool) $reporte->fresh()->disponible);
    }

    /**
     * Prueba 4: Motivo compuesto únicamente por espacios es rechazado.
     */
    public function test_motivo_compuesto_unicamente_por_espacios_es_rechazado(): void
    {
        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0004',
            'nombre' => 'Audífonos Sony',
            'descripcion' => 'Audífonos en laboratorio 2',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
            'disponible' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporte->id), [
            'motivo' => "   \t   \n   ",
            'lock_version' => 1,
        ]);

        $response->assertRedirect(route('admin.moderacion.show', $reporte->id));
        $response->assertSessionHasErrors('motivo');

        $this->assertEquals(Publicacion::ESTADO_APROBADA, $reporte->fresh()->estado);
    }

    /**
     * Prueba 5: Cerrar modal sin modificar datos (interfaz y persistencia).
     */
    public function test_cerrar_modal_sin_modificar_datos(): void
    {
        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0005',
            'nombre' => 'Gafas de sol',
            'descripcion' => 'Gafas oscuras con estuche',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
            'disponible' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.moderacion.show', $reporte->id));
        $response->assertOk();
        $response->assertSee('Cancelar publicación');
        $response->assertSee('modalCancelarOpen = false', false);
        $response->assertSee('Volver');

        // Confirmar que no hubo cambios en la base de datos
        $this->assertEquals(Publicacion::ESTADO_APROBADA, $reporte->fresh()->estado);
        $this->assertEquals(1, $reporte->fresh()->version);
    }

    /**
     * Prueba 6: Cambio correcto a estado cancelado.
     */
    public function test_cambio_correcto_a_estado_cancelado(): void
    {
        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0006',
            'nombre' => 'Calculadora Casio',
            'descripcion' => 'Calculadora científica blanca',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
            'disponible' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporte->id), [
            'motivo' => 'Cancelación solicitada por administración institucional',
            'lock_version' => 1,
        ]);

        $reporteActualizado = $reporte->fresh();
        $this->assertEquals(Publicacion::ESTADO_CANCELADO, $reporteActualizado->estado);
        $this->assertFalse((bool) $reporteActualizado->disponible);
    }

    /**
     * Prueba 7: Exclusión del muro público.
     */
    public function test_exclusion_del_muro_publico(): void
    {
        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0007',
            'nombre' => 'Mochila Verde Militar',
            'descripcion' => 'Mochila camuflaje en pasillo D',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
            'disponible' => true,
        ]);

        // Verificar que antes de cancelar aparece en el muro
        $muroAntes = $this->get(route('muro'));
        $muroAntes->assertOk();
        $muroAntes->assertSee('Mochila Verde Militar');

        // Cancelar el reporte
        $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporte->id), [
            'motivo' => 'Reporte retirado del muro por duplicidad comprobada',
            'lock_version' => 1,
        ]);

        // Comprobar que scope de muro no lo contiene
        $idsEnMuro = Publicacion::disponiblesMuro()->pluck('id')->toArray();
        $this->assertNotContains($reporte->id, $idsEnMuro);

        // Limpiar la sesión para evitar mensajes flash remanentes y consultar como visitante
        $this->flushSession();

        // Verificar que después de cancelar NO aparece en el muro
        $muroDespues = $this->get(route('muro'));
        $muroDespues->assertOk();
        $muroDespues->assertDontSee('Mochila Verde Militar');
        $muroDespues->assertDontSee($reporte->folio);
    }

    /**
     * Prueba 8: Exclusión de búsquedas públicas.
     */
    public function test_exclusion_de_busquedas_publicas(): void
    {
        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0008',
            'nombre' => 'Guitarra Electroacústica Fender',
            'descripcion' => 'Guitarra acústica café en aula magna',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
            'disponible' => true,
        ]);

        // Cancelar el reporte
        $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporte->id), [
            'motivo' => 'Cancelación por datos no verídicos',
            'lock_version' => 1,
        ]);

        // Limpiar sesión para evitar que el folio aparezca en el mensaje flash de éxito
        $this->flushSession();

        // Búsqueda en muro no debe retornar el reporte cancelado
        $busqueda = $this->get('/muro?buscar=Fender');
        $busqueda->assertOk();
        $busqueda->assertDontSee($reporte->folio);
        $busqueda->assertDontSee('Guitarra Electroacústica Fender');
    }

    /**
     * Prueba 9: Bloqueo del detalle general para publicaciones canceladas.
     */
    public function test_bloqueo_del_detalle_general(): void
    {
        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0009',
            'nombre' => 'Reloj Garmin Forerunner',
            'descripcion' => 'Reloj deportivo negro en canchas de fútbol',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
            'disponible' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporte->id), [
            'motivo' => 'Retiro de publicación cancelada',
            'lock_version' => 1,
        ]);

        // Acceso al detalle público general debe redirigir al muro con advertencia
        $response = $this->get(route('publicaciones.show', $reporte->id));
        $response->assertRedirect(route('muro'));
        $response->assertSessionHas('warning', 'Esta publicación no está disponible para consulta');
    }

    /**
     * Prueba 10: Conservación del folio, contenido y fotografías.
     */
    public function test_conservacion_del_folio_contenido_y_fotografias(): void
    {
        $admin = User::factory()->admin()->create();
        $autor = User::factory()->create();

        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261101-0010',
            'nombre' => 'Libro Cálculo Stewart',
            'descripcion' => 'Séptima edición con notas en lápiz',
            'lugar_hallazgo' => 'Biblioteca planta baja',
            'ubicacion_especifica' => 'Mesa central frente a ventanilla',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
            'disponible' => true,
        ]);

        $foto = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'fotografias/libro_calculo.jpg',
            'nombre_original' => 'libro_calculo.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 1024,
        ]);

        $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporte->id), [
            'motivo' => 'Duplicado administrativo confirmado',
            'lock_version' => 1,
        ]);

        $reporteActualizado = $reporte->fresh();
        $this->assertEquals('OBJ-20261101-0010', $reporteActualizado->folio);
        $this->assertEquals('Libro Cálculo Stewart', $reporteActualizado->nombre);
        $this->assertEquals('Séptima edición con notas en lápiz', $reporteActualizado->descripcion);
        $this->assertEquals('Biblioteca planta baja', $reporteActualizado->lugar_hallazgo);
        $this->assertEquals('Mesa central frente a ventanilla', $reporteActualizado->ubicacion_especifica);

        // La fotografía no se borra ni física ni lógicamente
        $this->assertDatabaseHas('fotografias', [
            'id' => $foto->id,
            'publicacion_id' => $reporte->id,
            'ruta' => 'fotografias/libro_calculo.jpg',
        ]);
    }

    /**
     * Prueba 11: Registro del motivo, responsable y fecha.
     */
    public function test_registro_del_motivo_responsable_y_fecha(): void
    {
        $fechaOperacion = Carbon::create(2026, 11, 1, 14, 30, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($fechaOperacion);

        $admin = User::factory()->admin()->create([
            'name' => 'Ing. Roberto Méndez',
        ]);

        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0011',
            'nombre' => 'Cargador Dell 65W',
            'descripcion' => 'Cargador tipo C negro',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
            'disponible' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporte->id), [
            'motivo' => 'Reclamado y cancelado administrativamente',
            'lock_version' => 1,
        ]);

        $decision = DecisionModeracion::where('publicacion_id', $reporte->id)->latest()->first();

        $this->assertNotNull($decision);
        $this->assertEquals($admin->id, $decision->user_id);
        $this->assertEquals(DecisionModeracion::TIPO_CANCELADA, $decision->tipo_decision);
        $this->assertStringContainsString('Reclamado y cancelado administrativamente', $decision->motivo);
        $this->assertEquals($fechaOperacion->format('Y-m-d H:i:s'), $decision->fecha_decision->format('Y-m-d H:i:s'));
    }

    /**
     * Prueba 12: Conservación del historial de decisiones anteriores.
     */
    public function test_conservacion_del_historial_de_decisiones(): void
    {
        $admin1 = User::factory()->admin()->create();
        $admin2 = User::factory()->admin()->create();

        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0012',
            'nombre' => 'Audífonos JBL',
            'descripcion' => 'Audífonos rojos',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 2,
            'disponible' => true,
        ]);

        // Simular primera decisión histórica (aprobación)
        DecisionModeracion::create([
            'publicacion_id' => $reporte->id,
            'user_id' => $admin1->id,
            'tipo_decision' => DecisionModeracion::TIPO_APROBADA,
            'motivo' => 'Publicación autorizada inicialmente',
            'fecha_decision' => Carbon::now(Publicacion::TIMEZONE)->subDays(2),
        ]);

        // Segunda decisión: Cancelación
        $this->actingAs($admin2)->post(route('admin.moderacion.cancelar', $reporte->id), [
            'motivo' => 'Cancelación definitiva del reporte',
            'lock_version' => 2,
        ]);

        $decisiones = DecisionModeracion::where('publicacion_id', $reporte->id)
            ->orderBy('id', 'asc')
            ->get();

        $this->assertCount(2, $decisiones);
        $this->assertEquals(DecisionModeracion::TIPO_APROBADA, $decisiones[0]->tipo_decision);
        $this->assertEquals(DecisionModeracion::TIPO_CANCELADA, $decisiones[1]->tipo_decision);
        $this->assertEquals($admin2->id, $decisiones[1]->user_id);
    }

    /**
     * Prueba 13: Documentación manual de duplicados (folio de referencia conservado).
     */
    public function test_documentacion_manual_de_duplicados(): void
    {
        $admin = User::factory()->admin()->create();

        $reporteConservado = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0013',
            'nombre' => 'Mochila Roja',
            'descripcion' => 'Reporte principal activo',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
            'disponible' => true,
        ]);

        $reporteDuplicado = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0014',
            'nombre' => 'Mochila Roja duplicada',
            'descripcion' => 'Mismo objeto reportado por otro estudiante',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
            'disponible' => false,
        ]);

        $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporteDuplicado->id), [
            'motivo' => 'Objeto duplicado, se mantiene el reporte previo',
            'folio_referencia_duplicado' => $reporteConservado->folio,
            'lock_version' => 1,
        ]);

        $reporteActualizado = $reporteDuplicado->fresh();
        $this->assertEquals(Publicacion::ESTADO_CANCELADO, $reporteActualizado->estado);
        $this->assertEquals($reporteConservado->folio, $reporteActualizado->folio_referencia_duplicado);

        $decision = DecisionModeracion::where('publicacion_id', $reporteDuplicado->id)->latest()->first();
        $this->assertEquals($reporteConservado->folio, $decision->folio_referencia_duplicado);
        $this->assertStringContainsString($reporteConservado->folio, $decision->motivo);
    }

    /**
     * Prueba 14: Coexistencia de reportes con títulos iguales.
     */
    public function test_coexistencia_de_reportes_con_titulos_iguales(): void
    {
        $admin = User::factory()->admin()->create();

        $reporte1 = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0015',
            'nombre' => 'Paraguas Azul',
            'descripcion' => 'Paraguas plegable azul rey',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
            'disponible' => true,
        ]);

        $reporte2 = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0016',
            'nombre' => 'Paraguas Azul',
            'descripcion' => 'Paraguas plegable azul rey',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
            'disponible' => true,
        ]);

        // Cancelar el segundo documentando el folio del primero
        $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporte2->id), [
            'motivo' => 'Mismo paraguas registrado por error',
            'folio_referencia_duplicado' => $reporte1->folio,
            'lock_version' => 1,
        ]);

        // El primer reporte permanece intacto y en el muro
        $this->assertEquals(Publicacion::ESTADO_APROBADA, $reporte1->fresh()->estado);
        $this->assertTrue((bool) $reporte1->fresh()->disponible);

        // El segundo reporte está cancelado
        $this->assertEquals(Publicacion::ESTADO_CANCELADO, $reporte2->fresh()->estado);
        $this->assertFalse((bool) $reporte2->fresh()->disponible);
    }

    /**
     * Prueba 15: Conservación de evidencias.
     */
    public function test_conservacion_de_evidencias(): void
    {
        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0017',
            'nombre' => 'Llaves con llavero de Mérida',
            'descripcion' => 'Juego de 3 llaves',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
            'disponible' => true,
        ]);

        $foto1 = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'fotografias/llaves_1.jpg',
            'nombre_original' => 'llaves_1.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 1024,
        ]);

        $foto2 = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'fotografias/llaves_2.jpg',
            'nombre_original' => 'llaves_2.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 2048,
        ]);

        $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporte->id), [
            'motivo' => 'Cancelación de registro de llaves',
            'lock_version' => 1,
        ]);

        $this->assertCount(2, $reporte->fresh()->fotografias);
        $this->assertDatabaseHas('fotografias', ['id' => $foto1->id]);
        $this->assertDatabaseHas('fotografias', ['id' => $foto2->id]);
    }

    /**
     * Prueba 16: Conservación de custodio y resguardo.
     */
    public function test_conservacion_de_custodio_y_resguardo(): void
    {
        $admin = User::factory()->admin()->create();
        $autor = User::factory()->create();

        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261101-0018',
            'nombre' => 'Carpeta de apuntes',
            'descripcion' => 'Carpeta negra con hojas de electromagnetismo',
            'lugar_hallazgo' => 'Laboratorio de física',
            'area_zona' => 'Edificio D',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
            'disponible' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporte->id), [
            'motivo' => 'Retiro de carpeta a petición de la academia',
            'lock_version' => 1,
        ]);

        $reporteActualizado = $reporte->fresh();
        // El autor original, custodio y ubicación no sufren modificaciones
        $this->assertEquals($autor->id, $reporteActualizado->user_id);
        $this->assertEquals('Laboratorio de física', $reporteActualizado->lugar_hallazgo);
        $this->assertEquals('Edificio D', $reporteActualizado->area_zona);
    }

    /**
     * Prueba 17: Protección de devoluciones e incidencias.
     */
    public function test_proteccion_de_devoluciones_e_incidencias(): void
    {
        $admin = User::factory()->admin()->create();

        // Caso A: Reporte con devolución en proceso
        $reporteDevolucion = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0019',
            'nombre' => 'Laptop HP',
            'descripcion' => 'Laptop en proceso de reclamo formal',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
            'disponible' => true,
            'devolucion_en_proceso' => true,
            'incidencia_abierta' => false,
        ]);

        $responseA = $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporteDevolucion->id), [
            'motivo' => 'Intento de cancelar con devolución activa',
            'lock_version' => 1,
        ]);

        $responseA->assertSessionHasErrors('cancelacion');
        $this->assertEquals(Publicacion::ESTADO_APROBADA, $reporteDevolucion->fresh()->estado);

        // Caso B: Reporte con incidencia abierta
        $reporteIncidencia = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0020',
            'nombre' => 'Celular Samsung',
            'descripcion' => 'Celular bajo disputa o incidencia',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
            'disponible' => true,
            'devolucion_en_proceso' => false,
            'incidencia_abierta' => true,
        ]);

        $responseB = $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporteIncidencia->id), [
            'motivo' => 'Intento de cancelar con incidencia',
            'lock_version' => 1,
        ]);

        $responseB->assertSessionHasErrors('cancelacion');
        $this->assertEquals(Publicacion::ESTADO_APROBADA, $reporteIncidencia->fresh()->estado);

        // Caso C: Objeto ya devuelto
        $reporteDevuelto = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0021',
            'nombre' => 'Credencial Tec',
            'descripcion' => 'Credencial entregada formalmente',
            'estado' => 'Devuelto',
            'version' => 1,
            'disponible' => false,
        ]);

        $responseC = $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporteDevuelto->id), [
            'motivo' => 'Intento de cancelar objeto ya devuelto',
            'lock_version' => 1,
        ]);

        $responseC->assertSessionHasErrors('cancelacion');
        $this->assertEquals('Devuelto', $reporteDevuelto->fresh()->estado);
    }

    /**
     * Prueba 18: Detección de versiones desactualizadas (control de concurrencia).
     */
    public function test_deteccion_de_versiones_desactualizadas(): void
    {
        $admin = User::factory()->admin()->create();

        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0022',
            'nombre' => 'Cartera Café',
            'descripcion' => 'Cartera de piel encontrada en pasillo',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 3, // Versión actual en base de datos
            'disponible' => true,
        ]);

        // Formulario envía una versión desactualizada (ej. 2)
        $response = $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporte->id), [
            'motivo' => 'Cancelación con token desactualizado',
            'lock_version' => 2,
        ]);

        $response->assertSessionHasErrors('concurrencia');
        $this->assertEquals(
            'El reporte cambió. Revisa su información actual antes de continuar.',
            session('errors')->first('concurrencia')
        );

        $this->assertEquals(Publicacion::ESTADO_APROBADA, $reporte->fresh()->estado);
    }

    /**
     * Prueba 19: Protección frente a cancelaciones simultáneas.
     */
    public function test_proteccion_frente_a_cancelaciones_simultaneas(): void
    {
        $admin = User::factory()->admin()->create();

        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0023',
            'nombre' => 'Mochila Azul',
            'descripcion' => 'Mochila deportiva',
            'estado' => Publicacion::ESTADO_CANCELADO, // Ya fue cancelado previamente
            'version' => 2,
            'disponible' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporte->id), [
            'motivo' => 'Segundo intento simultáneo de cancelación',
            'lock_version' => 2,
        ]);

        $response->assertSessionHasErrors('cancelacion');
        $this->assertStringContainsString('ya se encuentra cancelado', session('errors')->first('cancelacion'));
    }

    /**
     * Prueba 20: Errores de guardado sin cambios parciales (rollback de transacción).
     */
    public function test_errores_de_guardado_sin_cambios_parciales(): void
    {
        $admin = User::factory()->admin()->create();

        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261101-0024',
            'nombre' => 'Calculadora Texas Instruments',
            'descripcion' => 'Calculadora gráfica',
            'estado' => Publicacion::ESTADO_APROBADA,
            'version' => 1,
            'disponible' => true,
        ]);

        // Interceptamos la creación en DecisionModeracion para simular una falla de base de datos
        DecisionModeracion::creating(function () {
            throw new \RuntimeException('Falla simulada de integridad en historial de decisiones');
        });

        try {
            $this->actingAs($admin)->post(route('admin.moderacion.cancelar', $reporte->id), [
                'motivo' => 'Intento que fallará por transacción',
                'lock_version' => 1,
            ]);
        } catch (\Throwable $e) {
            // Excepción esperada
        }

        // El estado y disponibilidad del reporte original se preservan íntegros por el rollback
        $reporteActualizado = $reporte->fresh();
        $this->assertEquals(Publicacion::ESTADO_APROBADA, $reporteActualizado->estado);
        $this->assertTrue((bool) $reporteActualizado->disponible);
        $this->assertEquals(1, $reporteActualizado->version);
    }
}
