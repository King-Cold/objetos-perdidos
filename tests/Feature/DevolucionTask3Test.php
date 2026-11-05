<?php

namespace Tests\Feature;

use App\Models\Devolucion;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DevolucionTask3Test extends TestCase
{
    use RefreshDatabase;

    private function crearPublicacionElegible(array $attributes = []): array
    {
        $autor = User::factory()->create();

        $ahora = Carbon::now(Publicacion::TIMEZONE);
        $publicacion = Publicacion::factory()->create(array_merge([
            'user_id' => $autor->id,
            'nombre' => 'Audífonos Sony Inalámbricos',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_hallazgo' => $ahora->copy()->subDays(3)->format('Y-m-d'),
            'hora_hallazgo' => '11:00',
            'fecha_primera_aprobacion' => $ahora->copy()->subDays(3),
            'fecha_vencimiento' => $ahora->copy()->addDays(11),
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'devolucion_en_proceso' => false,
            'incidencia_abierta' => false,
            'tiene_incidencia' => false,
            'estado_devolucion' => null,
        ], $attributes));

        return [$autor, $publicacion];
    }

    /**
     * CA-07 & CA-08: Registro correcto en estado «Pendiente de verificación», con mensaje establecido,
     * retiro del muro y detalle general, conservando el resguardo y sin marcar «Devuelto».
     */
    public function test_registro_correcto_en_estado_pendiente_y_retiro_temporal_de_publicacion(): void
    {
        Storage::fake('local');
        [$autor, $publicacion] = $this->crearPublicacionElegible();

        $foto = UploadedFile::fake()->image('evidencia_entrega.jpg', 800, 600);
        $fechaEntrega = Carbon::now(Publicacion::TIMEZONE)->subDays(1)->format('Y-m-d');
        $horaEntrega = '16:30';

        $response = $this->actingAs($autor)->post(route('devoluciones.store', $publicacion->id), [
            'receptor_nombre' => 'Martín Gamboa Chan',
            'receptor_identificador' => '19070899',
            'fecha_entrega' => $fechaEntrega,
            'hora_entrega' => $horaEntrega,
            'explicacion_propiedad' => 'El receptor presentó factura electrónica a su nombre y vinculó el bluetooth frente a mí.',
            'fotografia' => $foto,
        ]);

        // Redirección y mensaje institucional exacto (CA-07)
        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('status', 'La devolución se registró y está pendiente de verificación');

        // CA-07: Comprobar registro en BD
        $this->assertDatabaseHas('devoluciones', [
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Martín Gamboa Chan',
            'receptor_identificador' => '19070899',
            'fecha_entrega' => $fechaEntrega,
            'estado' => Devolucion::ESTADO_PENDIENTE,
        ]);

        $devolucion = Devolucion::where('publicacion_id', $publicacion->id)->first();
        $this->assertNotNull($devolucion);
        $this->assertNotNull($devolucion->fecha_captura);
        Storage::disk('local')->assertExists($devolucion->fotografia_ruta);

        // CA-07 & CA-08: Comprobar actualización de la publicación
        $publicacion->refresh();
        $this->assertFalse((bool) $publicacion->disponible);
        $this->assertTrue((bool) $publicacion->devolucion_en_proceso);
        $this->assertEquals(Devolucion::ESTADO_PENDIENTE, $publicacion->estado_devolucion);
        $this->assertEquals(Publicacion::ESTADO_APROBADA, $publicacion->estado); // No se marca "Devuelto"
        $this->assertEquals(Publicacion::RESGUARDO_AUTOR, $publicacion->situacion_resguardo); // No cambia resguardo

        // Retiro del muro y detalle general (CA-07)
        $this->assertFalse($publicacion->isDisponible());
        $responseMuro = $this->get(route('muro'));
        $responseMuro->assertDontSee('Audífonos Sony Inalámbricos');

        $responseDetalleGeneral = $this->get(route('publicaciones.show', $publicacion->id));
        $responseDetalleGeneral->assertRedirect(route('muro'));
        $responseDetalleGeneral->assertSessionHas('warning', 'Esta publicación no está disponible para consulta');
    }

    /**
     * CA-05: Separación estricta entre momento real de entrega y momento de captura,
     * obteniendo el autor responsable desde la sesión activa.
     */
    public function test_desacopla_momento_de_entrega_y_captura_y_toma_autor_de_sesion(): void
    {
        Storage::fake('local');
        [$autor, $publicacion] = $this->crearPublicacionElegible();

        $fechaEntregaAyer = Carbon::now(Publicacion::TIMEZONE)->subDays(2)->format('Y-m-d');
        $horaEntrega = '09:15';

        $this->actingAs($autor)->post(route('devoluciones.store', $publicacion->id), [
            'receptor_nombre' => 'Susana Díaz Pool',
            'receptor_identificador' => 'DOC-9081',
            'fecha_entrega' => $fechaEntregaAyer,
            'hora_entrega' => $horaEntrega,
            'explicacion_propiedad' => 'La profesora acreditó la propiedad con la caja y número de serie coincidentes.',
            'fotografia' => UploadedFile::fake()->image('prueba.png'),
            // Intento de suplantar autor o fecha de captura desde el payload
            'user_id' => 9999,
            'fecha_captura' => '2020-01-01 00:00:00',
        ]);

        $devolucion = Devolucion::where('publicacion_id', $publicacion->id)->firstOrFail();

        // El autor debe ser el autenticado en sesión, no el del request
        $this->assertEquals($autor->id, $devolucion->user_id);
        $this->assertNotEquals(9999, $devolucion->user_id);

        // Momento de entrega vs momento de captura
        $this->assertEquals($fechaEntregaAyer, $devolucion->fecha_entrega->format('Y-m-d'));
        $this->assertEquals('09:15', substr($devolucion->hora_entrega, 0, 5));
        $this->assertEquals(Carbon::now(Publicacion::TIMEZONE)->format('Y-m-d'), $devolucion->fecha_captura->format('Y-m-d'));
    }

    /**
     * CA-11: Prevención de registros duplicados en el servidor si ya existe una devolución activa.
     */
    public function test_rechaza_segundo_envio_si_ya_existe_devolucion_activa(): void
    {
        Storage::fake('local');
        [$autor, $publicacion] = $this->crearPublicacionElegible([
            'devolucion_en_proceso' => true,
            'estado_devolucion' => Devolucion::ESTADO_PENDIENTE,
        ]);

        $response = $this->actingAs($autor)->post(route('devoluciones.store', $publicacion->id), [
            'receptor_nombre' => 'Segundo Intento Receptor',
            'receptor_identificador' => '19070999',
            'fecha_entrega' => Carbon::now(Publicacion::TIMEZONE)->subDays(1)->format('Y-m-d'),
            'hora_entrega' => '12:00',
            'explicacion_propiedad' => 'Explicación.',
            'fotografia' => UploadedFile::fake()->image('foto2.jpg'),
        ]);

        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('warning');

        // No debe crear un nuevo registro de devolución
        $this->assertEquals(0, Devolucion::where('publicacion_id', $publicacion->id)->count());
    }

    /**
     * CA-12: Si la publicación vence entre la apertura y el envío, bloquea el registro con el mensaje establecido.
     */
    public function test_rechaza_registro_si_la_publicacion_vence_antes_del_envio(): void
    {
        Storage::fake('local');
        [$autor, $publicacion] = $this->crearPublicacionElegible([
            'fecha_vencimiento' => Carbon::now(Publicacion::TIMEZONE)->subMinute(),
        ]);

        $response = $this->actingAs($autor)->post(route('devoluciones.store', $publicacion->id), [
            'receptor_nombre' => 'Receptor Intento Vencido',
            'receptor_identificador' => '19070999',
            'fecha_entrega' => Carbon::now(Publicacion::TIMEZONE)->subDays(1)->format('Y-m-d'),
            'hora_entrega' => '10:00',
            'explicacion_propiedad' => 'Explicación.',
            'fotografia' => UploadedFile::fake()->image('foto.jpg'),
        ]);

        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('warning', 'La publicación ha vencido. Si entregaste el objeto antes del vencimiento y no registraste la devolución, contacta a biblioteca e indica el folio del reporte.');

        $this->assertEquals(0, Devolucion::where('publicacion_id', $publicacion->id)->count());
    }

    /**
     * CA-01: Rechaza registro si el objeto cambió a resguardo confirmado de biblioteca.
     */
    public function test_rechaza_registro_si_cambia_a_resguardo_biblioteca(): void
    {
        Storage::fake('local');
        [$autor, $publicacion] = $this->crearPublicacionElegible([
            'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO,
        ]);

        $response = $this->actingAs($autor)->post(route('devoluciones.store', $publicacion->id), [
            'receptor_nombre' => 'Receptor',
            'receptor_identificador' => '19070999',
            'fecha_entrega' => Carbon::now(Publicacion::TIMEZONE)->subDays(1)->format('Y-m-d'),
            'hora_entrega' => '12:00',
            'explicacion_propiedad' => 'Explicación.',
            'fotografia' => UploadedFile::fake()->image('foto.jpg'),
        ]);

        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('warning');
        $this->assertEquals(0, Devolucion::where('publicacion_id', $publicacion->id)->count());
    }
}
