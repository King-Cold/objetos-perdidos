<?php

namespace Tests\Feature\Admin;

use App\Models\Devolucion;
use App\Models\Publicacion;
use App\Models\Recepcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DevolucionExtemporaneaOrientacionAccionTask1Test extends TestCase
{
    use RefreshDatabase;

    private function crearReporteVencido(User $autor): Publicacion
    {
        $fechaAprobacion = Carbon::now()->subDays(16);
        $fechaVencimiento = Carbon::now()->subDays(2);

        return Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OP-2026-EXT01',
            'nombre' => 'Calculadora Científica Casio',
            'titulo' => 'Calculadora Científica Casio',
            'categoria' => 'Electrónicos',
            'descripcion' => 'Calculadora color negro encontrada en el aula B-12.',
            'area_zona' => 'Edificio B',
            'ubicacion_especifica' => 'Aula B-12',
            'fecha_hallazgo' => Carbon::now()->subDays(17)->toDateString(),
            'hora_hallazgo' => '10:30',
            'estado' => Publicacion::ESTADO_APROBADA,
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_primera_aprobacion' => $fechaAprobacion,
            'fecha_vencimiento' => $fechaVencimiento,
        ]);
    }

    public function test_orientacion_al_autor_en_seguimiento_de_reporte_expirado_sin_habilitar_entrega_ordinaria(): void
    {
        $autor = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $reporte = $this->crearReporteVencido($autor);

        $mensajeEsperado = 'Si entregaste el objeto antes del vencimiento y no registraste la devolución, contacta a biblioteca e indica el folio del reporte';

        // 1. En "Mis reportes" se muestra el mensaje de orientación y NO se muestra acción ordinaria ni administrativa
        $respMisReportes = $this->actingAs($autor)->get(route('reportes.mis-reportes'));
        $respMisReportes->assertOk();
        $respMisReportes->assertSee($mensajeEsperado);
        $respMisReportes->assertDontSee('Registrar devolución reportada fuera de tiempo');
        $respMisReportes->assertDontSee(route('devoluciones.create', $reporte->id));

        // 2. En el detalle del reporte se muestra el mensaje de orientación y NO el botón ordinario
        $respShow = $this->actingAs($autor)->get(route('reportes.show', $reporte->id));
        $respShow->assertOk();
        $respShow->assertSee($mensajeEsperado);
        $respShow->assertDontSee('Registrar devolución reportada fuera de tiempo');
        $respShow->assertDontSee(route('devoluciones.create', $reporte->id));

        // 3. En el historial del objeto se muestra el mensaje de orientación al autor sin habilitar el botón administrativo
        $respHistorial = $this->actingAs($autor)->get(route('reportes.historial', $reporte->id));
        $respHistorial->assertOk();
        $respHistorial->assertSee($mensajeEsperado);
        $respHistorial->assertDontSee('Registrar devolución reportada fuera de tiempo');

        // 4. El autor no puede acceder al formulario ordinario de devolución después del vencimiento
        $respCreateOrdinaria = $this->actingAs($autor)->get(route('devoluciones.create', $reporte->id));
        $respCreateOrdinaria->assertRedirect(route('reportes.mis-reportes'));
        $respCreateOrdinaria->assertSessionHas('warning');

        // 5. El autor tampoco puede acceder a la ruta administrativa de devolución fuera de tiempo
        $respAdminRoute = $this->actingAs($autor)->get(route('admin.devoluciones-extemporaneas.create', $reporte->id));
        $respAdminRoute->assertForbidden();
    }

    public function test_bibliotecario_dispone_de_registrar_devolucion_reportada_fuera_de_tiempo_en_expirado_elegible(): void
    {
        $autor = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);
        $bibliotecario = User::factory()->admin()->create();

        $reporte = $this->crearReporteVencido($autor);

        $this->assertTrue($reporte->puedeRegistrarDevolucionExtemporanea());

        // Visible en detalle de moderación
        $respModeracionShow = $this->actingAs($bibliotecario)->get(route('admin.moderacion.show', $reporte->id));
        $respModeracionShow->assertOk();
        $respModeracionShow->assertSee('Registrar devolución reportada fuera de tiempo');
        $respModeracionShow->assertSee(route('admin.devoluciones-extemporaneas.create', $reporte->id));

        // Visible en listado de moderación (filtro vencidas)
        $respModeracionIndex = $this->actingAs($bibliotecario)->get(route('admin.moderacion.index', ['filtro' => 'vencidas']));
        $respModeracionIndex->assertOk();
        $respModeracionIndex->assertSee('Registrar devolución reportada fuera de tiempo');

        // Visible en inventario (sección pendientes de recepción)
        $respInventario = $this->actingAs($bibliotecario)->get(route('admin.inventario.index'));
        $respInventario->assertOk();
        $respInventario->assertSee('Registrar devolución reportada fuera de tiempo');

        // Visible en historial para el bibliotecario
        $respHistorial = $this->actingAs($bibliotecario)->get(route('reportes.historial', $reporte->id));
        $respHistorial->assertOk();
        $respHistorial->assertSee('Registrar devolución reportada fuera de tiempo');

        // Acceso exitoso a la vista administrativa de la acción
        $respAccion = $this->actingAs($bibliotecario)->get(route('admin.devoluciones-extemporaneas.create', $reporte->id));
        $respAccion->assertOk();
        $respAccion->assertSee('Registrar devolución reportada fuera de tiempo');
        $respAccion->assertSee($reporte->folio);
    }

    public function test_accion_administrativa_no_se_habilita_cuando_no_cumple_condiciones(): void
    {
        $autor = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);
        $bibliotecario = User::factory()->admin()->create();

        // Caso A: Reporte aún vigente (no expirado)
        $reporteVigente = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OP-2026-VIG01',
            'nombre' => 'Termo Metálico',
            'titulo' => 'Termo Metálico',
            'categoria' => 'Otros',
            'descripcion' => 'Termo azul en cafetería.',
            'area_zona' => 'Cafetería',
            'fecha_hallazgo' => Carbon::now()->subDays(2)->toDateString(),
            'estado' => Publicacion::ESTADO_APROBADA,
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_primera_aprobacion' => Carbon::now()->subDays(1),
            'fecha_vencimiento' => Carbon::now()->addDays(13),
        ]);

        $this->assertFalse($reporteVigente->puedeRegistrarDevolucionExtemporanea());
        $this->actingAs($bibliotecario)
            ->get(route('admin.devoluciones-extemporaneas.create', $reporteVigente->id))
            ->assertRedirect(route('admin.moderacion.show', $reporteVigente->id));

        // Caso B: Reporte expirado pero con devolución activa (Pendiente de verificación)
        $reporteConDevolucionActiva = $this->crearReporteVencido($autor);
        $reporteConDevolucionActiva->update(['folio' => 'OP-2026-DEV01']);

        Devolucion::create([
            'publicacion_id' => $reporteConDevolucionActiva->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Juan Pérez',
            'receptor_identificador' => '20261234',
            'fecha_entrega' => Carbon::now()->subDays(5)->toDateString(),
            'hora_entrega' => '12:00',
            'fecha_captura' => Carbon::now()->subDays(5),
            'explicacion_propiedad' => 'Mostró credencial y describió el objeto.',
            'fotografia_ruta' => 'devoluciones/evidencia1.jpg',
            'fotografia_nombre_original' => 'evidencia1.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 102400,
            'estado' => Devolucion::ESTADO_PENDIENTE,
        ]);

        $this->assertFalse($reporteConDevolucionActiva->fresh()->puedeRegistrarDevolucionExtemporanea());
        $this->actingAs($bibliotecario)
            ->get(route('admin.moderacion.show', $reporteConDevolucionActiva->id))
            ->assertDontSee(route('admin.devoluciones-extemporaneas.create', $reporteConDevolucionActiva->id));
        $this->actingAs($bibliotecario)
            ->get(route('admin.devoluciones-extemporaneas.create', $reporteConDevolucionActiva->id))
            ->assertRedirect(route('admin.moderacion.show', $reporteConDevolucionActiva->id));

        // Caso C: Reporte expirado pero con devolución en corrección (Requiere corrección)
        $reporteConDevolucionEnCorreccion = $this->crearReporteVencido($autor);
        $reporteConDevolucionEnCorreccion->update(['folio' => 'OP-2026-COR01']);

        Devolucion::create([
            'publicacion_id' => $reporteConDevolucionEnCorreccion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'María López',
            'receptor_identificador' => '20269999',
            'fecha_entrega' => Carbon::now()->subDays(4)->toDateString(),
            'hora_entrega' => '11:00',
            'fecha_captura' => Carbon::now()->subDays(4),
            'explicacion_propiedad' => 'Explicación inicial.',
            'fotografia_ruta' => 'devoluciones/evidencia2.jpg',
            'fotografia_nombre_original' => 'evidencia2.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 102400,
            'estado' => Devolucion::ESTADO_REQUIERE_CORRECCION,
            'motivo_correccion' => 'Foto borrosa',
        ]);

        $this->assertFalse($reporteConDevolucionEnCorreccion->fresh()->puedeRegistrarDevolucionExtemporanea());

        // Caso D: Reporte expirado pero con objeto ya recibido físicamente en biblioteca
        $reporteRecibidoEnBiblioteca = $this->crearReporteVencido($autor);
        $reporteRecibidoEnBiblioteca->update([
            'folio' => 'OP-2026-REC01',
            'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO,
        ]);

        Recepcion::create([
            'publicacion_id' => $reporteRecibidoEnBiblioteca->id,
            'user_id' => $bibliotecario->id,
            'fecha_recepcion' => Carbon::now()->subDay()->toDateString(),
            'hora_recepcion' => '09:30',
            'condicion_objeto' => 'Buen estado',
            'ubicacion_resguardo' => 'Estante A-1',
            'confirmacion_cotejo' => true,
        ]);

        $this->assertFalse($reporteRecibidoEnBiblioteca->fresh()->puedeRegistrarDevolucionExtemporanea());
        $this->actingAs($bibliotecario)
            ->get(route('admin.devoluciones-extemporaneas.create', $reporteRecibidoEnBiblioteca->id))
            ->assertRedirect(route('admin.moderacion.show', $reporteRecibidoEnBiblioteca->id));

        // Caso E: Reporte expirado pero con devolución verificada
        $reporteConDevolucionVerificada = $this->crearReporteVencido($autor);
        $reporteConDevolucionVerificada->update([
            'folio' => 'OP-2026-VER01',
            'estado' => Publicacion::ESTADO_DEVUELTO,
            'situacion_resguardo' => Publicacion::ESTADO_DEVUELTO,
        ]);

        Devolucion::create([
            'publicacion_id' => $reporteConDevolucionVerificada->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Carlos Ruiz',
            'receptor_identificador' => '20265555',
            'fecha_entrega' => Carbon::now()->subDays(6)->toDateString(),
            'hora_entrega' => '14:00',
            'fecha_captura' => Carbon::now()->subDays(6),
            'explicacion_propiedad' => 'Comprobó propiedad.',
            'fotografia_ruta' => 'devoluciones/evidencia3.jpg',
            'fotografia_nombre_original' => 'evidencia3.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 102400,
            'estado' => Devolucion::ESTADO_VERIFICADA,
        ]);

        $this->assertFalse($reporteConDevolucionVerificada->fresh()->puedeRegistrarDevolucionExtemporanea());
        $this->actingAs($bibliotecario)
            ->get(route('admin.devoluciones-extemporaneas.create', $reporteConDevolucionVerificada->id))
            ->assertRedirect(route('admin.moderacion.show', $reporteConDevolucionVerificada->id));
    }
}
