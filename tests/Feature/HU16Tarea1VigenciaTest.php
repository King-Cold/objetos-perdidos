<?php

namespace Tests\Feature;

use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HU16Tarea1VigenciaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Comprueba el cálculo centralizado de vigencia:
     * - Suma 14 días naturales exactos a la primera aprobación conservando la misma hora en America/Merida.
     * - La fecha del hallazgo y fecha de captura quedan fuera del cálculo.
     */
    public function test_calculo_centralizado_de_vigencia_a_partir_de_primera_aprobacion(): void
    {
        $fechaPrimeraAprobacion = Carbon::create(2026, 11, 1, 10, 30, 45, Publicacion::TIMEZONE);
        $vencimientoEsperado = Carbon::create(2026, 11, 15, 10, 30, 45, Publicacion::TIMEZONE);

        $calculado = Publicacion::calcularFechaVencimiento($fechaPrimeraAprobacion);

        $this->assertEquals($vencimientoEsperado->format('Y-m-d H:i:s'), $calculado->format('Y-m-d H:i:s'));
        $this->assertEquals(14, $fechaPrimeraAprobacion->diffInDays($calculado));
        $this->assertEquals(Publicacion::TIMEZONE, $calculado->timezoneName);
    }

    /**
     * Verifica el comportamiento exacto en los tres puntos temporales clave:
     * - 1 segundo antes del límite: reporte disponible en muro, búsquedas y detalle general.
     * - Exactamente al límite: fuera del muro, búsquedas y detalle general muestra mensaje de aviso.
     * - 1 segundo después del límite: fuera del muro, búsquedas y detalle general muestra mensaje de aviso.
     */
    public function test_comportamiento_un_segundo_antes_exactamente_al_limite_y_un_segundo_despues(): void
    {
        $fechaInicio = Carbon::create(2026, 11, 1, 14, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($fechaInicio);

        $autor = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261101-0099',
            'nombre' => 'Calculadora Científica Casio',
            'descripcion' => 'Calculadora científica en salón B-12',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'disponible' => false,
            'fecha_captura' => $fechaInicio->copy()->subDays(3),
            'fecha_hallazgo' => $fechaInicio->copy()->subDays(5)->toDateString(),
            'version' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => 1,
        ]);

        $reporte->refresh();
        $fechaLimite = $reporte->fecha_vencimiento;

        // --- 1. UN SEGUNDO ANTES DEL LÍMITE ---
        $vencimientoMerida = $fechaLimite->copy()->setTimezone(Publicacion::TIMEZONE);
        Carbon::setTestNow($vencimientoMerida->copy()->subSecond());

        $this->assertFalse($reporte->isVencida());
        $this->assertTrue($reporte->isDisponible());

        // Debe aparecer en el muro
        $resMuroAntes = $this->get(route('muro'));
        $resMuroAntes->assertStatus(200);
        $resMuroAntes->assertSee('Calculadora Científica Casio');

        // Debe encontrarse en la búsqueda por texto
        $resBusquedaAntes = $this->get(route('muro', ['buscar' => 'Calculadora Casio']));
        $resBusquedaAntes->assertStatus(200);
        $resBusquedaAntes->assertSee('Calculadora Científica Casio');

        // Debe abrirse en el detalle general
        $resDetalleAntes = $this->get(route('publicaciones.show', $reporte->id));
        $resDetalleAntes->assertStatus(200);
        $resDetalleAntes->assertSee('Calculadora Científica Casio');

        // --- 2. EXACTAMENTE AL LÍMITE ---
        $vencimientoMerida = $fechaLimite->copy()->setTimezone(Publicacion::TIMEZONE);
        Carbon::setTestNow($vencimientoMerida);

        $reporte->refresh();
        $this->assertTrue($reporte->isVencida());
        $this->assertFalse($reporte->isDisponible());

        // Fuera del muro
        $resMuroLimite = $this->get(route('muro'));
        $resMuroLimite->assertStatus(200);
        $resMuroLimite->assertDontSee('Calculadora Científica Casio');

        // Fuera de búsquedas
        $resBusquedaLimite = $this->get(route('muro', ['buscar' => 'Calculadora Casio']));
        $resBusquedaLimite->assertStatus(200);
        $resBusquedaLimite->assertDontSee('Calculadora Científica Casio');

        // Al abrir el detalle directo, debe redirigir con el mensaje específico
        $resDetalleLimite = $this->get(route('publicaciones.show', $reporte->id));
        $resDetalleLimite->assertRedirect();
        $resDetalleLimite->assertSessionHas('warning', 'Esta publicación no está disponible para consulta');

        // --- 3. UN SEGUNDO DESPUÉS DEL LÍMITE ---
        Carbon::setTestNow($vencimientoMerida->copy()->addSecond());

        $this->assertTrue($reporte->isVencida());
        $this->assertFalse($reporte->isDisponible());

        // Fuera del muro
        $resMuroDespues = $this->get(route('muro'));
        $resMuroDespues->assertStatus(200);
        $resMuroDespues->assertDontSee('Calculadora Científica Casio');

        // Fuera de búsquedas
        $resBusquedaDespues = $this->get(route('muro', ['buscar' => 'Calculadora Casio']));
        $resBusquedaDespues->assertStatus(200);
        $resBusquedaDespues->assertDontSee('Calculadora Científica Casio');

        // Al abrir el detalle directo, mensaje específico
        $resDetalleDespues = $this->get(route('publicaciones.show', $reporte->id));
        $resDetalleDespues->assertRedirect();
        $resDetalleDespues->assertSessionHas('warning', 'Esta publicación no está disponible para consulta');
    }

    /**
     * Verifica que la fecha límite original se conserva íntegra tras editar, corregir, reenviar o volver a aprobar.
     * Además, si la aprobación ocurre posterior al vencimiento, se mantiene fuera de la consulta pública.
     */
    public function test_conservacion_de_vencimiento_original_y_aprobacion_posterior_al_vencimiento(): void
    {
        $fechaInicio = Carbon::create(2026, 11, 1, 9, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($fechaInicio);

        $admin = User::factory()->admin()->create();
        $autor = User::factory()->create();

        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261101-0100',
            'nombre' => 'Mochila Azul Escolar',
            'descripcion' => 'Mochila olvidada en biblioteca',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'disponible' => false,
            'version' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => 1,
        ]);

        $reporte = $reporte->fresh();
        $fechaAprobacion = $reporte->fecha_primera_aprobacion;
        $vencimientoOriginal = $reporte->fecha_vencimiento;

        // 1. Simular edición y reenvío por parte del autor
        $fechaEdicion = Carbon::create(2026, 11, 5, 11, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($fechaEdicion);

        $reporte->reenviarARevision();
        $reporteActualizado = $reporte->fresh();

        $this->assertEquals($fechaAprobacion->format('Y-m-d H:i:s'), $reporteActualizado->fecha_primera_aprobacion->format('Y-m-d H:i:s'));
        $this->assertEquals($vencimientoOriginal->format('Y-m-d H:i:s'), $reporteActualizado->fecha_vencimiento->format('Y-m-d H:i:s'));

        // 2. Nueva aprobación durante periodo vigente (8 de noviembre)
        $fechaReaprobacion = Carbon::create(2026, 11, 8, 16, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($fechaReaprobacion);
        $this->flushSession();
        $this->actingAs($admin);
        session(['last_activity_time' => Carbon::now()->timestamp]);

        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => $reporte->fresh()->version,
        ]);

        $reporteReaprobado = $reporte->fresh();
        $this->assertEquals($vencimientoOriginal->format('Y-m-d H:i:s'), $reporteReaprobado->fecha_vencimiento->format('Y-m-d H:i:s'));
        $this->assertEquals($fechaAprobacion->format('Y-m-d H:i:s'), $reporteReaprobado->fecha_primera_aprobacion->format('Y-m-d H:i:s'));
        $this->assertTrue($reporteReaprobado->isDisponible());

        // 3. Simular ciclo de corrección y aprobación DESPUÉS del límite de vencimiento (ej. 16 de noviembre)
        $fechaPostVencimiento = Carbon::create(2026, 11, 16, 10, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($fechaPostVencimiento);
        $this->flushSession();
        $this->actingAs($admin);
        session(['last_activity_time' => Carbon::now()->timestamp]);

        $reporteReaprobado->reenviarARevision();

        $this->actingAs($admin)->post(route('admin.moderacion.aprobar', $reporte->id), [
            'lock_version' => $reporteReaprobado->fresh()->version,
        ]);

        $reporteFinal = $reporte->fresh();

        // Se conserva el vencimiento original
        $this->assertEquals($vencimientoOriginal->format('Y-m-d H:i:s'), $reporteFinal->fecha_vencimiento->format('Y-m-d H:i:s'));
        $this->assertEquals($fechaAprobacion->format('Y-m-d H:i:s'), $reporteFinal->fecha_primera_aprobacion->format('Y-m-d H:i:s'));
        $this->assertTrue($reporteFinal->isVencida());

        // Mantiene la publicación fuera de la consulta pública
        $this->assertFalse((bool) $reporteFinal->disponible);
        $this->assertFalse($reporteFinal->isDisponible());

        // No aparece en muro
        $this->get(route('muro'))->assertDontSee('Mochila Azul Escolar');

        // Detalle general bloqueado con mensaje
        $this->get(route('publicaciones.show', $reporte->id))
            ->assertRedirect()
            ->assertSessionHas('warning', 'Esta publicación no está disponible para consulta');
    }
}

