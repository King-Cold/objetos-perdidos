<?php

namespace Tests\Feature\Admin;

use App\Models\Devolucion;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DevolucionExtemporaneaCapturaDatosTask2Test extends TestCase
{
    use RefreshDatabase;

    private function crearFotoFalsa(string $nombre = 'evidencia_entrega.jpg', int $kilobytes = 150): UploadedFile
    {
        $jpegMinimo = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
        $contenido = str_pad($jpegMinimo, max(strlen($jpegMinimo), $kilobytes * 1024), "\0");

        return UploadedFile::fake()->createWithContent($nombre, $contenido);
    }

    private function crearReporteVencido(User $autor): Publicacion
    {
        $fechaAprobacion = Carbon::parse('2026-10-20 10:00:00', Publicacion::TIMEZONE);
        $fechaVencimiento = Carbon::parse('2026-11-03 10:00:00', Publicacion::TIMEZONE);

        return Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OP-2026-EXT02',
            'nombre' => 'Audífonos Sony Negros',
            'titulo' => 'Audífonos Sony Negros',
            'categoria' => 'Electrónicos',
            'descripcion' => 'Audífonos inalámbricos en estuche rígido.',
            'area_zona' => 'Biblioteca',
            'ubicacion_especifica' => 'Sala de lectura',
            'fecha_hallazgo' => '2026-10-19',
            'hora_hallazgo' => '09:30',
            'estado' => Publicacion::ESTADO_APROBADA,
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_primera_aprobacion' => $fechaAprobacion,
            'fecha_aprobacion' => $fechaAprobacion,
            'fecha_vencimiento' => $fechaVencimiento,
        ]);
    }

    public function test_formulario_muestra_folio_objeto_autor_vencimiento_original_y_campos_de_devolucion(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 12, 0, 0, Publicacion::TIMEZONE));

        $autor = User::factory()->create([
            'name' => 'Ana María Autora',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);
        $bibliotecario = User::factory()->admin()->create([
            'name' => 'Roberto Bibliotecario',
        ]);

        $reporte = $this->crearReporteVencido($autor);

        $response = $this->actingAs($bibliotecario)
            ->get(route('admin.devoluciones-extemporaneas.create', $reporte->id));

        $response->assertOk();
        $response->assertSee($reporte->folio);
        $response->assertSee('Audífonos Sony Negros');
        $response->assertSee('Ana María Autora');
        $response->assertSee('03/11/2026 • 10:00');
        $response->assertSee('motivo_registro_tardio', false);
        $response->assertSee('receptor_nombre', false);
        $response->assertSee('receptor_identificador', false);
        $response->assertSee('fecha_entrega', false);
        $response->assertSee('hora_entrega', false);
        $response->assertSee('explicacion_propiedad', false);
        $response->assertSee('fotografia', false);

        Carbon::setTestNow();
    }

    public function test_exige_motivo_del_registro_tardio_y_datos_de_devolucion_y_fotografia_hasta_2mb(): void
    {
        Storage::fake('local');
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 12, 0, 0, Publicacion::TIMEZONE));

        $autor = User::factory()->create(['role' => User::ROLE_USUARIO_GENERAL]);
        $bibliotecario = User::factory()->admin()->create();
        $reporte = $this->crearReporteVencido($autor);

        // 1. Motivo compuesto únicamente por espacios y campos faltantes
        $respInvalida = $this->actingAs($bibliotecario)
            ->from(route('admin.devoluciones-extemporaneas.create', $reporte->id))
            ->post(route('admin.devoluciones-extemporaneas.store', $reporte->id), [
                'motivo_registro_tardio' => '     ',
                'receptor_nombre' => '   ',
                'receptor_identificador' => '@@invalido@@',
                'fecha_entrega' => '',
                'hora_entrega' => '',
                'explicacion_propiedad' => '   ',
            ]);

        $respInvalida->assertRedirect(route('admin.devoluciones-extemporaneas.create', $reporte->id));
        $respInvalida->assertSessionHasErrors([
            'motivo_registro_tardio',
            'receptor_nombre',
            'receptor_identificador',
            'fecha_entrega',
            'hora_entrega',
            'explicacion_propiedad',
            'fotografia',
        ]);
        $this->assertDatabaseCount('devoluciones', 0);

        // 2. Fotografía mayor a 2 MB es rechazada
        $fotoPesada = $this->crearFotoFalsa('grande.jpg', 2200);
        $respFotoGrande = $this->actingAs($bibliotecario)
            ->from(route('admin.devoluciones-extemporaneas.create', $reporte->id))
            ->post(route('admin.devoluciones-extemporaneas.store', $reporte->id), [
                'motivo_registro_tardio' => 'El autor olvidó subir la devolución pero entregó a tiempo.',
                'receptor_nombre' => 'Luis Hernández',
                'receptor_identificador' => '21080456',
                'fecha_entrega' => '2026-10-25',
                'hora_entrega' => '14:30',
                'explicacion_propiedad' => 'Mostró factura y número de serie del estuche.',
                'fotografia' => $fotoPesada,
            ]);

        $respFotoGrande->assertSessionHasErrors(['fotografia']);
        $this->assertDatabaseCount('devoluciones', 0);

        Carbon::setTestNow();
    }

    public function test_valida_entrega_estrictamente_anterior_al_vencimiento_y_posterior_al_hallazgo(): void
    {
        Storage::fake('local');
        Carbon::setTestNow(Carbon::create(2026, 11, 5, 12, 0, 0, Publicacion::TIMEZONE));

        $autor = User::factory()->create(['role' => User::ROLE_USUARIO_GENERAL]);
        $bibliotecario = User::factory()->admin()->create();
        $reporte = $this->crearReporteVencido($autor); // Hallazgo: 2026-10-19 09:30 | Vencimiento: 2026-11-03 10:00

        // Caso 1: Exactamente al vencer el plazo (2026-11-03 10:00) -> rechazado
        $respExactoVencimiento = $this->actingAs($bibliotecario)
            ->post(route('admin.devoluciones-extemporaneas.store', $reporte->id), [
                'motivo_registro_tardio' => 'Reportado tardíamente en mostrador.',
                'receptor_nombre' => 'Pedro Sánchez',
                'receptor_identificador' => 'DOC-4051',
                'fecha_entrega' => '2026-11-03',
                'hora_entrega' => '10:00',
                'explicacion_propiedad' => 'Comprobó propiedad con credencial.',
                'fotografia' => $this->crearFotoFalsa(),
            ]);
        $respExactoVencimiento->assertSessionHasErrors(['hora_entrega']);

        // Caso 2: Después del vencimiento (2026-11-04 11:00) -> rechazado
        $respPosteriorVencimiento = $this->actingAs($bibliotecario)
            ->post(route('admin.devoluciones-extemporaneas.store', $reporte->id), [
                'motivo_registro_tardio' => 'Reportado tardíamente en mostrador.',
                'receptor_nombre' => 'Pedro Sánchez',
                'receptor_identificador' => 'DOC-4051',
                'fecha_entrega' => '2026-11-04',
                'hora_entrega' => '11:00',
                'explicacion_propiedad' => 'Comprobó propiedad con credencial.',
                'fotografia' => $this->crearFotoFalsa(),
            ]);
        $respPosteriorVencimiento->assertSessionHasErrors(['fecha_entrega', 'hora_entrega']);

        // Caso 3: Mismo día del hallazgo pero hora anterior (2026-10-19 08:00 < 09:30) -> rechazado
        $respAntesHoraHallazgo = $this->actingAs($bibliotecario)
            ->post(route('admin.devoluciones-extemporaneas.store', $reporte->id), [
                'motivo_registro_tardio' => 'Reportado tardíamente.',
                'receptor_nombre' => 'Pedro Sánchez',
                'receptor_identificador' => 'DOC-4051',
                'fecha_entrega' => '2026-10-19',
                'hora_entrega' => '08:00',
                'explicacion_propiedad' => 'Comprobó propiedad.',
                'fotografia' => $this->crearFotoFalsa(),
            ]);
        $respAntesHoraHallazgo->assertSessionHasErrors(['hora_entrega']);

        $this->assertDatabaseCount('devoluciones', 0);

        Carbon::setTestNow();
    }

    public function test_guarda_por_separado_entrega_real_captura_actual_autor_y_bibliotecario_responsable(): void
    {
        Storage::fake('local');
        $momentoCapturaActual = Carbon::create(2026, 11, 6, 16, 45, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($momentoCapturaActual);

        $autor = User::factory()->create([
            'name' => 'Carlos Autor Hallazgo',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);
        $bibliotecario = User::factory()->admin()->create([
            'name' => 'Elena Bibliotecaria',
        ]);

        $reporte = $this->crearReporteVencido($autor);
        $vencimientoOriginalAntes = $reporte->fecha_vencimiento->toDateTimeString();

        $foto = $this->crearFotoFalsa('entrega_previa.jpg', 180);

        $response = $this->actingAs($bibliotecario)
            ->post(route('admin.devoluciones-extemporaneas.store', $reporte->id), [
                'motivo_registro_tardio' => 'El autor entregó los audífonos antes de vencer el reporte, pero no tenía conexión para subir la evidencia hasta acudir a biblioteca.',
                'receptor_nombre' => 'Mariana Gómez Pérez',
                'receptor_identificador' => 'DOC-8821',
                'fecha_entrega' => '2026-10-28',
                'hora_entrega' => '13:15',
                'explicacion_propiedad' => 'La docente mostró el empaque con el mismo número de serie y su teléfono se vinculó automáticamente por Bluetooth.',
                'fotografia' => $foto,
            ]);

        $response->assertSessionHasNoErrors();
        $devolucion = Devolucion::first();
        $this->assertNotNull($devolucion);

        $response->assertRedirect(route('admin.devoluciones.show', $devolucion->id));
        $response->assertSessionHas('status', 'La devolución reportada fuera de tiempo se registró y está pendiente de verificación');

        // Verificar separación de fechas y responsables
        $this->assertTrue($devolucion->es_registro_extemporaneo);
        $this->assertSame($autor->id, $devolucion->user_id); // Autor que entregó
        $this->assertSame($bibliotecario->id, $devolucion->registrado_por_id); // Bibliotecario que documentó
        $this->assertNull($devolucion->admin_id); // Aún no resuelta/aprobada
        $this->assertSame('2026-10-28', $devolucion->fecha_entrega->format('Y-m-d'));
        $this->assertSame('13:15', substr((string) $devolucion->hora_entrega, 0, 5));
        $this->assertSame('2026-11-06 16:45:00', $devolucion->fecha_captura->format('Y-m-d H:i:s'));
        $this->assertSame(Devolucion::ESTADO_PENDIENTE, $devolucion->estado);
        $this->assertStringContainsString('no tenía conexión', $devolucion->motivo_registro_tardio);
        Storage::disk('local')->assertExists($devolucion->fotografia_ruta);

        // Verificar que la publicación no alteró su vencimiento original ni se aprobó automáticamente ni creó recepción en biblioteca
        $reporte->refresh();
        $this->assertSame($vencimientoOriginalAntes, $reporte->fecha_vencimiento->toDateTimeString());
        $this->assertSame(Publicacion::ESTADO_APROBADA, $reporte->estado);
        $this->assertSame(Publicacion::RESGUARDO_AUTOR, $reporte->situacion_resguardo);
        $this->assertTrue((bool) $reporte->devolucion_en_proceso);
        $this->assertSame(Devolucion::ESTADO_PENDIENTE, $reporte->estado_devolucion);
        $this->assertDatabaseCount('recepciones', 0);

        Carbon::setTestNow();
    }
}
