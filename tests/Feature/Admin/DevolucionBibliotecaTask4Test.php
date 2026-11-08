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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DevolucionBibliotecaTask4Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $autor;
    private User $receptor;
    private User $tercero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'email' => 'admin.biblioteca@merida.tecnm.mx',
        ]);

        $this->autor = User::factory()->create([
            'email' => 'autor.original@merida.tecnm.mx',
        ]);

        $this->receptor = User::factory()->create([
            'email' => 'receptor.dueno@merida.tecnm.mx',
        ]);

        $this->tercero = User::factory()->create([
            'email' => 'usuario.ajeno@merida.tecnm.mx',
        ]);
    }

    /**
     * CA-08: Conservación del historial.
     * Se mantienen los antecedentes de recepción, periodo anual de resguardo,
     * ubicaciones anteriores, incidencias y entregas previas, sin sobrescribir registros ni evidencias.
     */
    public function test_01_conserva_antecedentes_recepcion_resguardo_anual_y_eventos_previos(): void
    {
        Storage::fake('local');

        $reporte = Publicacion::factory()->create([
            'user_id' => $this->autor->id,
            'estado' => Publicacion::ESTADO_APROBADA,
            'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO,
            'disponible' => true,
        ]);

        // Registrar antecedentes de recepción física y resguardo anual
        $recepcion = Recepcion::create([
            'publicacion_id' => $reporte->id,
            'user_id' => $this->admin->id,
            'fecha_recepcion' => Carbon::now(Publicacion::TIMEZONE)->subDays(30)->toDateString(),
            'hora_recepcion' => '10:00:00',
            'fecha_captura' => Carbon::now(Publicacion::TIMEZONE)->subDays(30),
            'fecha_limite_anual' => Carbon::now(Publicacion::TIMEZONE)->addDays(335)->toDateString(),
            'condicion_objeto' => 'Excelente estado',
            'ubicacion_resguardo' => 'Estante B-12',
        ]);

        // Registrar decisión de recepción
        DecisionModeracion::create([
            'publicacion_id' => $reporte->id,
            'user_id' => $this->admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_RECEPCION_BIBLIOTECA,
            'motivo' => 'Recepción física inicial en biblioteca',
            'fecha_decision' => Carbon::now(Publicacion::TIMEZONE)->subDays(30),
        ]);

        // Registrar cambio de ubicación histórica
        DecisionModeracion::create([
            'publicacion_id' => $reporte->id,
            'user_id' => $this->admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_CAMBIO_UBICACION_BIBLIOTECA,
            'motivo' => 'Reubicado de Estante B-12 a Vitrina Principal',
            'fecha_decision' => Carbon::now(Publicacion::TIMEZONE)->subDays(15),
        ]);

        // Registrar devolución desde biblioteca
        $foto = UploadedFile::fake()->image('entrega.jpg', 600, 600);
        $payload = [
            'receptor_nombre' => 'María Fernández Ruiz',
            'receptor_identificador' => 'E19080999',
            'fecha_entrega' => Carbon::now(Publicacion::TIMEZONE)->toDateString(),
            'hora_entrega' => '12:30',
            'explicacion_propiedad' => 'Demostró ser la propietaria con credencial y factura original.',
            'declaracion_sin_contradicciones' => '1',
            'fotografia' => $foto,
        ];

        $response = $this->actingAs($this->admin)->post(
            route('admin.devoluciones-biblioteca.store', $reporte->id),
            $payload
        );

        $response->assertRedirect(route('admin.inventario.index'));

        // Verificar que los datos y antecedentes de recepción siguen intactos
        $recepcionActual = $reporte->fresh()->recepcion;
        $this->assertNotNull($recepcionActual);
        $this->assertEquals($recepcion->id, $recepcionActual->id);
        $this->assertEquals('Estante B-12', $recepcionActual->ubicacion_resguardo);
        $this->assertNotNull($recepcionActual->fecha_limite_anual);

        // Verificar que el timeline contiene todos los eventos sin sobrescrituras
        $eventos = $reporte->fresh()->getEventosHistorial();
        $tipos = $eventos->pluck('tipo')->toArray();

        $this->assertContains('Creación', $tipos);
        $this->assertContains('Recepción en biblioteca', $tipos);
        $this->assertContains('Cambio de ubicación en biblioteca', $tipos);
        $this->assertContains('Devolución desde biblioteca', $tipos);
    }

    /**
     * CA-08: Cada devolución válida se conserva como un evento independiente
     * si existió una devolución anterior (por ejemplo, corregida o previa).
     */
    public function test_02_cada_devolucion_se_conserva_como_evento_independiente(): void
    {
        Storage::fake('local');

        $reporte = Publicacion::factory()->create([
            'user_id' => $this->autor->id,
            'estado' => Publicacion::ESTADO_APROBADA,
            'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO,
            'disponible' => true,
        ]);

        Recepcion::create([
            'publicacion_id' => $reporte->id,
            'user_id' => $this->admin->id,
            'fecha_recepcion' => Carbon::now(Publicacion::TIMEZONE)->subDays(10)->toDateString(),
            'hora_recepcion' => '09:00:00',
            'fecha_captura' => Carbon::now(Publicacion::TIMEZONE)->subDays(10),
            'fecha_limite_anual' => Carbon::now(Publicacion::TIMEZONE)->addDays(355)->toDateString(),
            'condicion_objeto' => 'En orden',
            'ubicacion_resguardo' => 'Caja de seguridad 4',
        ]);

        // Simular devolución anterior que requirió corrección o previa
        $devAnterior = Devolucion::create([
            'publicacion_id' => $reporte->id,
            'user_id' => $this->autor->id,
            'receptor_nombre' => 'Receptor Previo Erróneo',
            'receptor_identificador' => 'E18080001',
            'fecha_entrega' => Carbon::now(Publicacion::TIMEZONE)->subDays(5)->toDateString(),
            'hora_entrega' => '10:00:00',
            'fecha_captura' => Carbon::now(Publicacion::TIMEZONE)->subDays(5),
            'explicacion_propiedad' => 'Intento previo con documentación incompleta',
            'fotografia_ruta' => 'devoluciones/' . $reporte->id . '/foto_previa.jpg',
            'estado' => Devolucion::ESTADO_REQUIERE_CORRECCION,
            'motivo_correccion' => 'Faltó corroborar número de serie',
        ]);

        // Ahora el bibliotecario registra la devolución definitiva desde biblioteca
        $foto = UploadedFile::fake()->image('entrega_final.png', 500, 500);
        $payload = [
            'receptor_nombre' => 'Receptor Legítimo Confirmado',
            'receptor_identificador' => 'E19080555',
            'fecha_entrega' => Carbon::now(Publicacion::TIMEZONE)->toDateString(),
            'hora_entrega' => '14:00',
            'explicacion_propiedad' => 'Verificación presencial exitosa de número de serie y pertenencia.',
            'declaracion_sin_contradicciones' => '1',
            'fotografia' => $foto,
        ];

        $this->actingAs($this->admin)->post(
            route('admin.devoluciones-biblioteca.store', $reporte->id),
            $payload
        );

        // Deben existir ambas devoluciones en la base de datos (eventos independientes)
        $devoluciones = Devolucion::where('publicacion_id', $reporte->id)->get();
        $this->assertCount(2, $devoluciones);

        // La devolución previa no fue sobreescrita
        $devAnteriorActual = Devolucion::find($devAnterior->id);
        $this->assertEquals('Receptor Previo Erróneo', $devAnteriorActual->receptor_nombre);
        $this->assertEquals(Devolucion::ESTADO_REQUIERE_CORRECCION, $devAnteriorActual->estado);

        // La nueva entrega está verificada
        $nuevaDev = $devoluciones->firstWhere('id', '!=', $devAnterior->id);
        $this->assertEquals('Receptor Legítimo Confirmado', $nuevaDev->receptor_nombre);
        $this->assertEquals(Devolucion::ESTADO_VERIFICADA, $nuevaDev->estado);
    }

    /**
     * CA-09: Privacidad de evidencia y datos de la devolución.
     * Los datos y fotografías de la devolución son accesibles ÚNICAMENTE para:
     * - El autor original del reporte
     * - El administrador/bibliotecario
     * Y denegados para:
     * - El receptor del objeto (no adquiere permisos)
     * - Terceros usuarios ajenos
     * - Visitantes no autenticados
     */
    public function test_03_privacidad_de_datos_y_evidencia_de_la_devolucion(): void
    {
        Storage::fake('local');

        $reporte = Publicacion::factory()->create([
            'user_id' => $this->autor->id,
            'estado' => Publicacion::ESTADO_DEVUELTO,
            'estado_devolucion' => 'Devuelto',
            'disponible' => false,
        ]);

        $rutaArchivo = 'devoluciones/' . $reporte->id . '/evidencia_test.jpg';
        Storage::disk('local')->put($rutaArchivo, 'contenido-imagen-privada');

        $devolucion = Devolucion::create([
            'publicacion_id' => $reporte->id,
            'user_id' => $this->admin->id,
            'admin_id' => $this->admin->id,
            'receptor_nombre' => $this->receptor->name,
            'receptor_identificador' => 'E19080123',
            'fecha_entrega' => Carbon::now(Publicacion::TIMEZONE)->toDateString(),
            'hora_entrega' => '11:00:00',
            'fecha_captura' => Carbon::now(Publicacion::TIMEZONE),
            'explicacion_propiedad' => 'Verificación presencial privada.',
            'fotografia_ruta' => $rutaArchivo,
            'fotografia_nombre_original' => 'evidencia_test.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 1024,
            'estado' => Devolucion::ESTADO_VERIFICADA,
        ]);

        // 1. Administrador/Bibliotecario tiene acceso al expediente y a la evidencia
        $this->actingAs($this->admin)->get(route('admin.devoluciones.show', $devolucion->id))->assertOk();
        $this->actingAs($this->admin)->get(route('devoluciones.evidencia', $devolucion->id))->assertOk();

        // 2. Autor original tiene acceso al seguimiento y a la evidencia
        $this->actingAs($this->autor)->get(route('devoluciones.show', $devolucion->id))->assertOk();
        $this->actingAs($this->autor)->get(route('devoluciones.evidencia', $devolucion->id))->assertOk();

        // 3. Receptor NO tiene acceso (CA-09: ser receptor no concede permisos)
        $this->actingAs($this->receptor)->get(route('devoluciones.show', $devolucion->id))->assertForbidden();
        $this->actingAs($this->receptor)->get(route('devoluciones.evidencia', $devolucion->id))->assertForbidden();

        // 4. Tercero ajeno NO tiene acceso
        $this->actingAs($this->tercero)->get(route('devoluciones.show', $devolucion->id))->assertForbidden();
        $this->actingAs($this->tercero)->get(route('devoluciones.evidencia', $devolucion->id))->assertForbidden();

        // 5. Usuario no autenticado es redirigido a login o denegado por middleware
        $respShow = $this->get(route('devoluciones.show', $devolucion->id));
        $this->assertTrue(in_array($respShow->status(), [302, 403], true));
        $respEvidencia = $this->get(route('devoluciones.evidencia', $devolucion->id));
        $this->assertTrue(in_array($respEvidencia->status(), [302, 403], true));
    }

    /**
     * CA-09: Las evidencias no se eliminan automáticamente tras el cierre y consulta.
     */
    public function test_04_las_evidencias_se_conservan_permanentemente(): void
    {
        Storage::fake('local');

        $reporte = Publicacion::factory()->create([
            'user_id' => $this->autor->id,
            'estado' => Publicacion::ESTADO_DEVUELTO,
        ]);

        $rutaArchivo = 'devoluciones/' . $reporte->id . '/evidencia_permanente.png';
        Storage::disk('local')->put($rutaArchivo, 'contenido-imagen-permanente');

        $devolucion = Devolucion::create([
            'publicacion_id' => $reporte->id,
            'user_id' => $this->admin->id,
            'admin_id' => $this->admin->id,
            'receptor_nombre' => 'Alumno Receptor',
            'receptor_identificador' => 'E19080777',
            'fecha_entrega' => Carbon::now(Publicacion::TIMEZONE)->toDateString(),
            'hora_entrega' => '15:00:00',
            'fecha_captura' => Carbon::now(Publicacion::TIMEZONE),
            'explicacion_propiedad' => 'Entrega presencial confirmada.',
            'fotografia_ruta' => $rutaArchivo,
            'estado' => Devolucion::ESTADO_VERIFICADA,
        ]);

        // Simular múltiples consultas autorizadas en el historial y en la evidencia
        $this->actingAs($this->autor)->get(route('reportes.historial', $reporte->id))->assertOk();
        $this->actingAs($this->autor)->get(route('devoluciones.evidencia', $devolucion->id))->assertOk();
        $this->actingAs($this->admin)->get(route('devoluciones.evidencia', $devolucion->id))->assertOk();

        // El archivo debe permanecer intacto en disco sin haber sido depurado
        Storage::disk('local')->assertExists($rutaArchivo);
    }
}
