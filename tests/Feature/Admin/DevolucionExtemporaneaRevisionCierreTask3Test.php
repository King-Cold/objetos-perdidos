<?php

namespace Tests\Feature\Admin;

use App\Models\DecisionModeracion;
use App\Models\Devolucion;
use App\Models\Publicacion;
use App\Models\Recepcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DevolucionExtemporaneaRevisionCierreTask3Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $autor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->admin = User::factory()->admin()->create([
            'email' => 'bibliotecario@merida.tecnm.mx',
        ]);

        $this->autor = User::factory()->create([
            'email' => 'autor.estudiante@merida.tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $this->asegurarColumnas();
    }

    private function asegurarColumnas(): void
    {
        if (! Schema::hasColumn('devoluciones', 'es_registro_extemporaneo')) {
            Schema::table('devoluciones', function ($table) {
                $table->boolean('es_registro_extemporaneo')->default(false)->after('admin_id');
                $table->foreignId('registrado_por_id')->nullable()->after('es_registro_extemporaneo')->constrained('users')->nullOnDelete();
                $table->text('motivo_registro_tardio')->nullable()->after('explicacion_propiedad');
            });
        }
    }

    private function crearArchivoImagen(string $nombre = 'evidencia.jpg', int $kilobytes = 100): UploadedFile
    {
        $jpegMinimo = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
        $contenido = str_pad($jpegMinimo, max(strlen($jpegMinimo), $kilobytes * 1024), "\0");

        return UploadedFile::fake()->createWithContent($nombre, $contenido);
    }

    private function crearDevolucionExtemporanea(string $estado = Devolucion::ESTADO_PENDIENTE, ?string $motivoCorreccion = null): array
    {
        $fechaHallazgo = Carbon::now(Publicacion::TIMEZONE)->subDays(20);
        $fechaVencimientoOriginal = Carbon::now(Publicacion::TIMEZONE)->subDays(6);

        $reporte = Publicacion::factory()->create([
            'user_id' => $this->autor->id,
            'nombre' => 'Calculadora Científica Casio',
            'estado' => Publicacion::ESTADO_APROBADA,
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'disponible' => false,
            'devolucion_en_proceso' => true,
            'estado_devolucion' => $estado,
            'fecha_hallazgo' => $fechaHallazgo->format('Y-m-d'),
            'hora_hallazgo' => '10:00:00',
            'fecha_primera_aprobacion' => $fechaHallazgo->copy()->addDay(),
            'fecha_vencimiento' => $fechaVencimientoOriginal,
        ]);

        $archivoFoto = $this->crearArchivoImagen('evidencia_inicial.jpg', 50);
        $rutaFoto = $archivoFoto->storeAs('devoluciones/' . $reporte->id, 'evidencia_inicial.jpg', 'local');

        $fechaEntrega = $fechaVencimientoOriginal->copy()->subDays(2)->format('Y-m-d');
        $horaEntrega = '11:30';

        $devolucion = Devolucion::create([
            'publicacion_id' => $reporte->id,
            'user_id' => $this->autor->id,
            'es_registro_extemporaneo' => true,
            'registrado_por_id' => $this->admin->id,
            'receptor_nombre' => 'María Fernández Gómez',
            'receptor_identificador' => '19080012',
            'fecha_entrega' => $fechaEntrega,
            'hora_entrega' => $horaEntrega,
            'fecha_captura' => Carbon::now(Publicacion::TIMEZONE),
            'fecha_envio_revision' => Carbon::now(Publicacion::TIMEZONE),
            'explicacion_propiedad' => 'La alumna mostró la contraseña del equipo y la etiqueta grabada con sus iniciales.',
            'motivo_registro_tardio' => 'El autor no tuvo conexión el día de la entrega física previa al vencimiento.',
            'fotografia_ruta' => $rutaFoto,
            'fotografia_nombre_original' => 'evidencia_inicial.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 10240,
            'estado' => $estado,
            'motivo_correccion' => $motivoCorreccion,
        ]);

        return [$reporte, $devolucion, $rutaFoto];
    }

    public function test_aprobacion_marca_devuelto_y_cierra_pendiente_con_motivo_especifico_sin_recepcion_ficticia(): void
    {
        [$reporte, $devolucion] = $this->crearDevolucionExtemporanea(Devolucion::ESTADO_PENDIENTE);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.devoluciones.aprobar', $devolucion->id), [
                'confirmacion_revision' => '1',
            ]);

        $response->assertRedirect(route('admin.devoluciones.show', $devolucion->id));
        $response->assertSessionHas('status');

        $reporte->refresh();
        $devolucion->refresh();

        // 1. Marcar como «Devuelto» y «Verificada»
        $this->assertEquals(Publicacion::ESTADO_DEVUELTO, $reporte->estado);
        $this->assertEquals(Devolucion::ESTADO_VERIFICADA, $reporte->estado_devolucion);
        $this->assertEquals(Devolucion::ESTADO_VERIFICADA, $devolucion->estado);
        $this->assertFalse((bool) $reporte->disponible);
        $this->assertFalse((bool) $reporte->devolucion_en_proceso);

        // 2. Cierre del seguimiento de entrega a biblioteca
        $this->assertFalse($reporte->correspondeEntregaBiblioteca());

        // 3. Registrar decisión con motivo exacto «Devolución previa registrada tardíamente»
        $decision = DecisionModeracion::where('publicacion_id', $reporte->id)
            ->where('tipo_decision', DecisionModeracion::TIPO_DEVOLUCION_APROBADA)
            ->first();

        $this->assertNotNull($decision);
        $this->assertEquals('Devolución previa registrada tardíamente', $decision->motivo);
        $this->assertEquals($this->admin->id, $decision->user_id);

        // 4. Sin recepción ficticia ni inventario: situacion_resguardo no cambia a biblioteca ni se crea Recepcion
        $this->assertEquals(Publicacion::RESGUARDO_AUTOR, $reporte->situacion_resguardo);
        $this->assertDatabaseMissing('recepciones', ['publicacion_id' => $reporte->id]);
        $this->assertEquals(0, Recepcion::where('publicacion_id', $reporte->id)->count());
    }

    public function test_bibliotecario_puede_solicitar_correccion_con_motivo_explicativo(): void
    {
        [$reporte, $devolucion] = $this->crearDevolucionExtemporanea(Devolucion::ESTADO_PENDIENTE);

        // Intento con motivo vacío debe fallar
        $responseVacio = $this->actingAs($this->admin)
            ->post(route('admin.devoluciones.solicitar-correccion', $devolucion->id), [
                'motivo' => '   ',
            ]);
        $responseVacio->assertSessionHasErrors(['motivo']);

        // Solicitud válida de corrección
        $motivoExplicativo = 'La fotografía adjunta no es legible y se requiere aclarar el número de matrícula del receptor.';
        $responseValido = $this->actingAs($this->admin)
            ->post(route('admin.devoluciones.solicitar-correccion', $devolucion->id), [
                'motivo' => $motivoExplicativo,
            ]);

        $responseValido->assertRedirect(route('admin.devoluciones.show', $devolucion->id));
        $responseValido->assertSessionHas('status');

        $reporte->refresh();
        $devolucion->refresh();

        $this->assertEquals(Devolucion::ESTADO_REQUIERE_CORRECCION, $devolucion->estado);
        $this->assertEquals(Devolucion::ESTADO_REQUIERE_CORRECCION, $reporte->estado_devolucion);
        $this->assertEquals($motivoExplicativo, $devolucion->motivo_correccion);
        $this->assertFalse((bool) $reporte->disponible);
        $this->assertTrue((bool) $reporte->devolucion_en_proceso);

        $decision = DecisionModeracion::where('publicacion_id', $reporte->id)
            ->where('tipo_decision', DecisionModeracion::TIPO_DEVOLUCION_CORRECCION)
            ->first();

        $this->assertNotNull($decision);
        $this->assertEquals($motivoExplicativo, $decision->motivo);
    }

    public function test_bibliotecario_puede_corregir_y_reenviar_la_misma_devolucion_sustituyendo_foto(): void
    {
        $motivoInicial = 'La fotografía no es suficientemente clara.';
        [$reporte, $devolucion, $rutaFotoInicial] = $this->crearDevolucionExtemporanea(
            Devolucion::ESTADO_REQUIERE_CORRECCION,
            $motivoInicial
        );

        $this->assertTrue(Storage::disk('local')->exists($rutaFotoInicial));

        // Vista de edición accesible para el bibliotecario
        $responseVista = $this->actingAs($this->admin)
            ->get(route('admin.devoluciones-extemporaneas.edit', $devolucion->id));

        $responseVista->assertOk();
        $responseVista->assertSee('Corregir devolución reportada fuera de tiempo');
        $responseVista->assertSee($motivoInicial);
        $responseVista->assertSee($devolucion->receptor_nombre);
        $responseVista->assertSee($devolucion->motivo_registro_tardio);

        // Envío de corrección con nueva fotografía
        $nuevaFoto = $this->crearArchivoImagen('nueva_evidencia_clara.png', 60);

        $responseUpdate = $this->actingAs($this->admin)
            ->put(route('admin.devoluciones-extemporaneas.update', $devolucion->id), [
                'motivo_registro_tardio' => 'El autor no pudo acudir ese día pero presentó la evidencia física original con fecha anterior.',
                'receptor_nombre' => 'María del Carmen Fernández Gómez',
                'receptor_identificador' => '19080012',
                'fecha_entrega' => Carbon::parse($devolucion->fecha_entrega)->format('Y-m-d'),
                'hora_entrega' => '11:45',
                'explicacion_propiedad' => 'La receptora demostró la clave de acceso al dispositivo y la identificación oficial vigente.',
                'fotografia' => $nuevaFoto,
            ]);

        $responseUpdate->assertRedirect(route('admin.devoluciones.show', $devolucion->id));
        $responseUpdate->assertSessionHas('status');

        $reporte->refresh();
        $devolucion->refresh();

        // 1. Conserva el mismo identificador de devolución (no duplicado)
        $this->assertEquals(1, Devolucion::where('publicacion_id', $reporte->id)->count());

        // 2. Estado regresa a Pendiente de verificación y se limpia motivo_correccion
        $this->assertEquals(Devolucion::ESTADO_PENDIENTE, $devolucion->estado);
        $this->assertEquals(Devolucion::ESTADO_PENDIENTE, $reporte->estado_devolucion);
        $this->assertNull($devolucion->motivo_correccion);
        $this->assertEquals('María del Carmen Fernández Gómez', $devolucion->receptor_nombre);
        $this->assertFalse((bool) $reporte->disponible);
        $this->assertTrue((bool) $reporte->devolucion_en_proceso);

        // 3. Se elimina la fotografía anterior y se conserva únicamente la nueva versión
        $this->assertFalse(Storage::disk('local')->exists($rutaFotoInicial));
        $this->assertTrue(Storage::disk('local')->exists($devolucion->fotografia_ruta));
        $this->assertEquals('nueva_evidencia_clara.png', $devolucion->fotografia_nombre_original);
    }

    public function test_bibliotecario_puede_corregir_sin_subir_nueva_foto_conservando_la_actual(): void
    {
        $motivoInicial = 'Falta precisar el apellido paterno del receptor.';
        [$reporte, $devolucion, $rutaFotoInicial] = $this->crearDevolucionExtemporanea(
            Devolucion::ESTADO_REQUIERE_CORRECCION,
            $motivoInicial
        );

        $responseUpdate = $this->actingAs($this->admin)
            ->put(route('admin.devoluciones-extemporaneas.update', $devolucion->id), [
                'motivo_registro_tardio' => $devolucion->motivo_registro_tardio,
                'receptor_nombre' => 'María Elena Fernández Gómez',
                'receptor_identificador' => $devolucion->receptor_identificador,
                'fecha_entrega' => Carbon::parse($devolucion->fecha_entrega)->format('Y-m-d'),
                'hora_entrega' => '11:30',
                'explicacion_propiedad' => $devolucion->explicacion_propiedad,
                // Sin adjuntar 'fotografia'
            ]);

        $responseUpdate->assertRedirect(route('admin.devoluciones.show', $devolucion->id));

        $devolucion->refresh();

        // La fotografía existente se mantiene intacta
        $this->assertTrue(Storage::disk('local')->exists($rutaFotoInicial));
        $this->assertEquals($rutaFotoInicial, $devolucion->fotografia_ruta);
        $this->assertEquals(Devolucion::ESTADO_PENDIENTE, $devolucion->estado);
        $this->assertEquals('María Elena Fernández Gómez', $devolucion->receptor_nombre);
    }

    public function test_enlace_para_canalizar_incidencias_disponible_en_vista_de_devolucion(): void
    {
        [$reporte, $devolucion] = $this->crearDevolucionExtemporanea(Devolucion::ESTADO_PENDIENTE);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.devoluciones.show', $devolucion->id));

        $response->assertOk();
        $response->assertSee(route('admin.incidencias.create', ['publicacionId' => $reporte->id, 'devolucion_id' => $devolucion->id]));
        $response->assertSee('Abrir incidencia');
    }
}
