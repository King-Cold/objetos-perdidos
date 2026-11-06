<?php

namespace Tests\Feature;

use App\Models\DecisionModeracion;
use App\Models\Devolucion;
use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HistorialProteccionConservacionTask4Test extends TestCase
{
    use RefreshDatabase;

    private function crearEscenarioUsuarios(): array
    {
        $autor = User::factory()->create([
            'name' => 'Sofía Cámara Solís',
            'email' => 'sofia.camara@campus.tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $admin = User::factory()->create([
            'name' => 'Lic. Gabriela Poot',
            'email' => 'gabriela.poot@biblioteca.tecnm.mx',
            'role' => User::ROLE_ADMIN,
        ]);

        $otroUsuario = User::factory()->create([
            'name' => 'Emilio Salazar',
            'email' => 'emilio.salazar@campus.tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $receptor = User::factory()->create([
            'name' => 'Mauricio Mena',
            'email' => 'mauricio.mena@campus.tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        return [$autor, $admin, $otroUsuario, $receptor];
    }

    /**
     * Criterio: Entregas realizadas por biblioteca y autorización al visualizar/descargar evidencias.
     * Cuando biblioteca registre una devolución, tanto el administrador/bibliotecario como el autor original
     * deberán poder consultar sus datos y evidencia. La persona receptora y otros usuarios no obtienen acceso.
     */
    public function test_autorizacion_al_visualizar_o_descargar_evidencias_incluida_entrega_por_biblioteca(): void
    {
        Storage::fake('local');
        [$autor, $admin, $otroUsuario, $receptor] = $this->crearEscenarioUsuarios();
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-4001',
            'nombre' => 'Disco Duro Externo Seagate 2TB',
            'estado' => Publicacion::ESTADO_DEVUELTO,
            'disponible' => false,
            'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO,
        ]);

        $archivoEvidencia = UploadedFile::fake()->create('evidencia_biblioteca.jpg', 140, 'image/jpeg');
        $rutaEvidencia = $archivoEvidencia->storeAs('devoluciones/' . $publicacion->id, 'evidencia_biblioteca.jpg', 'local');

        // Devolución registrada directamente por biblioteca (user_id = $admin->id)
        $devolucionBiblioteca = Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $admin->id,
            'admin_id' => $admin->id,
            'receptor_nombre' => 'Mauricio Mena',
            'receptor_identificador' => '19080777',
            'fecha_entrega' => $ahora->format('Y-m-d'),
            'hora_entrega' => '11:30:00',
            'fecha_captura' => $ahora,
            'explicacion_propiedad' => 'Entrega realizada en el módulo de biblioteca tras verificar archivos del propietario.',
            'fotografia_ruta' => $rutaEvidencia,
            'fotografia_nombre_original' => 'evidencia_biblioteca.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 143360,
            'estado' => Devolucion::ESTADO_VERIFICADA,
        ]);

        // 1. El autor original puede consultar el historial, el detalle de la entrega por biblioteca y su evidencia
        $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id))
            ->assertOk()
            ->assertSee('Mauricio Mena')
            ->assertSee('evidencia_biblioteca.jpg');

        $this->actingAs($autor)->get(route('devoluciones.show', $devolucionBiblioteca->id))->assertOk();
        $this->actingAs($autor)->get(route('devoluciones.evidencia', $devolucionBiblioteca->id))->assertOk();

        // 2. El administrador/bibliotecario puede consultar el historial, el detalle y su evidencia
        $this->actingAs($admin)->get(route('admin.reportes.historial', $publicacion->id))
            ->assertOk()
            ->assertSee('Mauricio Mena')
            ->assertSee('evidencia_biblioteca.jpg');

        $this->actingAs($admin)->get(route('devoluciones.evidencia', $devolucionBiblioteca->id))->assertOk();

        // 3. El receptor del objeto (Mauricio Mena) NO obtiene acceso por el hecho de haber recibido el objeto
        $this->actingAs($receptor)->get(route('reportes.historial', $publicacion->id))->assertForbidden();
        $this->actingAs($receptor)->get(route('devoluciones.show', $devolucionBiblioteca->id))->assertForbidden();
        $this->actingAs($receptor)->get(route('devoluciones.evidencia', $devolucionBiblioteca->id))->assertForbidden();

        // 4. Otro usuario general tampoco tiene acceso
        $this->actingAs($otroUsuario)->get(route('reportes.historial', $publicacion->id))->assertForbidden();
        $this->actingAs($otroUsuario)->get(route('devoluciones.evidencia', $devolucionBiblioteca->id))->assertForbidden();

        // 5. Visitante no autenticado es redirigido al login
        auth()->logout();
        $this->get(route('devoluciones.evidencia', $devolucionBiblioteca->id))->assertRedirect(route('login'));
    }

    /**
     * Criterio: Conservación sin vencimiento automático.
     * El historial y la última evidencia de cada devolución deberán conservarse sin eliminación automática
     * tras 14 días (vencimiento de publicación), 90 días o el término de un año de resguardo (365+ días).
     */
    public function test_conserva_historial_y_ultima_evidencia_sin_eliminacion_automatica_tras_14_90_y_365_dias(): void
    {
        Storage::fake('local');
        [$autor, $admin] = $this->crearEscenarioUsuarios();
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        // Escenarios: 15 días (>14 días), 95 días (>90 días), y 400 días (>1 año / 365 días)
        foreach ([15, 95, 400] as $diasTranscurridos) {
            $fechaAntigua = $ahora->copy()->subDays($diasTranscurridos);

            $publicacion = Publicacion::factory()->create([
                'user_id' => $autor->id,
                'folio' => "OBJ-ANTIGUO-{$diasTranscurridos}",
                'nombre' => "Objeto Conservado {$diasTranscurridos} Días",
                'estado' => Publicacion::ESTADO_DEVUELTO,
                'disponible' => false,
                'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO,
                'fecha_captura' => $fechaAntigua->copy()->subDays(2),
                'fecha_primera_aprobacion' => $fechaAntigua->copy()->subDay(),
                'fecha_vencimiento' => $fechaAntigua->copy()->addDays(13), // Ya vencido
                'created_at' => $fechaAntigua->copy()->subDays(2),
            ]);

            $foto = UploadedFile::fake()->create("evidencia_{$diasTranscurridos}.jpg", 100, 'image/jpeg');
            $ruta = $foto->storeAs('devoluciones/' . $publicacion->id, "evidencia_{$diasTranscurridos}.jpg", 'local');

            $devolucion = Devolucion::create([
                'publicacion_id' => $publicacion->id,
                'user_id' => $autor->id,
                'admin_id' => $admin->id,
                'receptor_nombre' => "Receptor {$diasTranscurridos} Días",
                'receptor_identificador' => "ID-{$diasTranscurridos}",
                'fecha_entrega' => $fechaAntigua->format('Y-m-d'),
                'hora_entrega' => '10:00:00',
                'fecha_captura' => $fechaAntigua,
                'explicacion_propiedad' => "Comprobación conservada por {$diasTranscurridos} días.",
                'fotografia_ruta' => $ruta,
                'fotografia_nombre_original' => "evidencia_{$diasTranscurridos}.jpg",
                'fotografia_mime_type' => 'image/jpeg',
                'fotografia_tamano_bytes' => 102400,
                'estado' => Devolucion::ESTADO_VERIFICADA,
            ]);

            // Tanto autor como administrador consultan el historial y la evidencia sin que se eliminen
            $resAutor = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
            $resAutor->assertOk();
            $resAutor->assertSee("OBJ-ANTIGUO-{$diasTranscurridos}");
            $resAutor->assertSee("Receptor {$diasTranscurridos} Días");
            $resAutor->assertSee("evidencia_{$diasTranscurridos}.jpg");

            $resEvidencia = $this->actingAs($admin)->get(route('devoluciones.evidencia', $devolucion->id));
            $resEvidencia->assertOk();

            $this->assertTrue(Storage::disk('local')->exists($ruta));
            $this->assertDatabaseHas('publicaciones', ['id' => $publicacion->id]);
            $this->assertDatabaseHas('devoluciones', ['id' => $devolucion->id]);
        }
    }

    /**
     * Criterio: Conservación de registros relacionados.
     * El perfil del autor («Mis reportes») no deberá ofrecer una opción para eliminar reportes que tengan
     * devoluciones, resguardo o incidencias, e impedirá borrarlos si se invoca la ruta directamente.
     * La cancelación de una publicación deberá conservar estos antecedentes.
     */
    public function test_impide_mostrar_opcion_y_borrar_reportes_con_devoluciones_resguardo_o_incidencias(): void
    {
        Storage::fake('local');
        [$autor, $admin] = $this->crearEscenarioUsuarios();
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        // 1. Reporte cancelado pero que tiene un registro de devolución en la relación devoluciones
        $repConDevolucion = Publicacion::factory()->cancelado()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-CON-DEV-01',
            'nombre' => 'Paraguas con Antecedente de Devolución',
            'devolucion_en_proceso' => false,
            'estado_devolucion' => null,
        ]);

        Devolucion::create([
            'publicacion_id' => $repConDevolucion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Receptor Registrado',
            'receptor_identificador' => '20080111',
            'fecha_entrega' => $ahora->format('Y-m-d'),
            'hora_entrega' => '10:00:00',
            'fecha_captura' => $ahora,
            'explicacion_propiedad' => 'Mostró credencial.',
            'fotografia_ruta' => 'devoluciones/1/ev.jpg',
            'estado' => Devolucion::ESTADO_PENDIENTE,
        ]);

        // 2. Reporte cancelado con resguardo físico en biblioteca
        $repConResguardo = Publicacion::factory()->cancelado()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-CON-RESG-02',
            'nombre' => 'Chamarra en Resguardo Biblioteca',
            'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO,
        ]);

        // 3. Reporte cancelado con antecedente de incidencia en decisiones
        $repConIncidencia = Publicacion::factory()->cancelado()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-CON-INC-03',
            'nombre' => 'Lentes con Incidencia Histórica',
            'tiene_incidencia' => false,
            'incidencia_abierta' => false,
        ]);

        DecisionModeracion::create([
            'publicacion_id' => $repConIncidencia->id,
            'user_id' => $admin->id,
            'tipo_decision' => 'incidencia_registrada',
            'motivo' => 'Disputa sobre propiedad registrada.',
            'fecha_decision' => $ahora,
        ]);

        // En «Mis reportes» NO debe aparecer el botón «Eliminar permanentemente» para ninguno de los tres
        $resMisReportes = $this->actingAs($autor)->get(route('reportes.mis-reportes'));
        $resMisReportes->assertOk();
        $resMisReportes->assertSee('OBJ-CON-DEV-01');
        $resMisReportes->assertSee('OBJ-CON-RESG-02');
        $resMisReportes->assertSee('OBJ-CON-INC-03');
        $resMisReportes->assertDontSee('Eliminar permanentemente');

        // Si el autor intenta borrarlos directamente mediante petición DELETE/POST, es rechazado y se conservan
        foreach ([$repConDevolucion, $repConResguardo, $repConIncidencia] as $repProtegido) {
            $resDelete = $this->actingAs($autor)->delete(route('reportes.destroy', $repProtegido->id));
            $resDelete->assertRedirect(route('reportes.mis-reportes'));
            $resDelete->assertSessionHas('warning');

            $this->assertDatabaseHas('publicaciones', ['id' => $repProtegido->id]);
        }
    }

    /**
     * Criterio: Archivos faltantes y consulta sin cambios.
     * Mostrar «No fue posible cargar la evidencia» si un archivo falta, sin sustituirlo por archivos de otro objeto.
     */
    public function test_muestra_no_fue_posible_cargar_la_evidencia_si_falta_archivo_sin_sustituirlo_por_otro(): void
    {
        Storage::fake('local');
        [$autor, $admin] = $this->crearEscenarioUsuarios();
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        // Otro objeto con su propia fotografía existente
        $otraPublicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-OTRO-9999',
        ]);
        $fotoOtro = UploadedFile::fake()->create('evidencia_de_otro_objeto.jpg', 120, 'image/jpeg');
        $rutaOtro = $fotoOtro->storeAs('devoluciones/' . $otraPublicacion->id, 'evidencia_de_otro_objeto.jpg', 'local');

        $devOtro = Devolucion::create([
            'publicacion_id' => $otraPublicacion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Receptor Otro Objeto',
            'receptor_identificador' => '11112222',
            'fecha_entrega' => $ahora->format('Y-m-d'),
            'hora_entrega' => '09:00:00',
            'fecha_captura' => $ahora,
            'explicacion_propiedad' => 'Evidencia del otro objeto.',
            'fotografia_ruta' => $rutaOtro,
            'fotografia_nombre_original' => 'evidencia_de_otro_objeto.jpg',
            'estado' => Devolucion::ESTADO_VERIFICADA,
        ]);

        // Objeto principal cuya evidencia no existe en el almacenamiento
        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-FALTA-EVIDENCIA',
            'nombre' => 'Billetera de Piel Café',
            'estado' => Publicacion::ESTADO_DEVUELTO,
            'disponible' => false,
        ]);

        $devFaltante = Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Luis Fernando Uc',
            'receptor_identificador' => '20080999',
            'fecha_entrega' => $ahora->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'fecha_captura' => $ahora,
            'explicacion_propiedad' => 'Mencionó el contenido exacto de la billetera.',
            'fotografia_ruta' => 'devoluciones/' . $publicacion->id . '/no_existe_en_disco.jpg',
            'fotografia_nombre_original' => 'no_existe_en_disco.jpg',
            'estado' => Devolucion::ESTADO_VERIFICADA,
        ]);

        $resHistorial = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $resHistorial->assertOk();
        $resHistorial->assertSee('No fue posible cargar la evidencia');

        // No debe sustituir la imagen faltante con el enlace o archivo del otro objeto
        $resHistorial->assertDontSee('evidencia_de_otro_objeto.jpg');
        $resHistorial->assertDontSee(route('devoluciones.evidencia', $devOtro->id));

        // El endpoint directo de descarga de la evidencia faltante devuelve 404 (no devuelve otro archivo)
        $this->actingAs($autor)->get(route('devoluciones.evidencia', $devFaltante->id))->assertNotFound();
    }
}

