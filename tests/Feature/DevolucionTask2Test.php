<?php

namespace Tests\Feature;

use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DevolucionTask2Test extends TestCase
{
    use RefreshDatabase;

    private function crearPublicacionElegible(array $attributes = []): array
    {
        $autor = User::factory()->create();

        $ahora = Carbon::now(Publicacion::TIMEZONE);
        $publicacion = Publicacion::factory()->create(array_merge([
            'user_id' => $autor->id,
            'nombre' => 'Laptop HP Gris',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_hallazgo' => $ahora->copy()->subDays(2)->format('Y-m-d'),
            'hora_hallazgo' => '10:00',
            'fecha_primera_aprobacion' => $ahora->copy()->subDays(2),
            'fecha_vencimiento' => $ahora->copy()->addDays(12),
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'devolucion_en_proceso' => false,
            'incidencia_abierta' => false,
            'tiene_incidencia' => false,
            'estado_devolucion' => null,
        ], $attributes));

        return [$autor, $publicacion];
    }

    /**
     * CA-03: Rechaza cuando los campos obligatorios están ausentes o son solo espacios.
     */
    public function test_rechaza_campos_obligatorios_vacios_o_compuestos_solo_por_espacios(): void
    {
        [$autor, $publicacion] = $this->crearPublicacionElegible();

        $response = $this->actingAs($autor)
            ->from(route('devoluciones.create', $publicacion->id))
            ->post(route('devoluciones.store', $publicacion->id), [
                'receptor_nombre' => '   ',
                'receptor_identificador' => '   ',
                'fecha_entrega' => '',
                'hora_entrega' => '   ',
                'explicacion_propiedad' => '    ',
            ]);

        $response->assertRedirect(route('devoluciones.create', $publicacion->id));
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
     * CA-04: Admite matrícula de estudiante o identificador institucional de docente sin restringirse a solo alumnos.
     */
    public function test_admite_matricula_estudiante_e_identificador_docente(): void
    {
        [$autor, $publicacion] = $this->crearPublicacionElegible();
        Storage::fake('local');

        // Prueba con matrícula numérica de estudiante
        $fotoValida = UploadedFile::fake()->image('evidencia_estudiante.jpg', 600, 400);
        $responseEstudiante = $this->actingAs($autor)->post(route('devoluciones.store', $publicacion->id), [
            'receptor_nombre' => 'Carlos López Sánchez',
            'receptor_identificador' => '19070045',
            'fecha_entrega' => Carbon::now(Publicacion::TIMEZONE)->subDays(1)->format('Y-m-d'),
            'hora_entrega' => '12:00',
            'explicacion_propiedad' => 'El alumno presentó su credencial vigente y comprobó el fondo de pantalla con su fotografía.',
            'fotografia' => $fotoValida,
        ]);

        $responseEstudiante->assertSessionDoesntHaveErrors(['receptor_identificador']);

        // Prueba con clave institucional de docente/empleado (letras, guiones y números)
        $fotoValidaDocente = UploadedFile::fake()->image('evidencia_docente.png', 600, 400);
        $responseDocente = $this->actingAs($autor)->post(route('devoluciones.store', $publicacion->id), [
            'receptor_nombre' => 'Dra. María Elena Pech',
            'receptor_identificador' => 'DOC-4892',
            'fecha_entrega' => Carbon::now(Publicacion::TIMEZONE)->subDays(1)->format('Y-m-d'),
            'hora_entrega' => '12:00',
            'explicacion_propiedad' => 'La docente presentó oficio de adscripción y acreditó la información de los archivos en la laptop.',
            'fotografia' => $fotoValidaDocente,
        ]);

        $responseDocente->assertSessionDoesntHaveErrors(['receptor_identificador']);
    }

    /**
     * CA-05: Rechaza fecha de entrega futura.
     */
    public function test_rechaza_fecha_entrega_futura(): void
    {
        [$autor, $publicacion] = $this->crearPublicacionElegible();
        Storage::fake('local');

        $fechaManana = Carbon::now(Publicacion::TIMEZONE)->addDay()->format('Y-m-d');

        $response = $this->actingAs($autor)->post(route('devoluciones.store', $publicacion->id), [
            'receptor_nombre' => 'Pedro Uc',
            'receptor_identificador' => '20070111',
            'fecha_entrega' => $fechaManana,
            'hora_entrega' => '10:00',
            'explicacion_propiedad' => 'Entrega acordada para mañana.',
            'fotografia' => UploadedFile::fake()->image('foto.jpg'),
        ]);

        $response->assertSessionHasErrors(['fecha_entrega']);
    }

    /**
     * CA-05: Rechaza hora de entrega futura cuando la fecha de entrega es el día de hoy.
     */
    public function test_rechaza_hora_entrega_futura_en_el_dia_actual(): void
    {
        [$autor, $publicacion] = $this->crearPublicacionElegible();
        Storage::fake('local');

        $hoy = Carbon::now(Publicacion::TIMEZONE);
        // Si ya estamos a final del día (23:59), saltamos para evitar overflow
        if ($hoy->hour >= 23) {
            $this->markTestSkipped('Hora actual muy cercana al final del día.');
        }

        $horaFutura = $hoy->copy()->addHour()->format('H:i');

        $response = $this->actingAs($autor)->post(route('devoluciones.store', $publicacion->id), [
            'receptor_nombre' => 'Pedro Uc',
            'receptor_identificador' => '20070111',
            'fecha_entrega' => $hoy->format('Y-m-d'),
            'hora_entrega' => $horaFutura,
            'explicacion_propiedad' => 'Entrega con hora futura hoy.',
            'fotografia' => UploadedFile::fake()->image('foto.jpg'),
        ]);

        $response->assertSessionHasErrors(['hora_entrega']);
    }

    /**
     * CA-05: Rechaza fecha de entrega anterior a la fecha de hallazgo.
     */
    public function test_rechaza_fecha_entrega_anterior_al_hallazgo(): void
    {
        $fechaHallazgo = Carbon::now(Publicacion::TIMEZONE)->subDays(3)->format('Y-m-d');
        [$autor, $publicacion] = $this->crearPublicacionElegible([
            'fecha_hallazgo' => $fechaHallazgo,
            'hora_hallazgo' => '12:00',
        ]);
        Storage::fake('local');

        $fechaAnterior = Carbon::now(Publicacion::TIMEZONE)->subDays(4)->format('Y-m-d');

        $response = $this->actingAs($autor)->post(route('devoluciones.store', $publicacion->id), [
            'receptor_nombre' => 'Pedro Uc',
            'receptor_identificador' => '20070111',
            'fecha_entrega' => $fechaAnterior,
            'hora_entrega' => '10:00',
            'explicacion_propiedad' => 'Explicación válida.',
            'fotografia' => UploadedFile::fake()->image('foto.jpg'),
        ]);

        $response->assertSessionHasErrors(['fecha_entrega']);
    }

    /**
     * CA-05: Respeta la hora de hallazgo si está registrada, rechazando una entrega anterior a esa hora en el mismo día.
     */
    public function test_rechaza_hora_entrega_anterior_a_hora_hallazgo_en_el_mismo_dia(): void
    {
        $fechaHallazgo = Carbon::now(Publicacion::TIMEZONE)->subDays(1)->format('Y-m-d');
        [$autor, $publicacion] = $this->crearPublicacionElegible([
            'fecha_hallazgo' => $fechaHallazgo,
            'hora_hallazgo' => '14:30',
        ]);
        Storage::fake('local');

        // Mismo día del hallazgo, pero a las 11:00 (anterior a las 14:30)
        $response = $this->actingAs($autor)->post(route('devoluciones.store', $publicacion->id), [
            'receptor_nombre' => 'Pedro Uc',
            'receptor_identificador' => '20070111',
            'fecha_entrega' => $fechaHallazgo,
            'hora_entrega' => '11:00',
            'explicacion_propiedad' => 'Explicación detallada de propiedad.',
            'fotografia' => UploadedFile::fake()->image('foto.jpg'),
        ]);

        $response->assertSessionHasErrors(['hora_entrega']);
    }

    /**
     * CA-05: Si el hallazgo no tiene hora registrada, no inventa hora y permite entregas a cualquier hora de esa fecha.
     */
    public function test_no_inventa_hora_si_hallazgo_no_tiene_hora_registrada(): void
    {
        $fechaHallazgo = Carbon::now(Publicacion::TIMEZONE)->subDays(1)->format('Y-m-d');
        [$autor, $publicacion] = $this->crearPublicacionElegible([
            'fecha_hallazgo' => $fechaHallazgo,
            'hora_hallazgo' => null,
        ]);
        Storage::fake('local');

        // Mismo día con hora temprana (07:00), debe admitirse sin error de hora
        $response = $this->actingAs($autor)->post(route('devoluciones.store', $publicacion->id), [
            'receptor_nombre' => 'Pedro Uc',
            'receptor_identificador' => '20070111',
            'fecha_entrega' => $fechaHallazgo,
            'hora_entrega' => '07:00',
            'explicacion_propiedad' => 'Explicación detallada de propiedad del objeto entregado.',
            'fotografia' => UploadedFile::fake()->image('foto.jpg'),
        ]);

        $response->assertSessionDoesntHaveErrors(['fecha_entrega', 'hora_entrega']);
    }

    /**
     * CA-06: Admite fotografías en formatos válidos JPG y PNG.
     */
    public function test_admite_fotografias_validas_jpg_y_png(): void
    {
        [$autor, $publicacion] = $this->crearPublicacionElegible();
        Storage::fake('local');

        // JPG válido
        $responseJpg = $this->actingAs($autor)->post(route('devoluciones.store', $publicacion->id), [
            'receptor_nombre' => 'Ana Morales',
            'receptor_identificador' => '21070500',
            'fecha_entrega' => Carbon::now(Publicacion::TIMEZONE)->format('Y-m-d'),
            'hora_entrega' => '09:00',
            'explicacion_propiedad' => 'Se verificó con credencial oficial y factura digital.',
            'fotografia' => UploadedFile::fake()->image('entrega.jpg', 800, 600),
        ]);
        $responseJpg->assertSessionDoesntHaveErrors(['fotografia']);

        // PNG válido
        $responsePng = $this->actingAs($autor)->post(route('devoluciones.store', $publicacion->id), [
            'receptor_nombre' => 'Ana Morales',
            'receptor_identificador' => '21070500',
            'fecha_entrega' => Carbon::now(Publicacion::TIMEZONE)->format('Y-m-d'),
            'hora_entrega' => '09:00',
            'explicacion_propiedad' => 'Se verificó con credencial oficial y factura digital.',
            'fotografia' => UploadedFile::fake()->image('entrega.png', 800, 600),
        ]);
        $responsePng->assertSessionDoesntHaveErrors(['fotografia']);
    }

    /**
     * CA-06: Rechaza archivos que no sean imágenes JPG o PNG (ej. PDF o texto).
     */
    public function test_rechaza_archivo_de_otro_formato_no_permitido(): void
    {
        [$autor, $publicacion] = $this->crearPublicacionElegible();
        Storage::fake('local');

        $archivoPdf = UploadedFile::fake()->create('documento.pdf', 500, 'application/pdf');

        $response = $this->actingAs($autor)->post(route('devoluciones.store', $publicacion->id), [
            'receptor_nombre' => 'Ana Morales',
            'receptor_identificador' => '21070500',
            'fecha_entrega' => Carbon::now(Publicacion::TIMEZONE)->format('Y-m-d'),
            'hora_entrega' => '09:00',
            'explicacion_propiedad' => 'Explicación suficiente.',
            'fotografia' => $archivoPdf,
        ]);

        $response->assertSessionHasErrors(['fotografia']);
    }

    /**
     * CA-06: Rechaza fotografía que supere los 2 MB (2048 KB).
     */
    public function test_rechaza_fotografia_que_supera_2_mb(): void
    {
        [$autor, $publicacion] = $this->crearPublicacionElegible();
        Storage::fake('local');

        // Archivo de imagen de 2.5 MB (2560 KB)
        $fotoPesada = UploadedFile::fake()->image('foto_pesada.jpg')->size(2560);

        $response = $this->actingAs($autor)->post(route('devoluciones.store', $publicacion->id), [
            'receptor_nombre' => 'Ana Morales',
            'receptor_identificador' => '21070500',
            'fecha_entrega' => Carbon::now(Publicacion::TIMEZONE)->format('Y-m-d'),
            'hora_entrega' => '09:00',
            'explicacion_propiedad' => 'Explicación suficiente.',
            'fotografia' => $fotoPesada,
        ]);

        $response->assertSessionHasErrors(['fotografia']);
    }

    /**
     * CA-10: Conserva los datos válidos previamente capturados ante un error de validación.
     */
    public function test_conserva_datos_validos_con_old_input_ante_errores(): void
    {
        [$autor, $publicacion] = $this->crearPublicacionElegible();
        Storage::fake('local');

        $response = $this->actingAs($autor)
            ->from(route('devoluciones.create', $publicacion->id))
            ->post(route('devoluciones.store', $publicacion->id), [
                'receptor_nombre' => 'Luis Fernando Canto',
                'receptor_identificador' => '18070999',
                'fecha_entrega' => Carbon::now(Publicacion::TIMEZONE)->addDay()->format('Y-m-d'), // Inválido (futura)
                'hora_entrega' => '10:00',
                'explicacion_propiedad' => 'Presentó el ticket de compra original y desbloqueó el teléfono.',
                'fotografia' => null, // Inválido (falta foto)
            ]);

        $response->assertRedirect(route('devoluciones.create', $publicacion->id));
        $response->assertSessionHasErrors(['fecha_entrega', 'fotografia']);

        // Comprobar que en la sesión persisten los inputs de texto válidos
        $this->assertEquals('Luis Fernando Canto', session('_old_input.receptor_nombre'));
        $this->assertEquals('18070999', session('_old_input.receptor_identificador'));
        $this->assertEquals('10:00', session('_old_input.hora_entrega'));
        $this->assertEquals('Presentó el ticket de compra original y desbloqueó el teléfono.', session('_old_input.explicacion_propiedad'));
    }
}

