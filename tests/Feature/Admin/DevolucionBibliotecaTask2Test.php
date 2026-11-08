<?php

namespace Tests\Feature\Admin;

use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\Recepcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DevolucionBibliotecaTask2Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Crea un escenario con un reporte bajo resguardo físico en biblioteca.
     */
    protected function crearEscenarioResguardo(array $overridesRecepcion = [], array $overridesReporte = []): array
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
            'nombre' => 'Calculadora Texas Instruments TI-84',
            'descripcion' => 'Calculadora gráfica color negro con tapa protectora.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'area_zona' => 'Edificio D',
            'lugar_hallazgo' => 'Laboratorio D-4',
            'situacion_resguardo' => 'En biblioteca',
            'fecha_hallazgo' => Carbon::create(2026, 10, 30, 9, 30, 0, Publicacion::TIMEZONE)->toDateString(),
            'hora_hallazgo' => '09:30:00',
            'fecha_captura' => Carbon::create(2026, 10, 30, 10, 0, 0, Publicacion::TIMEZONE),
            'fecha_primera_aprobacion' => $primeraAprobacion,
            'fecha_aprobacion' => $primeraAprobacion,
            'fecha_vencimiento' => $vencimiento,
            'devolucion_en_proceso' => false,
            'estado_devolucion' => null,
            'tiene_incidencia' => false,
            'incidencia_abierta' => false,
        ], $overridesReporte);

        $reporte = Publicacion::create($reporteData);

        Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'publicaciones/foto_ti84.jpg',
            'nombre_original' => 'foto_ti84.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 120000,
            'es_principal' => true,
        ]);

        $recepcionData = array_merge([
            'publicacion_id' => $reporte->id,
            'user_id' => $admin->id,
            'actualizado_por_id' => $admin->id,
            'fecha_recepcion' => Carbon::create(2026, 11, 16, 9, 0, 0, Recepcion::TIMEZONE)->toDateString(),
            'hora_recepcion' => '09:00:00',
            'fecha_captura' => Carbon::create(2026, 11, 16, 9, 15, 0, Recepcion::TIMEZONE),
            'condicion_objeto' => 'En buen estado, sin rayaduras profundas',
            'ubicacion_resguardo' => 'Gabinete B - Estante 1',
            'observaciones' => 'Sin accesorios adicionales',
            'confirmacion_cotejo' => true,
            'fecha_limite_anual' => Carbon::create(2027, 11, 16, 9, 0, 0, Recepcion::TIMEZONE)->toDateString(),
        ], $overridesRecepcion);

        $recepcion = Recepcion::create($recepcionData);

        return [$admin, $autor, $reporte, $recepcion];
    }

    protected function payloadValido(): array
    {
        return [
            'receptor_nombre' => 'José Eduardo Pech Pech',
            'receptor_identificador' => '19080554',
            'fecha_entrega' => '2026-11-20',
            'hora_entrega' => '10:00',
            'explicacion_propiedad' => 'El solicitante describió las calcomanías detrás de la tapa y las variables guardadas en la memoria de la calculadora.',
            'declaracion_sin_contradicciones' => '1',
            'fotografia' => UploadedFile::fake()->create('evidencia_entrega.jpg', 500, 'image/jpeg'),
        ];
    }

    /**
     * CA-03: Rechaza envío si no se confirma la ausencia de contradicciones pendientes.
     */
    public function test_01_rechaza_si_no_confirma_ausencia_de_contradicciones(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        $payload = $this->payloadValido();
        unset($payload['declaracion_sin_contradicciones']);

        $response = $this->actingAs($admin)->post(route('admin.devoluciones-biblioteca.store', $reporte->id), $payload);

        $response->assertSessionHasErrors(['declaracion_sin_contradicciones']);
    }

    /**
     * CA-03: Rechaza y orienta a procedimiento de incidencias si existen contradicciones / inconsistencias.
     */
    public function test_02_rechaza_si_se_marcan_inconsistencias_pendientes(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        $payload = $this->payloadValido();
        $payload['existen_inconsistencias'] = '1';

        $response = $this->actingAs($admin)->post(route('admin.devoluciones-biblioteca.store', $reporte->id), $payload);

        $response->assertSessionHasErrors(['existen_inconsistencias']);
    }

    /**
     * CA-04: Rechaza campos vacíos o con solo espacios.
     */
    public function test_03_rechaza_campos_obligatorios_vacios_o_espacios(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        $payload = [
            'receptor_nombre' => '   ',
            'receptor_identificador' => '   ',
            'fecha_entrega' => '',
            'hora_entrega' => '',
            'explicacion_propiedad' => '    ',
            'declaracion_sin_contradicciones' => '1',
        ];

        $response = $this->actingAs($admin)->post(route('admin.devoluciones-biblioteca.store', $reporte->id), $payload);

        $response->assertSessionHasErrors([
            'receptor_nombre',
            'receptor_identificador',
            'fecha_entrega',
            'hora_entrega',
            'explicacion_propiedad',
            'fotografia',
        ]);
    }

    /**
     * CA-04: Valida identificadores institucionales inválidos (formato matrícula o clave docente).
     */
    public function test_04_valida_identificador_institucional(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        $payloadInvalido = $this->payloadValido();
        $payloadInvalido['receptor_identificador'] = '$$##//??';

        $resInvalido = $this->actingAs($admin)->post(route('admin.devoluciones-biblioteca.store', $reporte->id), $payloadInvalido);
        $resInvalido->assertSessionHasErrors(['receptor_identificador']);

        // Matrícula válida
        $payloadValido = $this->payloadValido();
        $payloadValido['receptor_identificador'] = '20080123';
        $resValido = $this->actingAs($admin)->post(route('admin.devoluciones-biblioteca.store', $reporte->id), $payloadValido);
        $resValido->assertSessionDoesntHaveErrors(['receptor_identificador']);
    }

    /**
     * CA-04: Rechaza fotografías que no cumplan formato JPG o PNG o que excedan 2 MB.
     */
    public function test_05_valida_formato_y_tamano_de_fotografia(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        // 1. Archivo PDF no admitido
        $payloadPdf = $this->payloadValido();
        $payloadPdf['fotografia'] = UploadedFile::fake()->create('documento.pdf', 300, 'application/pdf');

        $resPdf = $this->actingAs($admin)->post(route('admin.devoluciones-biblioteca.store', $reporte->id), $payloadPdf);
        $resPdf->assertSessionHasErrors(['fotografia']);

        // 2. Archivo mayor a 2 MB (ej. 2500 KB)
        $payloadPesado = $this->payloadValido();
        $payloadPesado['fotografia'] = UploadedFile::fake()->create('foto_gigante.png', 2500, 'image/png');

        $resPesado = $this->actingAs($admin)->post(route('admin.devoluciones-biblioteca.store', $reporte->id), $payloadPesado);
        $resPesado->assertSessionHasErrors(['fotografia']);
    }

    /**
     * CA-05: Coherencia de fechas - Rechaza fechas futuras.
     */
    public function test_06_rechaza_fechas_futuras(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        $payloadFutura = $this->payloadValido();
        $payloadFutura['fecha_entrega'] = '2026-11-25'; // TestNow es 2026-11-20

        $response = $this->actingAs($admin)->post(route('admin.devoluciones-biblioteca.store', $reporte->id), $payloadFutura);

        $response->assertSessionHasErrors(['fecha_entrega']);
    }

    /**
     * CA-05: Coherencia de fechas - Rechaza fechas anteriores a la recepción física del objeto.
     */
    public function test_07_rechaza_fechas_anteriores_a_la_recepcion_fisica(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo([
            'fecha_recepcion' => '2026-11-16',
        ]);

        $payloadAnterior = $this->payloadValido();
        $payloadAnterior['fecha_entrega'] = '2026-11-10'; // Antes del 16 de noviembre

        $response = $this->actingAs($admin)->post(route('admin.devoluciones-biblioteca.store', $reporte->id), $payloadAnterior);

        $response->assertSessionHasErrors(['fecha_entrega']);
    }

    /**
     * CA-05: Coherencia de fechas - Rechaza hora futura para la fecha actual o anterior a la hora de recepción física.
     */
    public function test_08_rechaza_horas_incoherentes(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo([
            'fecha_recepcion' => '2026-11-20',
            'hora_recepcion' => '10:00:00',
        ]);

        // 1. Hora futura en el día actual (TestNow es 11:00)
        $payloadHoraFutura = $this->payloadValido();
        $payloadHoraFutura['fecha_entrega'] = '2026-11-20';
        $payloadHoraFutura['hora_entrega'] = '14:30';

        $res1 = $this->actingAs($admin)->post(route('admin.devoluciones-biblioteca.store', $reporte->id), $payloadHoraFutura);
        $res1->assertSessionHasErrors(['hora_entrega']);

        // 2. Misma fecha de recepción pero hora anterior a la recepción física (09:00 vs 10:00)
        $payloadHoraAnterior = $this->payloadValido();
        $payloadHoraAnterior['fecha_entrega'] = '2026-11-20';
        $payloadHoraAnterior['hora_entrega'] = '09:00';

        $res2 = $this->actingAs($admin)->post(route('admin.devoluciones-biblioteca.store', $reporte->id), $payloadHoraAnterior);
        $res2->assertSessionHasErrors(['hora_entrega']);
    }

    /**
     * Validación exitosa con todos los campos válidos y coherentes.
     */
    public function test_09_validacion_exitosa_con_datos_correctos(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        $payload = $this->payloadValido();

        $response = $this->actingAs($admin)->post(route('admin.devoluciones-biblioteca.store', $reporte->id), $payload);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('admin.inventario.index'));
        $response->assertSessionHas('status', 'La devolución desde biblioteca se registró correctamente.');
    }
}

