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

class HistorialLineaTiempoTask2Test extends TestCase
{
    use RefreshDatabase;

    private function crearAutorYAdmin(): array
    {
        $autor = User::factory()->create([
            'name' => 'Carla Mendoza Pech',
            'email' => 'carla.mendoza@campus.tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $admin = User::factory()->create([
            'name' => 'Mtro. Fernando Canul',
            'email' => 'fernando.canul@biblioteca.tecnm.mx',
            'role' => User::ROLE_ADMIN,
        ]);

        return [$autor, $admin];
    }

    /**
     * Criterio: Eventos registrados en orden cronológico ascendente.
     * Los eventos deben presentarse ordenados desde el más antiguo al más reciente.
     */
    public function test_linea_tiempo_presenta_eventos_en_orden_cronologico_ascendente(): void
    {
        [$autor, $admin] = $this->crearAutorYAdmin();
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        // 1. Creación (hace 10 días a las 09:00)
        $fechaCreacion = $ahora->copy()->subDays(10)->setTime(9, 0, 0);
        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-0001',
            'nombre' => 'Laptop HP Pavilion Azul',
            'descripcion' => 'Encontrada en cubículos de cómputo.',
            'estado' => Publicacion::ESTADO_DEVUELTO,
            'disponible' => false,
            'fecha_captura' => $fechaCreacion,
            'created_at' => $fechaCreacion,
            'updated_at' => $fechaCreacion,
            'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO,
        ]);

        // 2. Solicitud de corrección (hace 8 días a las 11:30)
        $fechaCorreccion = $ahora->copy()->subDays(8)->setTime(11, 30, 0);
        DecisionModeracion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_CORRECCION_SOLICITADA,
            'motivo' => 'Por favor especificar el modelo exacto y el aula.',
            'fecha_decision' => $fechaCorreccion,
            'created_at' => $fechaCorreccion,
        ]);

        // 3. Aprobación (hace 6 días a las 14:00)
        $fechaAprobacion = $ahora->copy()->subDays(6)->setTime(14, 0, 0);
        DecisionModeracion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_APROBADA,
            'motivo' => 'Datos validados correctamente. Aprobado para el muro.',
            'fecha_decision' => $fechaAprobacion,
            'created_at' => $fechaAprobacion,
        ]);

        // 4. Recepción en biblioteca (hace 4 días a las 10:00)
        $fechaRecepcion = $ahora->copy()->subDays(4)->setTime(10, 0, 0);
        DecisionModeracion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $admin->id,
            'tipo_decision' => 'recepcion_confirmada',
            'motivo' => 'Objeto recibido físicamente en el módulo de biblioteca central.',
            'fecha_decision' => $fechaRecepcion,
            'created_at' => $fechaRecepcion,
        ]);
        $publicacion->timestamps = false;
        $publicacion->update([
            'updated_at' => $fechaRecepcion,
            'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO,
        ]);
        $publicacion->timestamps = true;

        // 5. Devolución física (hace 2 días a las 16:30)
        $fechaDevolucion = $ahora->copy()->subDays(2)->setTime(16, 30, 0);
        Storage::fake('local');
        $foto = UploadedFile::fake()->image('evidencia.jpg', 400, 300);
        $ruta = $foto->storeAs('devoluciones/' . $publicacion->id, 'evidencia.jpg', 'local');

        Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Esteban Solís May',
            'receptor_identificador' => '19080555',
            'fecha_entrega' => $fechaDevolucion->format('Y-m-d'),
            'hora_entrega' => $fechaDevolucion->format('H:i:s'),
            'fecha_captura' => $fechaDevolucion,
            'explicacion_propiedad' => 'Demostró propiedad mediante contraseña y factura original.',
            'fotografia_ruta' => $ruta,
            'estado' => Devolucion::ESTADO_VERIFICADA,
            'created_at' => $fechaDevolucion,
        ]);

        $response = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $response->assertOk();

        $content = $response->getContent();

        // Verificamos presencia de los tipos de eventos
        $response->assertSee('Creación');
        $response->assertSee('Solicitud de corrección');
        $response->assertSee('Aprobación');
        $response->assertSee('Recepción en biblioteca');
        $response->assertSee('Devolución');

        // Verificamos que el orden en el HTML sea cronológico ascendente
        $posCreacion = strpos($content, 'data-tipo="Creación"');
        $posCorreccion = strpos($content, 'data-tipo="Solicitud de corrección"');
        $posAprobacion = strpos($content, 'data-tipo="Aprobación"');
        $posRecepcion = strpos($content, 'data-tipo="Recepción en biblioteca"');
        $posDevolucion = strpos($content, 'data-tipo="Devolución"');

        $this->assertNotFalse($posCreacion);
        $this->assertNotFalse($posCorreccion);
        $this->assertNotFalse($posAprobacion);
        $this->assertNotFalse($posRecepcion);
        $this->assertNotFalse($posDevolucion);

        $this->assertTrue($posCreacion < $posCorreccion, 'Creación debe preceder a la solicitud de corrección');
        $this->assertTrue($posCorreccion < $posAprobacion, 'Corrección debe preceder a la aprobación');
        $this->assertTrue($posAprobacion < $posRecepcion, 'Aprobación debe preceder a la recepción');
        $this->assertTrue($posRecepcion < $posDevolucion, 'Recepción debe preceder a la devolución');
    }

    /**
     * Criterio: Evento de creación con tipo, fecha/hora, responsable y motivo.
     */
    public function test_evento_creacion_muestra_tipo_fecha_hora_responsable_y_observacion(): void
    {
        [$autor, $admin] = $this->crearAutorYAdmin();
        $fechaCaptura = Carbon::create(2026, 11, 1, 8, 30, 0, Publicacion::TIMEZONE);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261101-0005',
            'nombre' => 'Calculadora Casio fx-991',
            'descripcion' => 'Calculadora científica en funda de piel.',
            'fecha_captura' => $fechaCaptura,
        ]);

        $response = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $response->assertOk();

        $response->assertSee('Creación');
        $response->assertSee('01/11/2026 • 08:30 a. m.');
        $response->assertSee('Carla Mendoza Pech');
        $response->assertSee('OBJ-20261101-0005');
        $response->assertSee('Calculadora científica en funda de piel.');
    }

    /**
     * Criterio: Evento de aprobación con tipo, fecha/hora, responsable y motivo.
     */
    public function test_evento_aprobacion_muestra_tipo_fecha_hora_responsable_y_motivo(): void
    {
        [$autor, $admin] = $this->crearAutorYAdmin();
        $fechaCaptura = Carbon::create(2026, 11, 2, 9, 0, 0, Publicacion::TIMEZONE);
        $fechaAprobacion = Carbon::create(2026, 11, 2, 11, 45, 0, Publicacion::TIMEZONE);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261102-0010',
            'fecha_captura' => $fechaCaptura,
            'estado' => Publicacion::ESTADO_APROBADA,
        ]);

        DecisionModeracion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_APROBADA,
            'motivo' => 'Publicación autorizada para su exhibición en el muro institucional.',
            'fecha_decision' => $fechaAprobacion,
        ]);

        $response = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $response->assertOk();

        $response->assertSee('Aprobación');
        $response->assertSee('02/11/2026 • 11:45 a. m.');
        $response->assertSee('Mtro. Fernando Canul');
        $response->assertSee('Publicación autorizada para su exhibición en el muro institucional.');
    }

    /**
     * Criterio: Evento de corrección con tipo, fecha/hora, responsable y motivo.
     */
    public function test_evento_correccion_muestra_tipo_fecha_hora_responsable_y_motivo(): void
    {
        [$autor, $admin] = $this->crearAutorYAdmin();
        $fechaCorr = Carbon::create(2026, 11, 3, 15, 10, 0, Publicacion::TIMEZONE);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261103-0020',
            'estado' => Publicacion::ESTADO_CORRECCION_SOLICITADA,
        ]);

        DecisionModeracion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_CORRECCION_SOLICITADA,
            'motivo' => 'La fotografía del hallazgo está borrosa; favor de adjuntar una toma nítida.',
            'fecha_decision' => $fechaCorr,
        ]);

        $response = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $response->assertOk();

        $response->assertSee('Solicitud de corrección');
        $response->assertSee('03/11/2026 • 03:10 p. m.');
        $response->assertSee('Mtro. Fernando Canul');
        $response->assertSee('La fotografía del hallazgo está borrosa; favor de adjuntar una toma nítida.');
    }

    /**
     * Criterio: Evento de cancelación con tipo, fecha/hora, responsable y motivo.
     */
    public function test_evento_cancelacion_muestra_tipo_fecha_hora_responsable_y_motivo(): void
    {
        [$autor, $admin] = $this->crearAutorYAdmin();
        $fechaCanc = Carbon::create(2026, 11, 4, 17, 20, 0, Publicacion::TIMEZONE);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261104-0030',
            'estado' => Publicacion::ESTADO_CANCELADO,
            'disponible' => false,
            'motivo_cancelacion' => 'El objeto fue reportado por duplicado con el folio OBJ-20261104-0001.',
            'fecha_cancelacion' => $fechaCanc,
        ]);

        DecisionModeracion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_CANCELADA,
            'motivo' => 'Cancelación administrativa por duplicidad de reporte.',
            'fecha_decision' => $fechaCanc,
        ]);

        $response = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $response->assertOk();

        $response->assertSee('Cancelación');
        $response->assertSee('04/11/2026 • 05:20 p. m.');
        $response->assertSee('Mtro. Fernando Canul');
        $response->assertSee('Cancelación administrativa por duplicidad de reporte.');
    }

    /**
     * Criterio: Evento de vencimiento con tipo, fecha/hora, responsable y motivo.
     */
    public function test_evento_vencimiento_muestra_tipo_fecha_hora_responsable_y_observacion(): void
    {
        [$autor, $admin] = $this->crearAutorYAdmin();
        $fechaVenc = Carbon::create(2026, 11, 5, 23, 59, 59, Publicacion::TIMEZONE);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261105-0040',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'fecha_vencimiento' => $fechaVenc,
        ]);

        $response = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $response->assertOk();

        $response->assertSee('Vencimiento');
        $response->assertSee('05/11/2026');
        $response->assertSee('Sistema institucional');
        $response->assertSee('Término del periodo ordinario de vigencia de 14 días naturales en el muro institucional.');
    }

    /**
     * Criterio: Evento de recepción en biblioteca con tipo, fecha/hora, responsable y observación.
     */
    public function test_evento_recepcion_muestra_tipo_fecha_hora_responsable_y_observacion(): void
    {
        [$autor, $admin] = $this->crearAutorYAdmin();
        $fechaRecep = Carbon::create(2026, 11, 4, 13, 15, 0, Publicacion::TIMEZONE);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261104-0050',
            'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO,
            'updated_at' => $fechaRecep,
        ]);

        $response = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $response->assertOk();

        $response->assertSee('Recepción en biblioteca');
        $response->assertSee('Personal de biblioteca');
        $response->assertSee('Objeto entregado físicamente en biblioteca bajo resguardo institucional confirmado.');
    }

    /**
     * Criterio: Evento de devolución con datos del receptor y comprobación de propiedad.
     */
    public function test_evento_devolucion_muestra_tipo_fecha_hora_responsable_receptor_y_comprobacion(): void
    {
        [$autor, $admin] = $this->crearAutorYAdmin();
        $fechaEntrega = Carbon::create(2026, 11, 5, 10, 0, 0, Publicacion::TIMEZONE);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261105-0060',
            'estado' => Publicacion::ESTADO_DEVUELTO,
        ]);

        Storage::fake('local');
        $foto = UploadedFile::fake()->image('evidencia.jpg', 400, 300);
        $ruta = $foto->storeAs('devoluciones/' . $publicacion->id, 'evidencia.jpg', 'local');

        Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Ana Sofía Baeza',
            'receptor_identificador' => '20080111',
            'fecha_entrega' => $fechaEntrega->format('Y-m-d'),
            'hora_entrega' => '10:00:00',
            'fecha_captura' => $fechaEntrega,
            'explicacion_propiedad' => 'Desbloqueó el teléfono frente al autor e indicó el fondo de pantalla exacto.',
            'fotografia_ruta' => $ruta,
            'estado' => Devolucion::ESTADO_VERIFICADA,
        ]);

        $response = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $response->assertOk();

        $response->assertSee('Devolución');
        $response->assertSee('05/11/2026 • 10:00 a. m.');
        $response->assertSee('Carla Mendoza Pech');
        $response->assertSee('Ana Sofía Baeza');
        $response->assertSee('20080111');
        $response->assertSee('Desbloqueó el teléfono frente al autor');
    }

    /**
     * Criterio: Evento de incidencia con responsable y motivo u observación.
     */
    public function test_evento_incidencia_muestra_tipo_fecha_hora_responsable_y_observacion(): void
    {
        [$autor, $admin] = $this->crearAutorYAdmin();
        $fechaInc = Carbon::create(2026, 11, 5, 16, 45, 0, Publicacion::TIMEZONE);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261105-0070',
            'incidencia_abierta' => true,
            'tiene_incidencia' => true,
            'estado_devolucion' => 'En incidencia',
            'updated_at' => $fechaInc,
        ]);

        $response = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $response->assertOk();

        $response->assertSee('Incidencia');
        $response->assertSee('Personal de biblioteca / Administración');
        $response->assertSee('Incidencia abierta: entrega física o resguardo bajo aclaración e investigación institucional.');
    }

    /**
     * Criterio: Independencia del muro y persistencia de antecedentes.
     * Mantener visibles los antecedentes aunque el reporte esté expirado, cancelado o retirado del muro.
     */
    public function test_antecedentes_permanecen_visibles_cuando_el_reporte_esta_expirado_cancelado_o_devuelto(): void
    {
        [$autor, $admin] = $this->crearAutorYAdmin();
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        // Publicación vencida, cancelada o devuelta
        $fechaCreacion = $ahora->copy()->subDays(20);
        $fechaAprobacion = $ahora->copy()->subDays(18);
        $fechaVencimiento = $ahora->copy()->subDays(4);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-9999',
            'nombre' => 'Mochila Totto Negra',
            'estado' => Publicacion::ESTADO_CANCELADO,
            'disponible' => false,
            'fecha_captura' => $fechaCreacion,
            'fecha_primera_aprobacion' => $fechaAprobacion,
            'fecha_vencimiento' => $fechaVencimiento,
            'fecha_cancelacion' => $ahora->copy()->subDays(2),
            'motivo_cancelacion' => 'El autor cerró el reporte tras recuperar el objeto.',
        ]);

        DecisionModeracion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_APROBADA,
            'motivo' => 'Publicación validada para muro.',
            'fecha_decision' => $fechaAprobacion,
        ]);

        // Consulta por el autor
        $resAutor = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $resAutor->assertOk();
        $resAutor->assertSee('Creación');
        $resAutor->assertSee('Aprobación');
        $resAutor->assertSee('Vencimiento');
        $resAutor->assertSee('Cancelación');
        $resAutor->assertSee('El autor cerró el reporte tras recuperar el objeto.');

        // Consulta por el administrador
        $resAdmin = $this->actingAs($admin)->get(route('reportes.historial', $publicacion->id));
        $resAdmin->assertOk();
        $resAdmin->assertSee('Creación');
        $resAdmin->assertSee('Aprobación');
        $resAdmin->assertSee('Vencimiento');
        $resAdmin->assertSee('Cancelación');
    }

    /**
     * Criterio: Devoluciones diferentes como eventos independientes.
     */
    public function test_conserva_devoluciones_diferentes_como_eventos_independientes(): void
    {
        [$autor, $admin] = $this->crearAutorYAdmin();
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-8888',
            'nombre' => 'Audífonos Sony WH-1000XM4',
            'estado' => Publicacion::ESTADO_DEVUELTO,
        ]);

        Storage::fake('local');
        $foto1 = UploadedFile::fake()->image('evidencia1.jpg', 400, 300);
        $ruta1 = $foto1->storeAs('devoluciones/' . $publicacion->id, 'evidencia1.jpg', 'local');

        $foto2 = UploadedFile::fake()->image('evidencia2.jpg', 400, 300);
        $ruta2 = $foto2->storeAs('devoluciones/' . $publicacion->id, 'evidencia2.jpg', 'local');

        // Primera entrega incorrecta
        $fechaEntrega1 = $ahora->copy()->subDays(5)->setTime(10, 0, 0);
        Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Carlos Pech Chan',
            'receptor_identificador' => '17080111',
            'fecha_entrega' => $fechaEntrega1->format('Y-m-d'),
            'hora_entrega' => '10:00:00',
            'fecha_captura' => $fechaEntrega1,
            'explicacion_propiedad' => 'Primera entrega realizada por confusión de nombre.',
            'fotografia_ruta' => $ruta1,
            'estado' => Devolucion::ESTADO_VERIFICADA,
        ]);

        // Segunda entrega al dueño legítimo tras recuperar el objeto
        $fechaEntrega2 = $ahora->copy()->subDays(1)->setTime(15, 0, 0);
        Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Rodrigo Morales Cetina',
            'receptor_identificador' => '17080999',
            'fecha_entrega' => $fechaEntrega2->format('Y-m-d'),
            'hora_entrega' => '15:00:00',
            'fecha_captura' => $fechaEntrega2,
            'explicacion_propiedad' => 'Entrega definitiva con factura y número de serie coincidentes.',
            'fotografia_ruta' => $ruta2,
            'estado' => Devolucion::ESTADO_VERIFICADA,
        ]);

        $response = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $response->assertOk();

        // Ambos receptores y eventos aparecen en el historial
        $response->assertSee('Carlos Pech Chan');
        $response->assertSee('Primera entrega realizada por confusión de nombre.');
        $response->assertSee('Rodrigo Morales Cetina');
        $response->assertSee('Entrega definitiva con factura y número de serie coincidentes.');

        // Verificar orden cronológico ascendente entre ambas entregas
        $content = $response->getContent();
        $posEntrega1 = strpos($content, 'Carlos Pech Chan');
        $posEntrega2 = strpos($content, 'Rodrigo Morales Cetina');

        $this->assertNotFalse($posEntrega1);
        $this->assertNotFalse($posEntrega2);
        $this->assertTrue($posEntrega1 < $posEntrega2, 'La primera devolución debe aparecer antes que la segunda');
    }
}
