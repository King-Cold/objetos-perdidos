<?php

namespace Tests\Feature\Admin;

use App\Models\DecisionModeracion;
use App\Models\Devolucion;
use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\Recepcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecepcionVencimientoTask161Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Crea un escenario base con fecha fija en America/Merida.
     */
    protected function crearEscenarioVencido(array $overrides = []): array
    {
        $ahora = Carbon::create(2026, 11, 20, 11, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($ahora);

        $admin = User::factory()->admin()->create([
            'email' => 'admin.biblioteca@itmerida.mx',
            'name' => 'Lic. María González (Bibliotecaria)',
        ]);

        $autor = User::factory()->create([
            'email' => 'autor.estudiante@itmerida.mx',
            'name' => 'Carlos Estudiante',
        ]);

        $primeraAprobacion = Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE);
        $vencimiento = Carbon::create(2026, 11, 15, 10, 0, 0, Publicacion::TIMEZONE);

        $reporteData = array_merge([
            'user_id' => $autor->id,
            'nombre' => 'Calculadora Científica HP Prime',
            'descripcion' => 'Calculadora graficadora color plata con funda negra olvidada en laboratorio.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false, // Fuera del muro por vencida
            'area_zona' => 'Edificio D',
            'lugar_hallazgo' => 'Laboratorio D-4',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_hallazgo' => Carbon::create(2026, 10, 30, 9, 30, 0, Publicacion::TIMEZONE)->toDateString(),
            'hora_hallazgo' => '09:30:00',
            'fecha_captura' => Carbon::create(2026, 10, 30, 10, 0, 0, Publicacion::TIMEZONE),
            'fecha_primera_aprobacion' => $primeraAprobacion,
            'fecha_aprobacion' => $primeraAprobacion,
            'fecha_vencimiento' => $vencimiento,
            'devolucion_en_proceso' => false,
            'estado_devolucion' => null,
            'version' => 1,
        ], $overrides);

        $reporte = Publicacion::create($reporteData);

        // Fotografía asociada
        $foto = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'fotografias/calculadora.jpg',
            'nombre_original' => 'calculadora.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 204800,
            'es_principal' => true,
        ]);

        return [$admin, $autor, $reporte, $foto, $ahora];
    }

    /**
     * 1. Administrador autorizado puede registrar recepción física.
     */
    public function test_01_administrador_autorizado_puede_registrar_recepcion_fisica(): void
    {
        [$admin, $autor, $reporte, $foto, $ahora] = $this->crearEscenarioVencido();

        // 0. El detalle de moderación muestra la opción «Registrar recepción»
        $resShowBefore = $this->actingAs($admin)->get(route('admin.moderacion.show', $reporte->id));
        $resShowBefore->assertOk();
        $resShowBefore->assertSee('Registrar recepción');

        // 1. Consulta la vista de cotejo previo y formulario
        $resCreate = $this->actingAs($admin)->get(route('admin.recepciones.create', $reporte->id));
        $resCreate->assertOk();
        $resCreate->assertSee($reporte->folio);
        $resCreate->assertSee('Calculadora Científica HP Prime');
        $resCreate->assertSee('Confirmo que he recibido físicamente el objeto');

        // 2. Envía el formulario completando los datos obligatorios
        $payload = [
            'confirmacion_cotejo' => '1',
            'fecha_recepcion' => '2026-11-19',
            'hora_recepcion' => '14:30',
            'condicion_objeto' => 'Calculadora en óptimas condiciones con stylus y funda original.',
            'ubicacion_resguardo' => 'Estante B-1, Gaveta 3 de resguardo institucional',
            'observaciones' => 'Entregada personalmente por el estudiante autor del reporte.',
        ];

        $resStore = $this->actingAs($admin)->post(route('admin.recepciones.store', $reporte->id), $payload);

        $resStore->assertRedirect(route('admin.moderacion.show', $reporte->id));
        $resStore->assertSessionHas('status', 'La recepción del objeto se registró correctamente');

        // Comprobación en base de datos
        $this->assertDatabaseHas('recepciones', [
            'publicacion_id' => $reporte->id,
            'user_id' => $admin->id,
            'fecha_recepcion' => '2026-11-19',
            'hora_recepcion' => '14:30',
            'ubicacion_resguardo' => 'Estante B-1, Gaveta 3 de resguardo institucional',
            'confirmacion_cotejo' => true,
        ]);

        $reporte->refresh();
        $this->assertSame('En biblioteca', $reporte->situacion_resguardo);
        $this->assertFalse($reporte->disponible);

        // 3. Tras registrarse, el detalle ya no muestra el botón de registrar recepción
        $resShowAfter = $this->actingAs($admin)->get(route('admin.moderacion.show', $reporte->id));
        $resShowAfter->assertOk();
        $resShowAfter->assertDontSee(route('admin.recepciones.create', $reporte->id));
    }

    /**
     * 2. Usuario general no puede registrarla (403 o redirección según corresponda).
     */
    public function test_02_usuario_general_no_puede_registrar_recepcion(): void
    {
        [$admin, $autor, $reporte] = $this->crearEscenarioVencido();

        $usuarioGeneral = User::factory()->create(['role' => User::ROLE_USUARIO_GENERAL]);

        // Intento de acceder a la vista
        $resCreate = $this->actingAs($usuarioGeneral)->get(route('admin.recepciones.create', $reporte->id));
        $resCreate->assertForbidden();

        // Intento de guardar
        $resStore = $this->actingAs($usuarioGeneral)->post(route('admin.recepciones.store', $reporte->id), [
            'confirmacion_cotejo' => '1',
            'fecha_recepcion' => '2026-11-19',
            'hora_recepcion' => '12:00',
            'condicion_objeto' => 'Intento no autorizado',
            'ubicacion_resguardo' => 'Biblioteca',
        ]);
        $resStore->assertForbidden();

        $this->assertDatabaseMissing('recepciones', [
            'publicacion_id' => $reporte->id,
        ]);
    }

    /**
     * 3. Visitante no autenticado es redirigido al login.
     */
    public function test_03_visitante_no_autenticado_es_redirigido_al_login(): void
    {
        [$admin, $autor, $reporte] = $this->crearEscenarioVencido();

        $resCreate = $this->get(route('admin.recepciones.create', $reporte->id));
        $resCreate->assertRedirect(route('login'));

        $resStore = $this->post(route('admin.recepciones.store', $reporte->id), [
            'confirmacion_cotejo' => '1',
        ]);
        $resStore->assertRedirect(route('login'));
    }

    /**
     * 4. Reporte vencido sin devolución permite recepción.
     */
    public function test_04_reporte_vencido_sin_devolucion_permite_recepcion(): void
    {
        [$admin, $autor, $reporte] = $this->crearEscenarioVencido();

        $this->assertTrue($reporte->isVencida());
        $this->assertTrue($reporte->esElegibleParaRecepcionFisica());

        $res = $this->actingAs($admin)->post(route('admin.recepciones.store', $reporte->id), [
            'confirmacion_cotejo' => '1',
            'fecha_recepcion' => '2026-11-18',
            'hora_recepcion' => '10:00',
            'condicion_objeto' => 'Buen estado general',
            'ubicacion_resguardo' => 'Gaveta Central A',
        ]);

        $res->assertRedirect(route('admin.moderacion.show', $reporte->id));
        $this->assertDatabaseCount('recepciones', 1);
    }

    /**
     * 5. Reporte vigente no permite recepción.
     */
    public function test_05_reporte_vigente_no_permite_recepcion(): void
    {
        $ahora = Carbon::create(2026, 11, 5, 12, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($ahora);

        $admin = User::factory()->admin()->create();
        $autor = User::factory()->create();

        $reporteVigente = Publicacion::create([
            'user_id' => $autor->id,
            'nombre' => 'Mochila Nike Vigente',
            'descripcion' => 'Mochila deportiva en excelente estado.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Edificio C',
            'fecha_hallazgo' => '2026-11-01',
            'fecha_captura' => '2026-11-01 10:00:00',
            'fecha_primera_aprobacion' => '2026-11-02 10:00:00',
            'fecha_aprobacion' => '2026-11-02 10:00:00',
            'fecha_vencimiento' => Carbon::create(2026, 11, 16, 10, 0, 0, Publicacion::TIMEZONE), // Vigente por 11 días
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'devolucion_en_proceso' => false,
        ]);

        $this->assertFalse($reporteVigente->isVencida());
        $this->assertFalse($reporteVigente->esElegibleParaRecepcionFisica());

        // GET redirige con error
        $resCreate = $this->actingAs($admin)->get(route('admin.recepciones.create', $reporteVigente->id));
        $resCreate->assertRedirect(route('admin.moderacion.show', $reporteVigente->id));
        $resCreate->assertSessionHas('error');

        // POST es rechazado
        $resStore = $this->actingAs($admin)->post(route('admin.recepciones.store', $reporteVigente->id), [
            'confirmacion_cotejo' => '1',
            'fecha_recepcion' => '2026-11-05',
            'hora_recepcion' => '11:00',
            'condicion_objeto' => 'Intento en publicación activa',
            'ubicacion_resguardo' => 'Estante 1',
        ]);

        $resStore->assertRedirect(route('admin.moderacion.show', $reporteVigente->id));
        $resStore->assertSessionHas('error');
        $this->assertDatabaseMissing('recepciones', ['publicacion_id' => $reporteVigente->id]);
    }

    /**
     * 6. Reporte con devolución ya registrada no permite recepción.
     */
    public function test_06_reporte_con_devolucion_ya_registrada_no_permite_recepcion(): void
    {
        [$admin, $autor, $reporte] = $this->crearEscenarioVencido([
            'estado_devolucion' => 'Verificada',
            'estado' => Publicacion::ESTADO_DEVUELTO,
        ]);

        Devolucion::create([
            'publicacion_id' => $reporte->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Dueño Original',
            'receptor_identificador' => '20080123',
            'fecha_entrega' => '2026-11-10',
            'hora_entrega' => '11:00:00',
            'fecha_captura' => Carbon::now(Publicacion::TIMEZONE),
            'fotografia_ruta' => 'devoluciones/entrega.jpg',
            'explicacion_propiedad' => 'Demostró ser el dueño mostrando la factura.',
            'estado' => Devolucion::ESTADO_VERIFICADA,
        ]);

        $this->assertFalse($reporte->esElegibleParaRecepcionFisica());

        $res = $this->actingAs($admin)->post(route('admin.recepciones.store', $reporte->id), [
            'confirmacion_cotejo' => '1',
            'fecha_recepcion' => '2026-11-18',
            'hora_recepcion' => '10:00',
            'condicion_objeto' => 'No debe recibirse',
            'ubicacion_resguardo' => 'Biblioteca',
        ]);

        $res->assertRedirect(route('admin.moderacion.show', $reporte->id));
        $this->assertDatabaseMissing('recepciones', ['publicacion_id' => $reporte->id]);
    }

    /**
     * 7. Reporte con devolución pendiente de verificación no permite recepción.
     */
    public function test_07_reporte_con_devolucion_pendiente_de_verificacion_no_permite_recepcion(): void
    {
        [$admin, $autor, $reporte] = $this->crearEscenarioVencido([
            'devolucion_en_proceso' => true,
            'estado_devolucion' => 'Pendiente de verificación',
        ]);

        Devolucion::create([
            'publicacion_id' => $reporte->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Receptor En Espera',
            'receptor_identificador' => '20080999',
            'fecha_entrega' => '2026-11-12',
            'hora_entrega' => '10:00:00',
            'fecha_captura' => Carbon::now(Publicacion::TIMEZONE),
            'fotografia_ruta' => 'devoluciones/pendiente.jpg',
            'explicacion_propiedad' => 'Entrega en verificación administrativa.',
            'estado' => Devolucion::ESTADO_PENDIENTE,
        ]);

        $this->assertFalse($reporte->esElegibleParaRecepcionFisica());

        $res = $this->actingAs($admin)->post(route('admin.recepciones.store', $reporte->id), [
            'confirmacion_cotejo' => '1',
            'fecha_recepcion' => '2026-11-18',
            'hora_recepcion' => '10:00',
            'condicion_objeto' => 'No debe recibirse con devolución pendiente',
            'ubicacion_resguardo' => 'Biblioteca',
        ]);

        $res->assertRedirect(route('admin.moderacion.show', $reporte->id));
        $this->assertDatabaseMissing('recepciones', ['publicacion_id' => $reporte->id]);
    }

    /**
     * 8. Reporte ya recibido en biblioteca no permite duplicar recepción.
     */
    public function test_08_reporte_ya_recibido_en_biblioteca_no_permite_duplicar_recepcion(): void
    {
        [$admin, $autor, $reporte] = $this->crearEscenarioVencido();

        // Primera recepción válida
        $this->actingAs($admin)->post(route('admin.recepciones.store', $reporte->id), [
            'confirmacion_cotejo' => '1',
            'fecha_recepcion' => '2026-11-18',
            'hora_recepcion' => '10:00',
            'condicion_objeto' => 'Primera recepción exitosa',
            'ubicacion_resguardo' => 'Estante A1',
        ]);

        $this->assertDatabaseCount('recepciones', 1);
        $reporte->refresh();
        $this->assertSame('En biblioteca', $reporte->situacion_resguardo);
        $this->assertFalse($reporte->esElegibleParaRecepcionFisica());

        // Intento de segunda recepción sobre el mismo reporte
        $resDuplicado = $this->actingAs($admin)->post(route('admin.recepciones.store', $reporte->id), [
            'confirmacion_cotejo' => '1',
            'fecha_recepcion' => '2026-11-19',
            'hora_recepcion' => '11:00',
            'condicion_objeto' => 'Intento duplicado',
            'ubicacion_resguardo' => 'Estante A2',
        ]);

        $resDuplicado->assertRedirect(route('admin.moderacion.show', $reporte->id));
        $resDuplicado->assertSessionHas('error');

        // Se mantiene únicamente un solo registro de recepción
        $this->assertDatabaseCount('recepciones', 1);
    }

    /**
     * 9. Obligatoriedad de la confirmación física previa.
     */
    public function test_09_obligatoriedad_de_la_confirmacion_fisica_previa(): void
    {
        [$admin, $autor, $reporte] = $this->crearEscenarioVencido();

        // Sin marcar el checkbox de cotejo
        $res = $this->actingAs($admin)->post(route('admin.recepciones.store', $reporte->id), [
            'fecha_recepcion' => '2026-11-18',
            'hora_recepcion' => '10:00',
            'condicion_objeto' => 'Objeto entregado',
            'ubicacion_resguardo' => 'Estante 3',
        ]);

        $res->assertSessionHasErrors(['confirmacion_cotejo']);
        $this->assertDatabaseMissing('recepciones', ['publicacion_id' => $reporte->id]);
    }

    /**
     * 10. Rechazo de campos obligatorios vacíos o con espacios.
     */
    public function test_10_rechazo_de_campos_obligatorios_vacios_o_con_espacios(): void
    {
        [$admin, $autor, $reporte] = $this->crearEscenarioVencido();

        $res = $this->actingAs($admin)->post(route('admin.recepciones.store', $reporte->id), [
            'confirmacion_cotejo' => '1',
            'fecha_recepcion' => '   ',
            'hora_recepcion' => '   ',
            'condicion_objeto' => '     ',
            'ubicacion_resguardo' => '    ',
        ]);

        $res->assertSessionHasErrors([
            'fecha_recepcion',
            'hora_recepcion',
            'condicion_objeto',
            'ubicacion_resguardo',
        ]);
        $this->assertDatabaseMissing('recepciones', ['publicacion_id' => $reporte->id]);
    }

    /**
     * 11. Rechazo de fecha u hora física futura.
     */
    public function test_11_rechazo_de_fecha_u_hora_fisica_futura(): void
    {
        [$admin, $autor, $reporte, $foto, $ahora] = $this->crearEscenarioVencido();

        // Fecha de mañana
        $manana = $ahora->copy()->addDay()->toDateString();
        $resFechaFutura = $this->actingAs($admin)->post(route('admin.recepciones.store', $reporte->id), [
            'confirmacion_cotejo' => '1',
            'fecha_recepcion' => $manana,
            'hora_recepcion' => '10:00',
            'condicion_objeto' => 'Buen estado',
            'ubicacion_resguardo' => 'Estante 1',
        ]);
        $resFechaFutura->assertSessionHasErrors(['fecha_recepcion']);

        // Mismo día pero hora posterior a la actual (ahora es 11:00)
        $resHoraFutura = $this->actingAs($admin)->post(route('admin.recepciones.store', $reporte->id), [
            'confirmacion_cotejo' => '1',
            'fecha_recepcion' => $ahora->toDateString(),
            'hora_recepcion' => '15:00', // Futura respecto a 11:00
            'condicion_objeto' => 'Buen estado',
            'ubicacion_resguardo' => 'Estante 1',
        ]);
        $resHoraFutura->assertSessionHasErrors(['hora_recepcion']);

        $this->assertDatabaseMissing('recepciones', ['publicacion_id' => $reporte->id]);
    }

    /**
     * 12. Rechazo de fecha física anterior al hallazgo.
     */
    public function test_12_rechazo_de_fecha_fisica_anterior_al_hallazgo(): void
    {
        [$admin, $autor, $reporte] = $this->crearEscenarioVencido();

        // Hallazgo fue 2026-10-30. Enviar fecha anterior 2026-10-25
        $res = $this->actingAs($admin)->post(route('admin.recepciones.store', $reporte->id), [
            'confirmacion_cotejo' => '1',
            'fecha_recepcion' => '2026-10-25',
            'hora_recepcion' => '10:00',
            'condicion_objeto' => 'Buen estado',
            'ubicacion_resguardo' => 'Estante 1',
        ]);

        $res->assertSessionHasErrors(['fecha_recepcion']);
        $this->assertDatabaseMissing('recepciones', ['publicacion_id' => $reporte->id]);
    }

    /**
     * 13. Conservación del folio y autor original tras la recepción.
     */
    public function test_13_conservacion_del_folio_y_autor_original_tras_la_recepcion(): void
    {
        [$admin, $autor, $reporte, $foto] = $this->crearEscenarioVencido();

        $folioOriginal = $reporte->folio;
        $autorIdOriginal = $reporte->user_id;

        $this->actingAs($admin)->post(route('admin.recepciones.store', $reporte->id), [
            'confirmacion_cotejo' => '1',
            'fecha_recepcion' => '2026-11-18',
            'hora_recepcion' => '10:00',
            'condicion_objeto' => 'Sin raspaduras',
            'ubicacion_resguardo' => 'Gaveta B-2',
        ]);

        $reporte->refresh();
        $this->assertSame($folioOriginal, $reporte->folio);
        $this->assertSame($autorIdOriginal, $reporte->user_id);
        $this->assertSame($foto->id, $reporte->foto_principal->id);
    }

    /**
     * 14. Diferenciación correcta entre fecha física y fecha de captura.
     */
    public function test_14_diferenciacion_correcta_entre_fecha_fisica_y_fecha_de_captura(): void
    {
        [$admin, $autor, $reporte, $foto, $ahora] = $this->crearEscenarioVencido();

        // Recepción física ocurrió hace 3 días (2026-11-17 a las 09:15)
        $this->actingAs($admin)->post(route('admin.recepciones.store', $reporte->id), [
            'confirmacion_cotejo' => '1',
            'fecha_recepcion' => '2026-11-17',
            'hora_recepcion' => '09:15',
            'condicion_objeto' => 'Completo',
            'ubicacion_resguardo' => 'Gaveta 1',
        ]);

        $recepcion = Recepcion::where('publicacion_id', $reporte->id)->firstOrFail();

        // 1. Fecha física entregada
        $this->assertSame('2026-11-17', $recepcion->fecha_recepcion->format('Y-m-d'));
        $this->assertSame('09:15', $recepcion->hora_recepcion);

        // 2. Fecha de captura automática del sistema (hoy 2026-11-20 11:00)
        $this->assertSame('2026-11-20 11:00:00', $recepcion->fecha_captura->format('Y-m-d H:i:s'));

        // 3. Vencimiento anual computado a partir de la fecha física (2026-11-17 + 1 año = 2027-11-17)
        $this->assertSame('2027-11-17', $recepcion->fecha_limite_anual->format('Y-m-d'));
    }

    /**
     * 15. Asignación correcta del usuario responsable de biblioteca.
     */
    public function test_15_asignacion_correcta_del_usuario_responsable_de_biblioteca(): void
    {
        [$admin, $autor, $reporte] = $this->crearEscenarioVencido();

        $this->actingAs($admin)->post(route('admin.recepciones.store', $reporte->id), [
            'confirmacion_cotejo' => '1',
            'fecha_recepcion' => '2026-11-18',
            'hora_recepcion' => '10:00',
            'condicion_objeto' => 'Buen estado',
            'ubicacion_resguardo' => 'Gaveta A',
        ]);

        $recepcion = Recepcion::where('publicacion_id', $reporte->id)->firstOrFail();
        $this->assertSame($admin->id, $recepcion->user_id);
        $this->assertSame($admin->name, $recepcion->user->name);

        // Registro de moderación también conserva al responsable
        $decision = DecisionModeracion::where('publicacion_id', $reporte->id)
            ->where('tipo_decision', 'recepcion_biblioteca')
            ->firstOrFail();
        $this->assertSame($admin->id, $decision->user_id);
    }

    /**
     * 16. Control de concurrencia o prevención de doble envío.
     */
    public function test_16_control_de_concurrencia_o_prevencion_de_doble_envio(): void
    {
        [$admin, $autor, $reporte] = $this->crearEscenarioVencido();

        $admin2 = User::factory()->admin()->create();

        // Admin 1 procesa la recepción
        $res1 = $this->actingAs($admin)->post(route('admin.recepciones.store', $reporte->id), [
            'confirmacion_cotejo' => '1',
            'fecha_recepcion' => '2026-11-18',
            'hora_recepcion' => '10:00',
            'condicion_objeto' => 'Recepción Admin 1',
            'ubicacion_resguardo' => 'Estante 1',
        ]);
        $res1->assertRedirect(route('admin.moderacion.show', $reporte->id));

        // Admin 2 intenta procesar simultáneamente o posterior el mismo reporte
        $res2 = $this->actingAs($admin2)->post(route('admin.recepciones.store', $reporte->id), [
            'confirmacion_cotejo' => '1',
            'fecha_recepcion' => '2026-11-18',
            'hora_recepcion' => '10:00',
            'condicion_objeto' => 'Recepción Admin 2',
            'ubicacion_resguardo' => 'Estante 2',
        ]);

        $res2->assertRedirect(route('admin.moderacion.show', $reporte->id));
        $res2->assertSessionHas('error');

        // Solo 1 registro existe en la tabla de recepciones
        $this->assertDatabaseCount('recepciones', 1);
    }

    /**
     * 17. Reversión de cambios en caso de error (transacción).
     */
    public function test_17_reversion_de_cambios_en_caso_de_error_transaccion(): void
    {
        [$admin, $autor, $reporte] = $this->crearEscenarioVencido();

        // Forzamos un fallo simulado en la base de datos dentro de la transacción
        // interceptando el evento creating de Recepcion o simulando una excepción
        Recepcion::creating(function () {
            throw new \RuntimeException('Fallo forzado en base de datos para probar atomicidad.');
        });

        $res = $this->actingAs($admin)->post(route('admin.recepciones.store', $reporte->id), [
            'confirmacion_cotejo' => '1',
            'fecha_recepcion' => '2026-11-18',
            'hora_recepcion' => '10:00',
            'condicion_objeto' => 'Buen estado',
            'ubicacion_resguardo' => 'Estante 1',
        ]);

        // Verificamos que no se crearon registros parciales y el reporte no fue alterado
        $this->assertDatabaseCount('recepciones', 0);
        $this->assertDatabaseMissing('decisiones_moderacion', [
            'publicacion_id' => $reporte->id,
            'tipo_decision' => 'recepcion_biblioteca',
        ]);

        $reporte->refresh();
        $this->assertSame(Publicacion::RESGUARDO_AUTOR, $reporte->situacion_resguardo);
    }

    /**
     * 18. La publicación recibida permanece fuera del muro público.
     */
    public function test_18_la_publicacion_recibida_permanece_fuera_del_muro_publico(): void
    {
        [$admin, $autor, $reporte] = $this->crearEscenarioVencido();

        $this->actingAs($admin)->post(route('admin.recepciones.store', $reporte->id), [
            'confirmacion_cotejo' => '1',
            'fecha_recepcion' => '2026-11-18',
            'hora_recepcion' => '10:00',
            'condicion_objeto' => 'Resguardo confirmado',
            'ubicacion_resguardo' => 'Gaveta B',
        ]);

        $reporte->refresh();
        $this->assertFalse($reporte->disponible);
        $this->assertFalse($reporte->isDisponible());
        $this->assertSame('En biblioteca', $reporte->situacion_resguardo);

        // No aparece en el muro público
        $resMuro = $this->get(route('muro'));
        $resMuro->assertOk();
        $resMuro->assertDontSee($reporte->folio);

        // Búsqueda en el muro no la expone
        $resBusqueda = $this->get(route('muro', ['buscar' => 'Calculadora HP']));
        $resBusqueda->assertOk();
        $resBusqueda->assertDontSee($reporte->folio);

        // Acceso directo por URL pública no está disponible y redirige al muro con aviso
        $resDetalle = $this->get(route('publicaciones.show', $reporte->id));
        $resDetalle->assertRedirect();
        $this->assertStringContainsString('/muro', $resDetalle->headers->get('Location'));
        $resDetalle->assertSessionHas('warning', 'Esta publicación no está disponible para consulta');
    }
}
