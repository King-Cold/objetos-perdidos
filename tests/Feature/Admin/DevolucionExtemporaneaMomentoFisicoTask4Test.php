<?php

namespace Tests\Feature\Admin;

use App\Models\Devolucion;
use App\Models\Publicacion;
use App\Models\Recepcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DevolucionExtemporaneaMomentoFisicoTask4Test extends TestCase
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

    private function crearReporteExpirado(): Publicacion
    {
        $fechaHallazgo = Carbon::parse('2026-10-15', Publicacion::TIMEZONE);
        $horaHallazgo = '09:00:00';
        $fechaVencimientoOriginal = Carbon::parse('2026-10-29 14:00:00', Publicacion::TIMEZONE);

        return Publicacion::create([
            'user_id' => $this->autor->id,
            'folio' => 'OP-2026-EXT4',
            'nombre' => 'Laptop HP Gris',
            'titulo' => 'Laptop HP Gris',
            'categoria' => 'Electrónicos',
            'descripcion' => 'Laptop HP gris olvidada en laboratorio.',
            'area_zona' => 'Edificio D',
            'ubicacion_especifica' => 'Laboratorio de Cómputo',
            'fecha_hallazgo' => $fechaHallazgo->format('Y-m-d'),
            'hora_hallazgo' => $horaHallazgo,
            'fecha_primera_aprobacion' => $fechaHallazgo->copy()->addDay(),
            'fecha_aprobacion' => $fechaHallazgo->copy()->addDay(),
            'fecha_vencimiento' => $fechaVencimientoOriginal,
            'estado' => Publicacion::ESTADO_APROBADA,
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'disponible' => false,
            'devolucion_en_proceso' => false,
        ]);
    }

    public function test_rechaza_entrega_exactamente_al_vencimiento_original(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-05 12:00:00', Publicacion::TIMEZONE));
        $reporte = $this->crearReporteExpirado();

        // Vencimiento original: 2026-10-29 14:00:00
        // Se intenta registrar entrega física exacta a las 14:00 de ese día
        $response = $this->actingAs($this->admin)
            ->post(route('admin.devoluciones-extemporaneas.store', $reporte->id), [
                'motivo_registro_tardio' => 'El autor no pudo registrarlo a tiempo.',
                'receptor_nombre' => 'Carlos López Pérez',
                'receptor_identificador' => '19080099',
                'fecha_entrega' => '2026-10-29',
                'hora_entrega' => '14:00',
                'explicacion_propiedad' => 'El receptor presentó su credencial y desbloqueó el equipo frente al autor.',
                'fotografia' => $this->crearArchivoImagen('evidencia.jpg', 50),
            ]);

        $response->assertSessionHasErrors(['hora_entrega']);
        $this->assertEquals(0, Devolucion::where('publicacion_id', $reporte->id)->count());
    }

    public function test_rechaza_entrega_posterior_al_vencimiento_original(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-05 12:00:00', Publicacion::TIMEZONE));
        $reporte = $this->crearReporteExpirado();

        // Caso A: Mismo día del vencimiento pero hora posterior (14:15 > 14:00)
        $responseHoraPosterior = $this->actingAs($this->admin)
            ->post(route('admin.devoluciones-extemporaneas.store', $reporte->id), [
                'motivo_registro_tardio' => 'El autor entregó 15 minutos tarde.',
                'receptor_nombre' => 'Carlos López Pérez',
                'receptor_identificador' => '19080099',
                'fecha_entrega' => '2026-10-29',
                'hora_entrega' => '14:15',
                'explicacion_propiedad' => 'El receptor presentó su credencial y desbloqueó el equipo.',
                'fotografia' => $this->crearArchivoImagen('evidencia.jpg', 50),
            ]);

        $responseHoraPosterior->assertSessionHasErrors(['hora_entrega']);

        // Caso B: Días posteriores al vencimiento (2026-10-30 > 2026-10-29)
        $responseDiaPosterior = $this->actingAs($this->admin)
            ->post(route('admin.devoluciones-extemporaneas.store', $reporte->id), [
                'motivo_registro_tardio' => 'Se entregó al día siguiente de vencer.',
                'receptor_nombre' => 'Carlos López Pérez',
                'receptor_identificador' => '19080099',
                'fecha_entrega' => '2026-10-30',
                'hora_entrega' => '10:00',
                'explicacion_propiedad' => 'El receptor presentó su credencial y desbloqueó el equipo.',
                'fotografia' => $this->crearArchivoImagen('evidencia.jpg', 50),
            ]);

        $responseDiaPosterior->assertSessionHasErrors(['fecha_entrega']);
        $this->assertEquals(0, Devolucion::where('publicacion_id', $reporte->id)->count());
    }

    public function test_rechaza_entrega_anterior_al_hallazgo(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-05 12:00:00', Publicacion::TIMEZONE));
        $reporte = $this->crearReporteExpirado();

        // Hallazgo: 2026-10-15 09:00:00
        // Caso A: Fecha anterior (2026-10-14 < 2026-10-15)
        $responseDiaAnterior = $this->actingAs($this->admin)
            ->post(route('admin.devoluciones-extemporaneas.store', $reporte->id), [
                'motivo_registro_tardio' => 'Fecha equivocada anterior al hallazgo.',
                'receptor_nombre' => 'Carlos López Pérez',
                'receptor_identificador' => '19080099',
                'fecha_entrega' => '2026-10-14',
                'hora_entrega' => '10:00',
                'explicacion_propiedad' => 'El receptor presentó su credencial y comprobó propiedad.',
                'fotografia' => $this->crearArchivoImagen('evidencia.jpg', 50),
            ]);

        $responseDiaAnterior->assertSessionHasErrors(['fecha_entrega']);

        // Caso B: Mismo día pero hora anterior al hallazgo (08:30 < 09:00)
        $responseHoraAnterior = $this->actingAs($this->admin)
            ->post(route('admin.devoluciones-extemporaneas.store', $reporte->id), [
                'motivo_registro_tardio' => 'Hora equivocada anterior a la del hallazgo.',
                'receptor_nombre' => 'Carlos López Pérez',
                'receptor_identificador' => '19080099',
                'fecha_entrega' => '2026-10-15',
                'hora_entrega' => '08:30',
                'explicacion_propiedad' => 'El receptor presentó su credencial y comprobó propiedad.',
                'fotografia' => $this->crearArchivoImagen('evidencia.jpg', 50),
            ]);

        $responseHoraAnterior->assertSessionHasErrors(['hora_entrega']);
        $this->assertEquals(0, Devolucion::where('publicacion_id', $reporte->id)->count());
    }

    public function test_rechaza_entrega_futura(): void
    {
        // Momento actual fijado
        Carbon::setTestNow(Carbon::parse('2026-10-25 10:00:00', Publicacion::TIMEZONE));
        $reporte = $this->crearReporteExpirado();

        // Caso A: Fecha futura respecto a testNow (2026-10-26 > 2026-10-25)
        $responseFechaFutura = $this->actingAs($this->admin)
            ->post(route('admin.devoluciones-extemporaneas.store', $reporte->id), [
                'motivo_registro_tardio' => 'Intento con fecha futura.',
                'receptor_nombre' => 'Carlos López Pérez',
                'receptor_identificador' => '19080099',
                'fecha_entrega' => '2026-10-26',
                'hora_entrega' => '09:00',
                'explicacion_propiedad' => 'El receptor presentó su credencial y comprobó propiedad.',
                'fotografia' => $this->crearArchivoImagen('evidencia.jpg', 50),
            ]);

        $responseFechaFutura->assertSessionHasErrors(['fecha_entrega']);

        // Caso B: Mismo día pero hora futura (11:00 > 10:00)
        $responseHoraFutura = $this->actingAs($this->admin)
            ->post(route('admin.devoluciones-extemporaneas.store', $reporte->id), [
                'motivo_registro_tardio' => 'Intento con hora futura.',
                'receptor_nombre' => 'Carlos López Pérez',
                'receptor_identificador' => '19080099',
                'fecha_entrega' => '2026-10-25',
                'hora_entrega' => '11:00',
                'explicacion_propiedad' => 'El receptor presentó su credencial y comprobó propiedad.',
                'fotografia' => $this->crearArchivoImagen('evidencia.jpg', 50),
            ]);

        $responseHoraFutura->assertSessionHasErrors(['hora_entrega']);
        $this->assertEquals(0, Devolucion::where('publicacion_id', $reporte->id)->count());
    }

    public function test_acepta_entrega_valida_y_guarda_como_pendiente_con_mensaje_exacto_sin_aprobar_automaticamente_ni_crear_recepcion(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-05 15:30:00', Publicacion::TIMEZONE));
        $reporte = $this->crearReporteExpirado();
        $vencimientoOriginalEsperado = $reporte->fecha_vencimiento->format('Y-m-d H:i:s');

        // Entrega física válida: 2026-10-25 a las 11:30 (posterior al hallazgo 10-15 y estrictamente anterior al vencimiento 10-29 14:00)
        $response = $this->actingAs($this->admin)
            ->post(route('admin.devoluciones-extemporaneas.store', $reporte->id), [
                'motivo_registro_tardio' => 'El autor entregó en el aula días antes de vencer pero olvidó capturarlo en el portal.',
                'receptor_nombre' => 'Carlos López Pérez',
                'receptor_identificador' => '19080099',
                'fecha_entrega' => '2026-10-25',
                'hora_entrega' => '11:30',
                'explicacion_propiedad' => 'El receptor presentó su credencial oficial y describió las pegatinas del equipo.',
                'fotografia' => $this->crearArchivoImagen('evidencia.jpg', 60),
            ]);

        // 1. Mensaje de confirmación exacto y redirección
        $response->assertSessionHas('status', 'La devolución reportada fuera de tiempo se registró y está pendiente de verificación');

        $devolucion = Devolucion::where('publicacion_id', $reporte->id)->first();
        $this->assertNotNull($devolucion);
        $response->assertRedirect(route('admin.devoluciones.show', $devolucion->id));

        $reporte->refresh();

        // 2. Estado queda en «Pendiente de verificación» (no aprobada automáticamente)
        $this->assertEquals(Devolucion::ESTADO_PENDIENTE, $devolucion->estado);
        $this->assertEquals(Devolucion::ESTADO_PENDIENTE, $reporte->estado_devolucion);
        $this->assertNotEquals(Publicacion::ESTADO_DEVUELTO, $reporte->estado);
        $this->assertNotEquals(Devolucion::ESTADO_VERIFICADA, $devolucion->estado);

        // 3. Mantenido fuera del muro público
        $this->assertFalse((bool) $reporte->disponible);
        $this->assertTrue((bool) $reporte->devolucion_en_proceso);

        // 4. Fechas verdaderas separadas y vencimiento original inalterado (sin simular cumplimiento)
        $this->assertEquals('2026-10-25', substr((string)$devolucion->fecha_entrega, 0, 10));
        $this->assertEquals('11:30', substr((string)$devolucion->hora_entrega, 0, 5));
        $this->assertEquals('2026-11-05 15:30:00', $devolucion->fecha_captura->format('Y-m-d H:i:s'));
        $this->assertEquals($vencimientoOriginalEsperado, $reporte->fecha_vencimiento->format('Y-m-d H:i:s'));

        // 5. Sin recepción ficticia ni incorporación al inventario
        $this->assertEquals(Publicacion::RESGUARDO_AUTOR, $reporte->situacion_resguardo);
        $this->assertEquals(0, Recepcion::where('publicacion_id', $reporte->id)->count());
    }
}

