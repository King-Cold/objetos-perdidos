<?php

namespace Tests\Feature;

use App\Models\DecisionModeracion;
use App\Models\Devolucion;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HU16Tarea3DevolucionesOportunasTest extends TestCase
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
     * Una devolución guardada ANTES del límite continúa su flujo ordinario de
     * verificación, corrección o resolución después del vencimiento.
     * El reporte permanece fuera de la consulta pública y su seguimiento carece
     * del pendiente de entrega a biblioteca.
     */
    public function test_devolucion_oportuna_continua_en_revision_despues_del_vencimiento_sin_pendiente_biblioteca(): void
    {
        // Fecha base: Aprobación inicial el 1 de noviembre a las 10:00:00 (vence 15 de noviembre a las 10:00:00)
        $primeraAprobacion = Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE);
        $vencimiento = Carbon::create(2026, 11, 15, 10, 0, 0, Publicacion::TIMEZONE);

        // Devolución registrada OPORTUNAMENTE el 10 de noviembre a las 14:00 (5 días antes del vencimiento)
        $fechaDevolucionOportuna = Carbon::create(2026, 11, 10, 14, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($fechaDevolucionOportuna);

        $autor = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $reporte = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Mochila Negra con Laptop',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Edificio B',
            'lugar_hallazgo' => 'Laboratorio B2',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_primera_aprobacion' => $primeraAprobacion,
            'fecha_aprobacion' => $primeraAprobacion,
            'fecha_vencimiento' => $vencimiento,
            'devolucion_en_proceso' => true,
            'estado_devolucion' => Devolucion::ESTADO_PENDIENTE,
        ]);

        $archivoEvidencia = UploadedFile::fake()->create('evidencia_entrega.jpg', 150, 'image/jpeg');
        $rutaEvidencia = $archivoEvidencia->storeAs('devoluciones/' . $reporte->id, 'evidencia.jpg', 'local');

        $devolucion = Devolucion::create([
            'publicacion_id' => $reporte->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Carlos Solís',
            'receptor_identificador' => '20080123',
            'fecha_entrega' => '2026-11-10',
            'hora_entrega' => '13:50:00',
            'fecha_captura' => $fechaDevolucionOportuna,
            'fecha_envio_revision' => $fechaDevolucionOportuna,
            'explicacion_propiedad' => 'Demostró ser propietario encendiendo el equipo con su contraseña.',
            'fotografia_ruta' => $rutaEvidencia,
            'estado' => Devolucion::ESTADO_PENDIENTE,
        ]);

        // AVANZAMOS EL TIEMPO: Nos situamos DESPUÉS del vencimiento (20 de noviembre)
        $despuesVencimiento = Carbon::create(2026, 11, 20, 12, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($despuesVencimiento);

        $reporte->refresh();

        // 1. Está vencida
        $this->assertTrue($reporte->isVencida());

        // 2. Está fuera de la consulta pública (muro)
        $this->assertFalse($reporte->isDisponible());
        $this->assertSame(0, Publicacion::disponiblesMuro()->where('id', $reporte->id)->count());

        // 3. NO genera pendiente de entrega a biblioteca porque tiene devolución activa en proceso
        $this->assertFalse($reporte->correspondeEntregaBiblioteca());
        $this->assertSame('Devolución en verificación', $reporte->seguimiento_texto);

        // 4. En el seguimiento privado (reportes.show y reportes.mis-reportes), NO se muestra el pendiente
        $resShow = $this->actingAs($autor)->get(route('reportes.show', $reporte->id));
        $resShow->assertOk();
        $resShow->assertDontSee('Pendiente de entrega a biblioteca');
        $resShow->assertSee('Pendiente de verificación');

        // 5. El flujo administrativo continúa activo: Administrador puede abrir la devolución y resolverla
        $resDevShow = $this->actingAs($admin)->get(route('admin.devoluciones.show', $devolucion->id));
        $resDevShow->assertOk();
        $resDevShow->assertSee('Carlos Solís');

        // El administrador aprueba la devolución después del vencimiento sin impedimento
        $resAprobar = $this->actingAs($admin)->post(route('admin.devoluciones.aprobar', $devolucion->id));
        $resAprobar->assertRedirect(route('admin.devoluciones.show', $devolucion->id));

        $reporte->refresh();
        $this->assertSame(Publicacion::ESTADO_DEVUELTO, $reporte->estado);
        $this->assertSame(Devolucion::ESTADO_VERIFICADA, $reporte->estado_devolucion);
        $this->assertFalse((bool) $reporte->disponible);
    }

    /**
     * Criterio 2:
     * Al intentar abrir por URL directa el formulario de devolución (/reportes/{id}/devolucion/registrar)
     * de un reporte vencido, el servidor lo bloquea y redirige con mensaje de advertencia.
     */
    public function test_autor_intenta_abrir_formulario_devolucion_reporte_vencido_es_rechazado(): void
    {
        $ahoraMerida = Carbon::create(2026, 11, 20, 10, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($ahoraMerida);

        $autor = User::factory()->create();
        $reporte = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Audífonos Inalámbricos JBL',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Edificio D',
            'lugar_hallazgo' => 'Pasillo central',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_primera_aprobacion' => Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE),
            'fecha_vencimiento' => Carbon::create(2026, 11, 15, 10, 0, 0, Publicacion::TIMEZONE),
            'devolucion_en_proceso' => false,
        ]);

        $this->assertTrue($reporte->isVencida());
        $this->assertFalse($reporte->puedeRegistrarDevolucion());

        // El autor intenta abrir la URL directa de creación de devolución
        $response = $this->actingAs($autor)->get(route('devoluciones.create', $reporte->id));

        // Debe redirigir a mis-reportes con mensaje explicativo
        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('warning', 'La publicación ha vencido. Si entregaste el objeto antes del vencimiento y no registraste la devolución, contacta a biblioteca e indica el folio del reporte.');
    }

    /**
     * Criterio 3:
     * Caso del formulario abierto ANTES del límite y enviado AL llegar al vencimiento o DESPUÉS.
     * El servidor debe rechazar el POST, no guardar la devolución ni modificar el vencimiento.
     */
    public function test_formulario_abierto_antes_del_limite_y_enviado_al_vencer_o_despues_es_rechazado(): void
    {
        $fechaInicio = Carbon::create(2026, 11, 1, 12, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($fechaInicio);

        $autor = User::factory()->create();

        $vencimiento = $fechaInicio->copy()->addDays(14); // 2026-11-15 12:00:00

        $reporte = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Llaves con Llavero Metálico TecNM',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Edificio C',
            'lugar_hallazgo' => 'Laboratorio C1',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_captura' => $fechaInicio->copy()->subDays(2),
            'fecha_primera_aprobacion' => $fechaInicio,
            'fecha_aprobacion' => $fechaInicio,
            'fecha_vencimiento' => $vencimiento,
            'devolucion_en_proceso' => false,
            'estado_devolucion' => null,
        ]);

        $vencimientoMerida = $reporte->fecha_vencimiento->copy()->setTimezone(Publicacion::TIMEZONE);

        // 1 minuto antes del vencimiento: el formulario era accesible
        Carbon::setTestNow($vencimientoMerida->copy()->subMinute());
        $reporte->refresh();

        $this->assertFalse($reporte->isVencida());
        $this->assertTrue($reporte->puedeRegistrarDevolucion());

        // EL USUARIO DEJÓ ABIERTO EL FORMULARIO Y PRESIONA ENVIAR AL LLEGAR AL VENCIMIENTO EXACTO:
        Carbon::setTestNow($vencimientoMerida);

        $archivoEvidencia = UploadedFile::fake()->create('evidencia_tardia.jpg', 200, 'image/jpeg');

        $payload = [
            'receptor_nombre' => 'Mariana Pech',
            'receptor_identificador' => '21080999',
            'fecha_entrega' => $vencimientoMerida->format('Y-m-d'),
            'hora_entrega' => $vencimientoMerida->format('H:i'),
            'explicacion_propiedad' => 'Probó la llave en el candado asignado en el casillero.',
            'fotografia' => $archivoEvidencia,
            'declaracion_veracidad' => '1',
        ];

        $responsePost = $this->actingAs($autor)->post(route('devoluciones.store', $reporte->id), $payload);

        // 1. Debe rechazar el POST y redirigir
        $responsePost->assertRedirect(route('reportes.mis-reportes'));
        $responsePost->assertSessionHas('warning', 'La publicación ha vencido. Si entregaste el objeto antes del vencimiento y no registraste la devolución, contacta a biblioteca e indica el folio del reporte.');

        // 2. Ninguna devolución debe haber sido creada en base de datos
        $this->assertSame(0, Devolucion::where('publicacion_id', $reporte->id)->count());

        // 3. El reporte permanece sin devolución en proceso y conserva su vencimiento original intacto
        $reporte->refresh();
        $this->assertFalse((bool) $reporte->devolucion_en_proceso);
        $this->assertNull($reporte->estado_devolucion);
        $this->assertSame($vencimientoMerida->format('Y-m-d H:i:s'), $reporte->fecha_vencimiento->copy()->setTimezone(Publicacion::TIMEZONE)->format('Y-m-d H:i:s'));

        // 4. Consultar el detalle del reporte no modifica la fecha límite ni el estado de vencimiento
        $resShow = $this->actingAs($autor)->get(route('reportes.show', $reporte->id));
        $resShow->assertOk();

        $reporte->refresh();
        $this->assertSame($vencimientoMerida->format('Y-m-d H:i:s'), $reporte->fecha_vencimiento->copy()->setTimezone(Publicacion::TIMEZONE)->format('Y-m-d H:i:s'));
    }
}
