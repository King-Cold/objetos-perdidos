<?php

namespace Tests\Feature;

use App\Models\Devolucion;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HU16Tarea2SeguimientoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Criterio 1:
     * Si el reporte vence, el objeto sigue con el autor y no hay devolución activa ni confirmada:
     * - Disponibilidad muestra «Expirada».
     * - Seguimiento muestra «Pendiente de entrega a biblioteca».
     * - Resguardo muestra «En posesión del autor».
     * - Se informa al autor que dispone de dos días de atención de biblioteca posteriores al vencimiento.
     */
    public function test_autor_ve_expirada_y_pendiente_entrega_en_mis_reportes_y_show(): void
    {
        $ahoraMerida = Carbon::create(2026, 11, 20, 10, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($ahoraMerida);

        $autor = User::factory()->create([
            'email' => 'autor.vencido@correo.itmerida.mx',
            'phone' => '9991234567',
        ]);

        $primeraAprobacion = Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE);
        $vencimiento = Carbon::create(2026, 11, 15, 10, 0, 0, Publicacion::TIMEZONE);

        $reporte = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Laptop HP Gris Plateada',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Edificio F',
            'lugar_hallazgo' => 'Laboratorio F3',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_primera_aprobacion' => $primeraAprobacion,
            'fecha_aprobacion' => $primeraAprobacion,
            'fecha_vencimiento' => $vencimiento,
            'devolucion_en_proceso' => false,
            'estado_devolucion' => null,
        ]);

        $this->assertTrue($reporte->isVencida());
        $this->assertTrue($reporte->correspondeEntregaBiblioteca());
        $this->assertSame('Expirada', $reporte->disponibilidad_texto);
        $this->assertSame('Pendiente de entrega a biblioteca', $reporte->seguimiento_texto);

        // 1. Consulta en Mis Reportes
        $responseMisReportes = $this->actingAs($autor)->get(route('reportes.mis-reportes'));
        $responseMisReportes->assertOk();
        $responseMisReportes->assertSee($reporte->folio);
        $responseMisReportes->assertSee('Expirada');
        $responseMisReportes->assertSee('Pendiente de entrega a biblioteca');
        $responseMisReportes->assertSee('En posesión del autor');
        $responseMisReportes->assertSee('dos días de atención de biblioteca posteriores al vencimiento');

        // 2. Consulta en el detalle privado del reporte (reportes.show)
        $responseShow = $this->actingAs($autor)->get(route('reportes.show', $reporte->id));
        $responseShow->assertOk();
        $responseShow->assertSee($reporte->folio);
        $responseShow->assertSee('Expirada');
        $responseShow->assertSee('Pendiente de entrega a biblioteca');
        $responseShow->assertSee('En posesión del autor');
        $responseShow->assertSee('dos días de atención de biblioteca posteriores al vencimiento');
    }

    /**
     * Criterio 2:
     * Administrador / bibliotecario consulta el reporte vencido en moderación y en historial,
     * visualizando el folio, «Expirada», «Pendiente de entrega a biblioteca» y la situación de resguardo.
     */
    public function test_administrador_y_bibliotecario_visualizan_situacion_en_seguimiento(): void
    {
        $ahoraMerida = Carbon::create(2026, 11, 20, 12, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($ahoraMerida);

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email' => 'admin.biblioteca@itmerida.mx',
        ]);

        $autor = User::factory()->create();

        $primeraAprobacion = Carbon::create(2026, 11, 1, 9, 30, 0, Publicacion::TIMEZONE);
        $vencimiento = Carbon::create(2026, 11, 15, 9, 30, 0, Publicacion::TIMEZONE);

        $reporte = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Calculadora Científica Casio',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Edificio B',
            'lugar_hallazgo' => 'Aula B4',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_primera_aprobacion' => $primeraAprobacion,
            'fecha_vencimiento' => $vencimiento,
            'devolucion_en_proceso' => false,
        ]);

        // Verificación en detalle de moderación
        $responseMod = $this->actingAs($admin)->get(route('admin.moderacion.show', $reporte->id));
        $responseMod->assertOk();
        $responseMod->assertSee($reporte->folio);
        $responseMod->assertSee('Expirada');
        $responseMod->assertSee('Pendiente de entrega a biblioteca');
        $responseMod->assertSee('En posesión del autor');

        // Verificación en historial del reporte
        $responseHist = $this->actingAs($admin)->get(route('reportes.historial', $reporte->id));
        $responseHist->assertOk();
        $responseHist->assertSee($reporte->folio);
        $responseHist->assertSee('Expirada');
        $responseHist->assertSee('Pendiente de entrega a biblioteca');
        $responseHist->assertSee('En posesión del autor');
    }

    /**
     * Criterio 3:
     * Idempotencia: consultas repetidas y ejecuciones sucesivas producen exactamente
     * un solo estado/evento de pendiente sin duplicados.
     */
    public function test_idempotencia_consultas_repetidas_producen_un_solo_pendiente(): void
    {
        $ahoraMerida = Carbon::create(2026, 11, 20, 10, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($ahoraMerida);

        $autor = User::factory()->create();
        $reporte = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Mochila Negra Puma',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Cafetería',
            'lugar_hallazgo' => 'Mesa exterior cafetería',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_primera_aprobacion' => Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE),
            'fecha_vencimiento' => Carbon::create(2026, 11, 15, 10, 0, 0, Publicacion::TIMEZONE),
            'devolucion_en_proceso' => false,
        ]);

        // Ejecutar 5 veces consecutivas el cálculo de eventos históricos
        for ($i = 0; $i < 5; $i++) {
            $eventos = $reporte->getEventosHistorial();
            $pendientes = $eventos->where('tipo_slug', 'pendiente_entrega_biblioteca');
            $this->assertCount(1, $pendientes, "En la iteración {$i} debe existir exactamente un evento de pendiente.");
        }

        // Consultas HTTP repetidas
        for ($j = 0; $j < 3; $j++) {
            $response = $this->actingAs($autor)->get(route('reportes.show', $reporte->id));
            $response->assertOk();
            // Comprobamos que el aviso principal de seguimiento esté presente
            $response->assertSee('Pendiente de entrega a biblioteca');
            // Y que los eventos históricos no se dupliquen al recargar la vista
            $this->assertCount(1, $reporte->fresh()->getEventosHistorial()->where('tipo_slug', 'pendiente_entrega_biblioteca'));
        }
    }

    /**
     * Criterio 4:
     * Si hay una devolución cancelada o rechazada, comprueba el resguardo vigente
     * para determinar si corresponde generar el pendiente de entrega.
     */
    public function test_devolucion_cancelada_comprueba_resguardo_vigente(): void
    {
        $ahoraMerida = Carbon::create(2026, 11, 20, 10, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($ahoraMerida);

        $autor = User::factory()->create();
        $reporte = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Termo Metálico Azul',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Canchas deportivas',
            'lugar_hallazgo' => 'Gradas de básquetbol',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_primera_aprobacion' => Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE),
            'fecha_vencimiento' => Carbon::create(2026, 11, 15, 10, 0, 0, Publicacion::TIMEZONE),
            'devolucion_en_proceso' => false,
            'estado_devolucion' => null, // Devolución previa quedó cancelada
        ]);

        // Se registra una devolución con estado 'Cancelada' en la tabla devoluciones
        Devolucion::create([
            'publicacion_id' => $reporte->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Estudiante Receptor',
            'receptor_identificador' => '20080001',
            'fecha_entrega' => '2026-11-10',
            'hora_entrega' => '12:00:00',
            'fecha_captura' => Carbon::create(2026, 11, 10, 12, 0, 0, Publicacion::TIMEZONE),
            'fotografia_ruta' => 'devoluciones/test.jpg',
            'explicacion_propiedad' => 'Devolución que fue cancelada por error de datos',
            'estado' => 'Cancelada',
        ]);

        // Al continuar bajo resguardo del autor ('En posesión del autor'), corresponde entrega a biblioteca
        $this->assertTrue($reporte->correspondeEntregaBiblioteca());
        $this->assertSame('Pendiente de entrega a biblioteca', $reporte->seguimiento_texto);

        // En contraste: si el resguardo ya está confirmado en biblioteca, NO corresponde entrega a biblioteca
        $reporte->update(['situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO]);
        $reporte->refresh();

        $this->assertFalse($reporte->correspondeEntregaBiblioteca());
        $this->assertNull($reporte->seguimiento_texto);
    }

    /**
     * Criterio 5:
     * Si existe una devolución en proceso o pendiente de verificación,
     * NO corresponde el pendiente de entrega a biblioteca.
     */
    public function test_devolucion_en_proceso_no_genera_pendiente_entrega_biblioteca(): void
    {
        $ahoraMerida = Carbon::create(2026, 11, 20, 10, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($ahoraMerida);

        $autor = User::factory()->create();
        $reporte = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Audífonos Bluetooth Sony',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Centro de Cómputo',
            'lugar_hallazgo' => 'Laboratorio CC1',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_primera_aprobacion' => Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE),
            'fecha_vencimiento' => Carbon::create(2026, 11, 15, 10, 0, 0, Publicacion::TIMEZONE),
            'devolucion_en_proceso' => true,
            'estado_devolucion' => 'Pendiente de verificación',
        ]);

        $this->assertTrue($reporte->isVencida());
        $this->assertFalse($reporte->correspondeEntregaBiblioteca());
        $this->assertSame('Devolución en verificación', $reporte->seguimiento_texto);
    }
}
