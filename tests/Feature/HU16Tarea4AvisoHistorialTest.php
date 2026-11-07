<?php

namespace Tests\Feature;

use App\Events\PublicacionVencidaPendienteEntrega;
use App\Models\DecisionModeracion;
use App\Models\Devolucion;
use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HU16Tarea4AvisoHistorialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Criterio 1:
     * Como autor, abre el reporte vencido y se genera y visualiza el aviso de vencimiento
     * con el folio y el plazo de dos días de atención de biblioteca posteriores al vencimiento.
     */
    public function test_autor_recibe_aviso_de_vencimiento_con_folio_y_plazo(): void
    {
        Event::fake([PublicacionVencidaPendienteEntrega::class]);

        $ahoraMerida = Carbon::create(2026, 11, 20, 10, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($ahoraMerida);

        $autor = User::factory()->create([
            'email' => 'estudiante.aviso@itmerida.mx',
        ]);

        $primeraAprobacion = Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE);
        $vencimiento = Carbon::create(2026, 11, 15, 10, 0, 0, Publicacion::TIMEZONE);

        $reporte = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Calculadora Científica Casio Fx-991',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Edificio B',
            'lugar_hallazgo' => 'Salón B12',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_captura' => Carbon::create(2026, 11, 1, 9, 0, 0, Publicacion::TIMEZONE),
            'fecha_primera_aprobacion' => $primeraAprobacion,
            'fecha_aprobacion' => $primeraAprobacion,
            'fecha_vencimiento' => $vencimiento,
            'devolucion_en_proceso' => false,
            'estado_devolucion' => null,
        ]);

        // 1. Al consultar mis-reportes, se despacha el evento de aviso
        $responseMisReportes = $this->actingAs($autor)->get(route('reportes.mis-reportes'));
        $responseMisReportes->assertOk();
        $responseMisReportes->assertSee($reporte->folio);
        $responseMisReportes->assertSee('Pendiente de entrega a biblioteca');
        $responseMisReportes->assertSee('Dispones de dos días de atención de biblioteca posteriores al vencimiento, conforme al horario institucional, para llevar el objeto.');

        // Verificar despacho del evento con los datos requeridos
        Event::assertDispatched(PublicacionVencidaPendienteEntrega::class, function ($event) use ($reporte, $autor) {
            return $event->publicacion->id === $reporte->id
                && $event->autor->id === $autor->id
                && str_contains($event->plazoTexto, 'dos días de atención de biblioteca posteriores al vencimiento');
        });
    }

    /**
     * Criterio 2:
     * Desde el seguimiento autorizado, el reporte conserva su resguardo vigente,
     * fotografías, historial y evidencias disponibles.
     */
    public function test_conserva_resguardo_vigente_fotografias_historial_y_evidencias(): void
    {
        $ahoraMerida = Carbon::create(2026, 11, 20, 10, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($ahoraMerida);

        $autor = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $primeraAprobacion = Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE);
        $vencimiento = Carbon::create(2026, 11, 15, 10, 0, 0, Publicacion::TIMEZONE);

        $reporte = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Mochila Azul Samsonite',
            'descripcion' => 'Mochila escolar con cuadernos y estuche.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Edificio H',
            'lugar_hallazgo' => 'Laboratorio H1',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_captura' => Carbon::create(2026, 11, 1, 8, 30, 0, Publicacion::TIMEZONE),
            'fecha_primera_aprobacion' => $primeraAprobacion,
            'fecha_aprobacion' => $primeraAprobacion,
            'fecha_vencimiento' => $vencimiento,
            'devolucion_en_proceso' => false,
        ]);

        // Fotografía asociada
        $foto = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'fotografias/mochila.jpg',
            'nombre_original' => 'mochila.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
            'es_principal' => true,
        ]);

        // Decisión de moderación histórica
        DecisionModeracion::create([
            'publicacion_id' => $reporte->id,
            'user_id' => $admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_APROBADA,
            'motivo' => 'Publicación autorizada para el muro.',
            'fecha_decision' => $primeraAprobacion,
        ]);

        // 1. Resguardo vigente: continúa figurando bajo resguardo del autor
        $this->assertSame(Publicacion::RESGUARDO_AUTOR, $reporte->situacion_resguardo);
        $this->assertFalse($reporte->hasRecepcionFisicaConfirmada());

        // 2. Fotografías siguen disponibles
        $this->assertCount(1, $reporte->fresh()->fotografias);
        $this->assertSame($foto->id, $reporte->fresh()->foto_principal->id);

        // 3. Historial de eventos completo y preservado
        $eventos = $reporte->fresh()->getEventosHistorial();
        $this->assertTrue($eventos->contains('tipo_slug', 'creacion'));
        $this->assertTrue($eventos->contains('tipo_slug', 'aprobacion'));
        $this->assertTrue($eventos->contains('tipo_slug', 'vencimiento'));
        $this->assertTrue($eventos->contains('tipo_slug', 'pendiente_entrega_biblioteca'));

        // 4. Consulta autorizada desde el detalle privado
        $resShow = $this->actingAs($autor)->get(route('reportes.show', $reporte->id));
        $resShow->assertOk();
        $resShow->assertSee('En posesión del autor');
        $resShow->assertSee($reporte->folio);

        // 5. Consulta autorizada desde el historial completo
        $resHist = $this->actingAs($autor)->get(route('reportes.historial', $reporte->id));
        $resHist->assertOk();
        $resHist->assertSee('En posesión del autor');
        $resHist->assertSee('Pendiente de entrega a biblioteca');
    }

    /**
     * Criterio 3:
     * - Vuelve a ejecutar el proceso de vencimiento para verificar que exista un solo aviso (idempotencia).
     * - Simula un fallo del mecanismo de avisos para comprobar que la publicación permanezca
     *   fuera de la consulta pública de forma estricta.
     */
    public function test_un_solo_aviso_por_ejecucion_y_fallo_de_avisos_mantiene_reporte_fuera_del_muro(): void
    {
        Event::fake([PublicacionVencidaPendienteEntrega::class]);

        $ahoraMerida = Carbon::create(2026, 11, 20, 10, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($ahoraMerida);

        $autor = User::factory()->create();

        $primeraAprobacion = Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE);
        $vencimiento = Carbon::create(2026, 11, 15, 10, 0, 0, Publicacion::TIMEZONE);

        $reporte = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Termo Metálico Negro',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Cafetería',
            'lugar_hallazgo' => 'Mesa exterior',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_captura' => Carbon::create(2026, 11, 1, 9, 0, 0, Publicacion::TIMEZONE),
            'fecha_primera_aprobacion' => $primeraAprobacion,
            'fecha_aprobacion' => $primeraAprobacion,
            'fecha_vencimiento' => $vencimiento,
            'devolucion_en_proceso' => false,
        ]);

        // 1. Ejecutar el proceso programado múltiples veces
        $this->artisan('publicaciones:procesar-vencimientos')->assertExitCode(0);
        $this->artisan('publicaciones:procesar-vencimientos')->assertExitCode(0);
        $this->artisan('publicaciones:procesar-vencimientos')->assertExitCode(0);

        // Se verifica que se haya despachado exactamente UN solo aviso
        Event::assertDispatched(PublicacionVencidaPendienteEntrega::class, 1);

        // 2. SIMULAR FALLO DEL MECANISMO DE AVISOS:
        // Configurar un listener o disparador que lance una excepción simulada
        Event::listen(PublicacionVencidaPendienteEntrega::class, function () {
            throw new \RuntimeException('Error simulado en servicio externo de avisos institucionales');
        });

        // Crear otro reporte vencido
        $reporteFallo = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Lentes de Sol RayBan',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Edificio A',
            'lugar_hallazgo' => 'Pasillo principal',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_captura' => Carbon::create(2026, 11, 1, 9, 0, 0, Publicacion::TIMEZONE),
            'fecha_primera_aprobacion' => $primeraAprobacion,
            'fecha_aprobacion' => $primeraAprobacion,
            'fecha_vencimiento' => $vencimiento,
            'devolucion_en_proceso' => false,
        ]);

        // Despacho del aviso con fallo controlado en el mecanismo
        $reporteFallo->despacharAvisoVencimientoSiCorresponde();

        // COMPROBACIÓN CRÍTICA:
        // A pesar del fallo en el envío del aviso, la publicación DEBE PERMANECER FUERA DEL MURO
        // y de los resultados de búsqueda, y su enlace directo debe bloquearse.
        $this->assertTrue($reporteFallo->isVencida());
        $this->assertFalse($reporteFallo->isDisponible());
        $this->assertSame(0, Publicacion::disponiblesMuro()->where('id', $reporteFallo->id)->count());

        $resMuro = $this->get(route('muro'));
        $resMuro->assertDontSee('Lentes de Sol RayBan');

        $resBusqueda = $this->get(route('muro', ['buscar' => 'Lentes']));
        $resBusqueda->assertDontSee('Lentes de Sol RayBan');

        $resDetallePublico = $this->get(route('publicaciones.show', $reporteFallo->id));
        $resDetallePublico->assertRedirect();
        $resDetallePublico->assertSessionHas('warning', 'Esta publicación no está disponible para consulta');
    }
}
