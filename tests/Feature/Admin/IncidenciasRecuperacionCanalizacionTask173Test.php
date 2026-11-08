<?php

namespace Tests\Feature\Admin;

use App\Models\Devolucion;
use App\Models\Incidencia;
use App\Models\Publicacion;
use App\Models\Recepcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncidenciasRecuperacionCanalizacionTask173Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $autor;
    protected User $usuarioGeneral;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 11, 8, 14, 0, 0, Publicacion::TIMEZONE));
        Storage::fake('local');

        $this->admin = User::factory()->admin()->create([
            'name' => 'Bibliotecaria Admin Recuperación',
            'email' => 'admin.recuperacion@itmerida.mx',
        ]);

        $this->autor = User::factory()->create([
            'name' => 'Autor Reporte Recuperación',
            'email' => 'autor.recuperacion@itmerida.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $this->usuarioGeneral = User::factory()->create([
            'name' => 'Usuario General Externo',
            'email' => 'externo.recuperacion@itmerida.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function crearIncidenciaEnEsperaDeRecuperacion(string $folio = 'OBJ-20261108-0301', string $nombre = 'Laptop Lenovo ThinkPad'): array
    {
        $archivo = UploadedFile::fake()->create('evidencia_entrega_incorrecta.jpg', 120, 'image/jpeg');
        $rutaEvidencia = $archivo->store('devoluciones/evidencias', 'local');

        $pub = Publicacion::create([
            'user_id' => $this->autor->id,
            'folio' => $folio,
            'nombre' => $nombre,
            'titulo' => $nombre,
            'descripcion' => 'Descripción detallada para ' . $nombre,
            'fecha_hallazgo' => '2026-11-01',
            'hora_hallazgo' => '09:00',
            'area_zona' => 'Centro de Cómputo',
            'lugar_hallazgo' => 'Centro de Cómputo',
            'ubicacion_especifica' => 'Mesa 3',
            'estado' => Publicacion::ESTADO_DEVUELTO,
            'disponible' => false,
            'devolucion_en_proceso' => false,
            'incidencia_abierta' => true,
            'tiene_incidencia' => true,
            'estado_devolucion' => Incidencia::ESTADO_ESPERA_RECUPERACION,
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_primera_aprobacion' => Carbon::now(Publicacion::TIMEZONE)->subDays(4),
            'fecha_aprobacion' => Carbon::now(Publicacion::TIMEZONE)->subDays(4),
            'fecha_vencimiento' => Carbon::now(Publicacion::TIMEZONE)->addDays(10),
        ]);

        $dev = Devolucion::create([
            'publicacion_id' => $pub->id,
            'user_id' => $this->autor->id,
            'admin_id' => $this->admin->id,
            'receptor_nombre' => 'Receptor Equivocado',
            'receptor_identificador' => '21089988',
            'fecha_entrega' => '2026-11-05',
            'hora_entrega' => '13:00',
            'explicacion_propiedad' => 'Mostró funda parecida pero otro número de serie.',
            'fotografia_ruta' => $rutaEvidencia,
            'fotografia_nombre_original' => 'evidencia_entrega_incorrecta.jpg',
            'estado' => Devolucion::ESTADO_PENDIENTE,
            'fecha_captura' => Carbon::now(Publicacion::TIMEZONE)->subDays(2),
        ]);

        $incidencia = Incidencia::create([
            'publicacion_id' => $pub->id,
            'devolucion_id' => $dev->id,
            'user_id' => $this->admin->id,
            'responsable_revision_id' => $this->admin->id,
            'motivo' => 'El propietario real acreditó con factura que el número de serie le pertenece.',
            'estado' => Incidencia::ESTADO_ESPERA_RECUPERACION,
            'resultado' => 'Entrega incorrecta confirmada — Solicitud de entrega física en biblioteca',
            'motivo_resolucion' => 'Se confirmó que el receptor no era el dueño legítimo.',
            'solicitud_recuperacion' => 'Se solicita llevar físicamente el objeto a biblioteca institucional.',
            'recuperacion_confirmada' => false,
            'fecha_apertura' => Carbon::now(Publicacion::TIMEZONE)->subHours(5),
            'fecha_revision' => Carbon::now(Publicacion::TIMEZONE)->subHours(3),
        ]);

        return [$pub, $dev, $incidencia];
    }

    public function test_promesa_de_devolver_el_objeto_no_se_considera_recuperacion_ni_permite_cerrar_la_incidencia(): void
    {
        [$pub, $dev, $incidencia] = $this->crearIncidenciaEnEsperaDeRecuperacion();

        // 1. Registrar en observaciones que el receptor prometió devolver el objeto mañana
        $this->actingAs($this->admin)
            ->post(route('admin.incidencias.observaciones.store', $incidencia->id), [
                'tipo' => 'Contacto por medio externo',
                'medio_contacto' => 'Llamada telefónica',
                'descripcion' => 'Se contactó al receptor equivocado para solicitar la devolución del equipo.',
                'resultado_obtenido' => 'El receptor prometió llevar el objeto mañana a la biblioteca.',
            ])
            ->assertRedirect(route('admin.incidencias.show', $incidencia->id));

        $incidencia->refresh();
        $this->assertFalse($incidencia->hasRecuperacionFisicaConfirmada());
        $this->assertSame(Incidencia::ESTADO_ESPERA_RECUPERACION, $incidencia->estado);

        // 2. Intentar cerrar la incidencia solo con la promesa (sin recepción física en biblioteca) debe ser rechazado
        $responseCerrar = $this->actingAs($this->admin)
            ->post(route('admin.incidencias.cerrar', $incidencia->id), [
                'resultado' => 'Intento de cierre por promesa',
                'motivo' => 'El receptor prometió llevarlo mañana.',
            ]);

        $responseCerrar->assertRedirect(route('admin.incidencias.show', $incidencia->id));
        $responseCerrar->assertSessionHasErrors('recuperacion');

        $incidencia->refresh();
        $pub->refresh();
        $this->assertSame(Incidencia::ESTADO_ESPERA_RECUPERACION, $incidencia->estado);
        $this->assertNull($incidencia->fecha_cierre);
        $this->assertFalse($pub->disponible);
        $this->assertTrue($pub->incidencia_abierta);
    }

    public function test_completa_recuperacion_mediante_recepcion_fisica_conserva_antecedentes_y_permite_cerrar_caso(): void
    {
        [$pub, $dev, $incidencia] = $this->crearIncidenciaEnEsperaDeRecuperacion();

        // 1. Completar recuperación física desde la incidencia (vinculada con recepción física en biblioteca)
        $responseRec = $this->actingAs($this->admin)
            ->post(route('admin.incidencias.recuperacion-fisica', $incidencia->id), [
                'confirmacion_cotejo' => '1',
                'fecha_recepcion' => '2026-11-08',
                'hora_recepcion' => '11:30',
                'ubicacion_resguardo' => 'Gaveta B-4 de resguardo en biblioteca',
                'condicion_objeto' => 'Laptop recibida en buen estado físico con cargador.',
                'observaciones' => 'Entregada físicamente en mostrador de biblioteca.',
            ]);

        $responseRec->assertRedirect(route('admin.incidencias.show', $incidencia->id));
        $responseRec->assertSessionHas('status');

        $incidencia->refresh();
        $pub->refresh();
        $dev->refresh();

        // Se vinculó la recepción física con la incidencia y el reporte original
        $this->assertTrue($incidencia->hasRecuperacionFisicaConfirmada());
        $this->assertNotNull($incidencia->recepcion_id);
        $this->assertNotNull($incidencia->fecha_recuperacion);

        $recepcion = Recepcion::find($incidencia->recepcion_id);
        $this->assertNotNull($recepcion);
        $this->assertSame($pub->id, $recepcion->publicacion_id);
        $this->assertSame($incidencia->id, $recepcion->incidencia_id);
        $this->assertSame('Gaveta B-4 de resguardo en biblioteca', $recepcion->ubicacion_resguardo);

        // Se conserva intacto el antecedente y la evidencia fotográfica de la entrega incorrecta, sin marcarla como correcta
        $this->assertDatabaseHas('devoluciones', [
            'id' => $dev->id,
            'receptor_nombre' => 'Receptor Equivocado',
            'fotografia_ruta' => $dev->fotografia_ruta,
        ]);
        $this->assertNotSame(Devolucion::ESTADO_VERIFICADA, $dev->estado);
        $this->assertTrue(Storage::disk('local')->exists($dev->fotografia_ruta));

        // El objeto no se publica automáticamente en el muro
        $this->assertFalse($pub->disponible);
        $this->assertSame('En biblioteca', $pub->situacion_resguardo);

        // 2. Ahora que la recuperación física en biblioteca está confirmada, se permite cerrar el caso con motivo, fecha y responsable
        $responseCerrar = $this->actingAs($this->admin)
            ->post(route('admin.incidencias.cerrar', $incidencia->id), [
                'resultado' => 'Objeto recuperado físicamente en biblioteca — Incidencia cerrada',
                'motivo' => 'Se verificó el ingreso físico del equipo al resguardo de biblioteca y se cierra la incidencia.',
            ]);

        $responseCerrar->assertRedirect(route('admin.incidencias.show', $incidencia->id));
        $responseCerrar->assertSessionHas('status');

        $incidencia->refresh();
        $pub->refresh();

        $this->assertSame(Incidencia::ESTADO_CERRADA, $incidencia->estado);
        $this->assertSame($this->admin->id, $incidencia->cerrada_por_id);
        $this->assertNotNull($incidencia->fecha_cierre);
        $this->assertSame('Se verificó el ingreso físico del equipo al resguardo de biblioteca y se cierra la incidencia.', $incidencia->motivo_resolucion);

        // El objeto permanece en resguardo de biblioteca y fuera del muro público
        $this->assertFalse($pub->disponible);
        $this->assertFalse($pub->incidencia_abierta);
        $this->assertTrue($pub->tiene_incidencia);
        $this->assertSame('En biblioteca', $pub->situacion_resguardo);
    }

    public function test_tambien_vincula_recuperacion_cuando_se_registra_desde_controlador_de_recepciones(): void
    {
        [$pub, $dev, $incidencia] = $this->crearIncidenciaEnEsperaDeRecuperacion('OBJ-20261108-0302', 'Tablet Samsung S8');

        // El reporte con incidencia en espera de recuperación es elegible en RecepcionController
        $this->actingAs($this->admin)
            ->get(route('admin.recepciones.create', ['id' => $pub->id, 'from_incidencia' => 1]))
            ->assertOk()
            ->assertSee('Incidencia #' . $incidencia->id);

        $responseStore = $this->actingAs($this->admin)
            ->post(route('admin.recepciones.store', $pub->id), [
                'from_incidencia' => '1',
                'confirmacion_cotejo' => '1',
                'fecha_recepcion' => '2026-11-08',
                'hora_recepcion' => '12:15',
                'ubicacion_resguardo' => 'Estante C-1 de biblioteca',
                'condicion_objeto' => 'Tablet en buen estado con funda azul.',
                'observaciones' => 'Recuperada tras seguimiento administrativo.',
            ]);

        $responseStore->assertRedirect(route('admin.incidencias.show', $incidencia->id));

        $incidencia->refresh();
        $dev->refresh();
        $this->assertTrue($incidencia->hasRecuperacionFisicaConfirmada());
        $this->assertNotNull($incidencia->recepcion_id);
        $this->assertNotSame(Devolucion::ESTADO_VERIFICADA, $dev->estado);
    }

    public function test_marcar_sin_resolver_y_canalizada_con_destino_institucional_y_observaciones_sin_sanciones(): void
    {
        [$pub, $dev, $incidencia] = $this->crearIncidenciaEnEsperaDeRecuperacion('OBJ-20261108-0303', 'Audífonos Sony WH-1000XM4');

        // 1. Validar que rechace campos vacíos o de solo espacios
        $this->actingAs($this->admin)
            ->post(route('admin.incidencias.canalizar', $incidencia->id), [
                'motivo' => '   ',
                'responsable_canalizacion' => '   ',
                'observaciones_seguimiento' => '   ',
            ])
            ->assertSessionHasErrors(['motivo', 'responsable_canalizacion', 'observaciones_seguimiento']);

        // 2. Canalizar el caso registrando motivo, destino institucional y observaciones
        $response = $this->actingAs($this->admin)
            ->post(route('admin.incidencias.canalizar', $incidencia->id), [
                'motivo' => 'El receptor no acudió a entregar el objeto tras tres avisos formales.',
                'responsable_canalizacion' => 'Subdirección de Servicios Administrativos',
                'observaciones_seguimiento' => 'Se turnan antecedentes de la entrega y bitácora de contactos realizados para su atención institucional.',
            ]);

        $response->assertRedirect(route('admin.incidencias.show', $incidencia->id));
        $response->assertSessionHas('status');

        $incidencia->refresh();
        $pub->refresh();
        $this->autor->refresh();

        $this->assertSame(Incidencia::ESTADO_SIN_RESOLVER_CANALIZADA, $incidencia->estado);
        $this->assertSame('Sin resolver y canalizada', $incidencia->resultado);
        $this->assertSame('El receptor no acudió a entregar el objeto tras tres avisos formales.', $incidencia->motivo_resolucion);
        $this->assertSame('Subdirección de Servicios Administrativos', $incidencia->responsable_canalizacion);
        $this->assertSame('Se turnan antecedentes de la entrega y bitácora de contactos realizados para su atención institucional.', $incidencia->observaciones_seguimiento);
        $this->assertSame($this->admin->id, $incidencia->cerrada_por_id);
        $this->assertNotNull($incidencia->fecha_cierre);

        // El objeto NO debe marcarse como «Devuelto» ni ofrecerse como «Disponible»
        $this->assertNotSame(Publicacion::ESTADO_DEVUELTO, $pub->estado);
        $this->assertNotSame('Disponible', $pub->estado);
        $this->assertFalse($pub->disponible);
        $this->assertFalse($pub->isDisponible());

        // Sin sanciones automáticas para el autor del reporte
        $this->assertSame(User::ROLE_USUARIO_GENERAL, $this->autor->role);

        // El autor solo ve el seguimiento autorizado «Sin resolver y canalizada» sin datos privados del receptor ni del destino interno
        $this->actingAs($this->autor)
            ->get(route('reportes.show', $pub->id))
            ->assertOk()
            ->assertSee('Sin resolver y canalizada')
            ->assertDontSee('Receptor Equivocado')
            ->assertDontSee('Subdirección de Servicios Administrativos');
    }
}
