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

class HistorialDetalleDevolucionesTask3Test extends TestCase
{
    use RefreshDatabase;

    private function crearUsuariosBase(): array
    {
        $autor = User::factory()->create([
            'name' => 'Valeria Rosado Tun',
            'email' => 'valeria.rosado@campus.tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $admin = User::factory()->create([
            'name' => 'Lic. Ricardo Balam',
            'email' => 'ricardo.balam@biblioteca.tecnm.mx',
            'role' => User::ROLE_ADMIN,
        ]);

        return [$autor, $admin];
    }

    /**
     * Criterio: Consulta de devoluciones.
     * Cada evento de devolución deberá permitir consultar los datos vigentes del receptor,
     * la fecha y hora de entrega, la explicación de la comprobación de propiedad,
     * la última fotografía y el resultado de su revisión.
     */
    public function test_evento_devolucion_permite_consultar_detalle_completo_y_evidencia_vigente(): void
    {
        Storage::fake('local');
        [$autor, $admin] = $this->crearUsuariosBase();
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-3001',
            'nombre' => 'Tablet Samsung Galaxy Tab S8',
            'estado' => Publicacion::ESTADO_DEVUELTO,
            'disponible' => false,
        ]);

        $fotoEvidencia = UploadedFile::fake()->create('evidencia_vigente_tab.jpg', 150, 'image/jpeg');
        $rutaEvidencia = $fotoEvidencia->storeAs('devoluciones/' . $publicacion->id, 'evidencia_vigente_tab.jpg', 'local');

        $devolucion = Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'admin_id' => $admin->id,
            'receptor_nombre' => 'Mariana Cetina Aguilar',
            'receptor_identificador' => '21080456',
            'fecha_entrega' => '2026-11-04',
            'hora_entrega' => '12:45:00',
            'fecha_captura' => $ahora->copy()->subDays(2),
            'explicacion_propiedad' => 'Desbloqueó la tablet con huella digital y mostró el S-Pen con sus iniciales.',
            'fotografia_ruta' => $rutaEvidencia,
            'fotografia_nombre_original' => 'evidencia_vigente_tab.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 153600,
            'estado' => Devolucion::ESTADO_VERIFICADA,
        ]);

        // 1. Consulta desde la vista de historial (autor y admin)
        foreach ([$autor, $admin] as $usuario) {
            $response = $this->actingAs($usuario)->get(route('reportes.historial', $publicacion->id));
            $response->assertOk();

            // Datos vigentes del receptor
            $response->assertSee('Datos vigentes del receptor');
            $response->assertSee('Mariana Cetina Aguilar');
            $response->assertSee('21080456');

            // Momento físico (fecha y hora de entrega)
            $response->assertSee('Fecha y hora de entrega física');
            $response->assertSee('04/11/2026 • 12:45 p. m.');

            // Explicación de comprobación de propiedad
            $response->assertSee('Explicación de la comprobación de propiedad');
            $response->assertSee('Desbloqueó la tablet con huella digital y mostró el S-Pen con sus iniciales.');

            // Última fotografía y enlace operativo a la evidencia vigente
            $response->assertSee('Última fotografía de evidencia');
            $response->assertSee('evidencia_vigente_tab.jpg');
            $response->assertSee(route('devoluciones.evidencia', $devolucion->id));

            // Resultado de revisión
            $response->assertSee('Resultado de su revisión');
            $response->assertSee('Verificada');
            $response->assertSee('Lic. Ricardo Balam');
        }

        // 2. El enlace de la fotografía vigente responde 200 OK para autor y admin
        $this->actingAs($autor)->get(route('devoluciones.evidencia', $devolucion->id))->assertOk();
        $this->actingAs($admin)->get(route('devoluciones.evidencia', $devolucion->id))->assertOk();
    }

    /**
     * Criterio: Corrección y sustitución de evidencia.
     * Si se corrige una devolución, deberán mostrarse únicamente sus datos y fotografía más recientes.
     * Las imágenes sustituidas no deberán aparecer como versiones anteriores ni permanecer disponibles.
     * Se conservarán las decisiones de revisión con sus fechas, responsables y motivos.
     */
    public function test_correccion_muestra_solo_datos_y_evidencia_mas_recientes_sin_versiones_sustituidas(): void
    {
        Storage::fake('local');
        [$autor, $admin] = $this->crearUsuariosBase();
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-3002',
            'nombre' => 'Reloj Inteligente Garmin',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'devolucion_en_proceso' => true,
            'estado_devolucion' => Devolucion::ESTADO_REQUIERE_CORRECCION,
            'fecha_hallazgo' => $ahora->copy()->subDays(6)->format('Y-m-d'),
            'hora_hallazgo' => '09:00',
        ]);

        // Imagen original que será sustituida
        $fotoAntigua = UploadedFile::fake()->create('foto_antigua_borrosa.jpg', 100, 'image/jpeg');
        $rutaAntigua = $fotoAntigua->storeAs('devoluciones/' . $publicacion->id, 'foto_antigua_borrosa.jpg', 'local');

        $devolucion = Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'admin_id' => $admin->id,
            'receptor_nombre' => 'Nombre Antiguo Incorrecto',
            'receptor_identificador' => '00000111',
            'fecha_entrega' => $ahora->copy()->subDays(3)->format('Y-m-d'),
            'hora_entrega' => '10:00',
            'fecha_captura' => $ahora->copy()->subDays(3),
            'explicacion_propiedad' => 'Explicación antigua incompleta.',
            'motivo_correccion' => 'La fotografía está borrosa y falta el apellido completo del receptor.',
            'fotografia_ruta' => $rutaAntigua,
            'fotografia_nombre_original' => 'foto_antigua_borrosa.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 102400,
            'estado' => Devolucion::ESTADO_REQUIERE_CORRECCION,
        ]);

        // Decisión administrativa previa de solicitud de corrección
        DecisionModeracion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_DEVOLUCION_CORRECCION,
            'motivo' => 'La fotografía está borrosa y falta el apellido completo del receptor.',
            'fecha_decision' => $ahora->copy()->subDays(2),
        ]);

        // El autor corrige la devolución y reemplaza la fotografía por una nueva
        $fotoNueva = UploadedFile::fake()->create('foto_nueva_nitida.jpg', 180, 'image/jpeg');

        $resUpdate = $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => 'Jorge Alberto Méndez Tuz',
            'receptor_identificador' => '19081234',
            'fecha_entrega' => $ahora->copy()->subDays(3)->format('Y-m-d'),
            'hora_entrega' => '11:30',
            'explicacion_propiedad' => 'Mostró la aplicación Garmin Connect sincronizada con el número de serie del reloj.',
            'fotografia' => $fotoNueva,
        ]);

        $resUpdate->assertRedirect(route('devoluciones.show', $devolucion->id));

        // Verificar que el archivo físico antiguo fue eliminado del almacenamiento y no permanece disponible
        $this->assertFalse(Storage::disk('local')->exists($rutaAntigua));

        // Consultar el historial
        $resHistorial = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $resHistorial->assertOk();

        // 1. Muestra únicamente los datos y fotografía más recientes
        $resHistorial->assertSee('Jorge Alberto Méndez Tuz');
        $resHistorial->assertSee('19081234');
        $resHistorial->assertSee('Mostró la aplicación Garmin Connect sincronizada con el número de serie del reloj.');
        $resHistorial->assertSee('foto_nueva_nitida.jpg');

        // 2. NO muestra los datos ni la fotografía reemplazados
        $resHistorial->assertDontSee('Nombre Antiguo Incorrecto');
        $resHistorial->assertDontSee('00000111');
        $resHistorial->assertDontSee('Explicación antigua incompleta.');
        $resHistorial->assertDontSee('foto_antigua_borrosa.jpg');

        // 3. Conserva la decisión de revisión en el historial con su responsable y motivo
        $resHistorial->assertSee('Corrección de devolución');
        $resHistorial->assertSee('Lic. Ricardo Balam');
        $resHistorial->assertSee('La fotografía está borrosa y falta el apellido completo del receptor.');
    }

    /**
     * Criterio: Devoluciones diferentes.
     * Si un objeto se entregó incorrectamente, fue recuperado y después se devolvió a otra persona,
     * ambas entregas deberán aparecer como eventos distintos con su propia evidencia y enlace de detalle.
     */
    public function test_entregas_distintas_se_mantienen_independientes_con_sus_propios_datos_y_evidencias(): void
    {
        Storage::fake('local');
        [$autor, $admin] = $this->crearUsuariosBase();
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-3003',
            'nombre' => 'Calculadora Financiera HP 17bII+',
            'estado' => Publicacion::ESTADO_DEVUELTO,
            'disponible' => false,
        ]);

        $fotoEntrega1 = UploadedFile::fake()->create('evidencia_entrega_1.jpg', 110, 'image/jpeg');
        $ruta1 = $fotoEntrega1->storeAs('devoluciones/' . $publicacion->id, 'evidencia_entrega_1.jpg', 'local');

        $fotoEntrega2 = UploadedFile::fake()->create('evidencia_entrega_2.jpg', 140, 'image/jpeg');
        $ruta2 = $fotoEntrega2->storeAs('devoluciones/' . $publicacion->id, 'evidencia_entrega_2.jpg', 'local');

        // Primera entrega (entrega incorrecta antes de incidencia y recuperación)
        $dev1 = Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'admin_id' => $admin->id,
            'receptor_nombre' => 'Receptor Inicial Equivocado',
            'receptor_identificador' => '18080001',
            'fecha_entrega' => $ahora->copy()->subDays(5)->format('Y-m-d'),
            'hora_entrega' => '09:15:00',
            'fecha_captura' => $ahora->copy()->subDays(5),
            'explicacion_propiedad' => 'Dijo haber perdido una calculadora similar en el edificio D.',
            'fotografia_ruta' => $ruta1,
            'fotografia_nombre_original' => 'evidencia_entrega_1.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 112640,
            'estado' => Devolucion::ESTADO_VERIFICADA,
        ]);

        // Antecedente de incidencia tras la primera entrega
        DecisionModeracion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $admin->id,
            'tipo_decision' => 'incidencia_registrada',
            'motivo' => 'El receptor inicial devolvió el objeto a biblioteca tras notar que no era su modelo; se recuperó el artículo.',
            'fecha_decision' => $ahora->copy()->subDays(3),
        ]);

        // Segunda entrega (al propietario legítimo)
        $dev2 = Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'admin_id' => $admin->id,
            'receptor_nombre' => 'Propietario Legítimo Final',
            'receptor_identificador' => '18089999',
            'fecha_entrega' => $ahora->copy()->subDays(1)->format('Y-m-d'),
            'hora_entrega' => '16:20:00',
            'fecha_captura' => $ahora->copy()->subDays(1),
            'explicacion_propiedad' => 'Presentó nota de compra con el número de serie grabado en el compartimento de baterías.',
            'fotografia_ruta' => $ruta2,
            'fotografia_nombre_original' => 'evidencia_entrega_2.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 143360,
            'estado' => Devolucion::ESTADO_VERIFICADA,
        ]);

        $response = $this->actingAs($admin)->get(route('reportes.historial', $publicacion->id));
        $response->assertOk();

        // Verifica que ambas devoluciones y el antecedente de la incidencia aparecen en orden cronológico
        $response->assertSeeInOrder([
            'Receptor Inicial Equivocado',
            '18080001',
            'evidencia_entrega_1.jpg',
            'Incidencia',
            'El receptor inicial devolvió el objeto a biblioteca tras notar que no era su modelo; se recuperó el artículo.',
            'Propietario Legítimo Final',
            '18089999',
            'evidencia_entrega_2.jpg',
        ]);

        // Cada evento conserva su propio enlace al expediente y a su propia evidencia
        $response->assertSee(route('admin.devoluciones.show', $dev1->id));
        $response->assertSee(route('devoluciones.evidencia', $dev1->id));
        $response->assertSee(route('admin.devoluciones.show', $dev2->id));
        $response->assertSee(route('devoluciones.evidencia', $dev2->id));
    }

    /**
     * Criterio: Archivos faltantes y consulta sin cambios.
     * Si una evidencia no puede abrirse, deberá mostrarse «No fue posible cargar la evidencia»,
     * sin sustituirla por archivos de otro objeto ni modificar el resultado de la revisión.
     */
    public function test_muestra_mensaje_cuando_archivo_de_evidencia_no_esta_disponible_sin_alterar_revision(): void
    {
        Storage::fake('local');
        [$autor, $admin] = $this->crearUsuariosBase();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-3004',
            'nombre' => 'Termo Yeti Azul Marino',
            'estado' => Publicacion::ESTADO_DEVUELTO,
            'disponible' => false,
        ]);

        // Devolución cuyo archivo en disco no existe (faltante)
        $devolucion = Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'admin_id' => $admin->id,
            'receptor_nombre' => 'Andrea Noh',
            'receptor_identificador' => '22080333',
            'fecha_entrega' => '2026-11-05',
            'hora_entrega' => '14:00:00',
            'fecha_captura' => Carbon::now(Publicacion::TIMEZONE)->subDay(),
            'explicacion_propiedad' => 'Describió las calcomanías en la base del termo.',
            'fotografia_ruta' => 'devoluciones/' . $publicacion->id . '/archivo_inexistente.jpg',
            'fotografia_nombre_original' => 'archivo_inexistente.jpg',
            'estado' => Devolucion::ESTADO_VERIFICADA,
        ]);

        // 1. En el historial se muestra «No fue posible cargar la evidencia» y se conserva el resultado «Verificada»
        $resHistorial = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $resHistorial->assertOk();
        $resHistorial->assertSee('No fue posible cargar la evidencia');
        $resHistorial->assertSee('Verificada');

        // 2. En el detalle de devolución también se muestra «No fue posible cargar la evidencia»
        $resShowAutor = $this->actingAs($autor)->get(route('devoluciones.show', $devolucion->id));
        $resShowAutor->assertOk();
        $resShowAutor->assertSee('No fue posible cargar la evidencia');

        $resShowAdmin = $this->actingAs($admin)->get(route('admin.devoluciones.show', $devolucion->id));
        $resShowAdmin->assertOk();
        $resShowAdmin->assertSee('No fue posible cargar la evidencia');

        // 3. El estado de la devolución en base de datos permanece intacto
        $this->assertEquals(Devolucion::ESTADO_VERIFICADA, $devolucion->fresh()->estado);
    }
}

