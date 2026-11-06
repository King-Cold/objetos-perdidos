<?php

namespace Tests\Feature\Admin;

use App\Events\DevolucionReenviadaRevision;
use App\Models\DecisionModeracion;
use App\Models\Devolucion;
use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerificacionDevolucionTask123Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * Helper para preparar una publicación y devolución en estado 'Requiere corrección' con decisión registrada.
     */
    private function crearDevolucionEnCorreccionFixture(array $pubAttrs = [], array $devAttrs = []): array
    {
        $autor = User::factory()->create(['role' => User::ROLE_USUARIO_GENERAL]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $otroUsuario = User::factory()->create(['role' => User::ROLE_USUARIO_GENERAL]);

        $ahora = Carbon::now(Publicacion::TIMEZONE);

        $publicacion = Publicacion::factory()->create(array_merge([
            'user_id' => $autor->id,
            'nombre' => 'Calculadora Casio fx-991LAX',
            'descripcion' => 'Calculadora científica color negro encontrada en aula F-102',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'devolucion_en_proceso' => true,
            'estado_devolucion' => Devolucion::ESTADO_REQUIERE_CORRECCION,
            'fecha_hallazgo' => $ahora->copy()->subDays(6)->format('Y-m-d'),
            'hora_hallazgo' => '09:30',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'area_zona' => 'Edificio F',
        ], $pubAttrs));

        // Fotografía del hallazgo
        Fotografia::create([
            'publicacion_id' => $publicacion->id,
            'ruta' => 'fotografias/hallazgo_fixture_' . $publicacion->id . '.jpg',
            'nombre_original' => 'hallazgo.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 15000,
            'es_principal' => true,
        ]);

        // Fotografía inicial de evidencia privada
        $fotoEvidencia = UploadedFile::fake()->image('evidencia_original.jpg', 600, 400);
        $rutaEvidencia = $fotoEvidencia->storeAs('devoluciones/' . $publicacion->id, 'evidencia_original.jpg', 'local');

        $motivoInicial = 'La fotografía está muy oscura y no se aprecia con claridad la credencial institucional del receptor.';

        $devolucion = Devolucion::create(array_merge([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'admin_id' => $admin->id,
            'receptor_nombre' => 'Eduardo Pech Canul',
            'receptor_identificador' => '20080123',
            'fecha_entrega' => $ahora->copy()->subDays(3)->format('Y-m-d'),
            'hora_entrega' => '11:15',
            'fecha_captura' => $ahora->copy()->subDays(2),
            'fecha_envio_revision' => $ahora->copy()->subDays(2),
            'fecha_resolucion' => $ahora->copy()->subDays(1),
            'motivo_correccion' => $motivoInicial,
            'explicacion_propiedad' => 'El receptor presentó credencial y describió las marcas de uso del reverso.',
            'fotografia_ruta' => $rutaEvidencia,
            'fotografia_nombre_original' => 'evidencia_original.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 20480,
            'estado' => Devolucion::ESTADO_REQUIERE_CORRECCION,
        ], $devAttrs));

        // Registro de la decisión administrativa en el historial
        DecisionModeracion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_DEVOLUCION_CORRECCION,
            'motivo' => $motivoInicial,
            'fecha_decision' => $ahora->copy()->subDays(1),
        ]);

        return [$autor, $admin, $otroUsuario, $publicacion, $devolucion];
    }

    /**
     * 1. El autor consulta el motivo vigente en su apartado privado.
     */
    public function test_01_el_autor_consulta_el_motivo_vigente(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionEnCorreccionFixture();

        $response = $this->actingAs($autor)->get(route('devoluciones.show', $devolucion->id));

        $response->assertOk();
        $response->assertSee($publicacion->folio);
        $response->assertSee($publicacion->nombre);
        $response->assertSee('Requiere corrección');
        $response->assertSee($devolucion->motivo_correccion);
        $response->assertSee('Corregir devolución');
        $response->assertSee(route('devoluciones.edit', $devolucion->id));
    }

    /**
     * 2. El autor puede acceder al formulario de corrección y modificar sus datos.
     */
    public function test_02_autor_puede_corregir_sus_datos(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionEnCorreccionFixture();

        // Acceso al formulario
        $responseForm = $this->actingAs($autor)->get(route('devoluciones.edit', $devolucion->id));
        $responseForm->assertOk();
        $responseForm->assertSee($devolucion->receptor_nombre);
        $responseForm->assertSee($devolucion->receptor_identificador);
        $responseForm->assertSee('Guardar y reenviar');

        // Envío de actualización de datos
        $response = $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => 'Eduardo Pech Canul Corregido',
            'receptor_identificador' => 'DOC-9081',
            'fecha_entrega' => Carbon::now(Publicacion::TIMEZONE)->subDays(2)->format('Y-m-d'),
            'hora_entrega' => '14:30',
            'explicacion_propiedad' => 'El profesor acreditó la propiedad con número de serie y credencial docente vigente.',
        ]);

        $response->assertRedirect(route('devoluciones.show', $devolucion->id));
        $response->assertSessionHas('status', 'La devolución se corrigió y fue reenviada a verificación administrativa.');

        $this->assertDatabaseHas('devoluciones', [
            'id' => $devolucion->id,
            'receptor_nombre' => 'Eduardo Pech Canul Corregido',
            'receptor_identificador' => 'DOC-9081',
            'hora_entrega' => '14:30',
        ]);
    }

    /**
     * 3. El autor puede sustituir la fotografía de evidencia.
     */
    public function test_03_autor_puede_sustituir_la_fotografia(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionEnCorreccionFixture();

        $nuevaFoto = UploadedFile::fake()->image('nueva_evidencia.png', 800, 600);

        $response = $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => $devolucion->receptor_nombre,
            'receptor_identificador' => $devolucion->receptor_identificador,
            'fecha_entrega' => $devolucion->fecha_entrega->format('Y-m-d'),
            'hora_entrega' => substr((string) $devolucion->hora_entrega, 0, 5),
            'explicacion_propiedad' => $devolucion->explicacion_propiedad,
            'fotografia' => $nuevaFoto,
        ]);

        $response->assertRedirect(route('devoluciones.show', $devolucion->id));

        $devolucion->refresh();
        $this->assertEquals('nueva_evidencia.png', $devolucion->fotografia_nombre_original);
        $this->assertEquals('image/png', $devolucion->fotografia_mime_type);
        Storage::disk('local')->assertExists($devolucion->fotografia_ruta);
    }

    /**
     * 4. El autor puede conservar la fotografía actual si sigue siendo válida.
     */
    public function test_04_autor_puede_conservar_la_fotografia_actual_si_sigue_siendo_valida(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionEnCorreccionFixture();
        $rutaOriginal = $devolucion->fotografia_ruta;

        // Se envía actualización SIN adjuntar archivo 'fotografia'
        $response = $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => 'Nombre Actualizado',
            'receptor_identificador' => $devolucion->receptor_identificador,
            'fecha_entrega' => $devolucion->fecha_entrega->format('Y-m-d'),
            'hora_entrega' => substr((string) $devolucion->hora_entrega, 0, 5),
            'explicacion_propiedad' => 'Explicación detallada actualizada con información adicional.',
        ]);

        $response->assertRedirect(route('devoluciones.show', $devolucion->id));

        $devolucion->refresh();
        $this->assertEquals($rutaOriginal, $devolucion->fotografia_ruta);
        Storage::disk('local')->assertExists($rutaOriginal);
    }

    /**
     * 5. La nueva fotografía cumple formato y tamaño (JPG/PNG <= 2 MB).
     */
    public function test_05_la_nueva_fotografia_cumple_formato_y_tamano(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionEnCorreccionFixture();

        // 1. Archivo PDF no permitido
        $archivoPdf = UploadedFile::fake()->create('documento.pdf', 500, 'application/pdf');
        $responsePdf = $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => $devolucion->receptor_nombre,
            'receptor_identificador' => $devolucion->receptor_identificador,
            'fecha_entrega' => $devolucion->fecha_entrega->format('Y-m-d'),
            'hora_entrega' => substr((string) $devolucion->hora_entrega, 0, 5),
            'explicacion_propiedad' => $devolucion->explicacion_propiedad,
            'fotografia' => $archivoPdf,
        ]);
        $responsePdf->assertSessionHasErrors(['fotografia']);

        // 2. Archivo mayor a 2 MB (2500 KB)
        $archivoPesado = UploadedFile::fake()->image('foto_pesada.jpg')->size(2500);
        $responsePesado = $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => $devolucion->receptor_nombre,
            'receptor_identificador' => $devolucion->receptor_identificador,
            'fecha_entrega' => $devolucion->fecha_entrega->format('Y-m-d'),
            'hora_entrega' => substr((string) $devolucion->hora_entrega, 0, 5),
            'explicacion_propiedad' => $devolucion->explicacion_propiedad,
            'fotografia' => $archivoPesado,
        ]);
        $responsePesado->assertSessionHasErrors(['fotografia']);
    }

    /**
     * 6. Los datos inválidos se rechazan y conservan errores.
     */
    public function test_06_los_datos_invalidos_se_rechazan(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionEnCorreccionFixture();

        $fechaFutura = Carbon::now(Publicacion::TIMEZONE)->addDays(2)->format('Y-m-d');
        $fechaPreviaHallazgo = Carbon::parse($publicacion->fecha_hallazgo)->subDays(2)->format('Y-m-d');

        // Nombre vacío y fecha futura
        $response = $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => '   ',
            'receptor_identificador' => '!!!ID_INVALIDO###',
            'fecha_entrega' => $fechaFutura,
            'hora_entrega' => '12:00',
            'explicacion_propiedad' => '   ',
        ]);

        $response->assertSessionHasErrors(['receptor_nombre', 'receptor_identificador', 'fecha_entrega', 'explicacion_propiedad']);

        // Fecha anterior al hallazgo
        $responsePrevia = $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => 'Receptor Valido',
            'receptor_identificador' => '19070001',
            'fecha_entrega' => $fechaPreviaHallazgo,
            'hora_entrega' => '12:00',
            'explicacion_propiedad' => 'Explicacion valida',
        ]);

        $responsePrevia->assertSessionHasErrors(['fecha_entrega']);
    }

    /**
     * 7. Cancelar la edición no modifica información en la base de datos.
     */
    public function test_07_cancelar_no_modifica_informacion(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionEnCorreccionFixture();

        $response = $this->actingAs($autor)->get(route('devoluciones.edit', $devolucion->id));
        $response->assertOk();
        $response->assertSee('Cancelar edición sin guardar');
        $response->assertSee(route('devoluciones.show', $devolucion->id));

        $devolucionActual = Devolucion::find($devolucion->id);
        $this->assertEquals('Eduardo Pech Canul', $devolucionActual->receptor_nombre);
        $this->assertEquals(Devolucion::ESTADO_REQUIERE_CORRECCION, $devolucionActual->estado);
    }

    /**
     * 8. Se mantiene el mismo identificador de devolución tras la corrección.
     */
    public function test_08_se_mantiene_el_mismo_identificador_de_devolucion(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionEnCorreccionFixture();
        $idOriginal = $devolucion->id;

        $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => 'Eduardo Pech Canul Actualizado',
            'receptor_identificador' => $devolucion->receptor_identificador,
            'fecha_entrega' => $devolucion->fecha_entrega->format('Y-m-d'),
            'hora_entrega' => substr((string) $devolucion->hora_entrega, 0, 5),
            'explicacion_propiedad' => 'Explicación actualizada y ampliada.',
        ]);

        $devoluciones = Devolucion::where('publicacion_id', $publicacion->id)->get();
        $this->assertCount(1, $devoluciones);
        $this->assertEquals($idOriginal, $devoluciones->first()->id);
    }

    /**
     * 9. No se genera otro evento de entrega ni registros duplicados.
     */
    public function test_09_no_se_genera_otro_evento_de_entrega(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionEnCorreccionFixture();

        $this->assertEquals(1, Devolucion::count());
        $this->assertEquals(1, Publicacion::count());

        $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => 'Receptor Reenviado',
            'receptor_identificador' => '20080123',
            'fecha_entrega' => $devolucion->fecha_entrega->format('Y-m-d'),
            'hora_entrega' => substr((string) $devolucion->hora_entrega, 0, 5),
            'explicacion_propiedad' => 'Explicación complementada.',
        ]);

        $this->assertEquals(1, Devolucion::count());
        $this->assertEquals(1, Publicacion::count());
    }

    /**
     * 10. El reenvío cambia el estado correctamente a «Pendiente de verificación».
     */
    public function test_10_el_reenvio_cambia_el_estado_correctamente(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionEnCorreccionFixture();
        $fechaCapturaOriginal = $devolucion->fecha_captura;

        $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => $devolucion->receptor_nombre,
            'receptor_identificador' => $devolucion->receptor_identificador,
            'fecha_entrega' => $devolucion->fecha_entrega->format('Y-m-d'),
            'hora_entrega' => substr((string) $devolucion->hora_entrega, 0, 5),
            'explicacion_propiedad' => 'Explicación corregida conforme a la solicitud.',
        ]);

        $devolucion->refresh();
        $publicacion->refresh();

        $this->assertEquals(Devolucion::ESTADO_PENDIENTE, $devolucion->estado);
        $this->assertEquals(Devolucion::ESTADO_PENDIENTE, $publicacion->estado_devolucion);
        $this->assertNull($devolucion->motivo_correccion); // Motivo anterior deja de estar vigente
        $this->assertNotNull($devolucion->fecha_envio_revision);
        $this->assertEquals($fechaCapturaOriginal->toDateTimeString(), $devolucion->fecha_captura->toDateTimeString()); // Fecha de registro inicial se conserva
        $this->assertFalse((bool) $publicacion->disponible); // Permanece fuera del muro
        $this->assertTrue((bool) $publicacion->devolucion_en_proceso);
    }

    /**
     * 11. La devolución reaparece en la cola administrativa ordenada por fecha de reenvío.
     */
    public function test_11_la_devolucion_reaparece_en_la_cola_administrativa(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionEnCorreccionFixture();

        // Antes del reenvío: no debe aparecer en la cola de pendientes
        $this->assertCount(0, Devolucion::pendientesVerificacion()->get());

        // El autor reenvía
        $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => $devolucion->receptor_nombre,
            'receptor_identificador' => $devolucion->receptor_identificador,
            'fecha_entrega' => $devolucion->fecha_entrega->format('Y-m-d'),
            'hora_entrega' => substr((string) $devolucion->hora_entrega, 0, 5),
            'explicacion_propiedad' => 'Explicación completada con éxito.',
        ]);

        // Después del reenvío: debe figurar en la cola administrativa
        $this->assertCount(1, Devolucion::pendientesVerificacion()->get());

        $responseCola = $this->actingAs($admin)->get(route('admin.devoluciones.index'));
        $responseCola->assertOk();
        $responseCola->assertSee($publicacion->folio);
        $responseCola->assertSee('Pendiente de verificación');
    }

    /**
     * 12. La fotografía sustituida deja de ser accesible e invalida versiones antiguas.
     */
    public function test_12_la_fotografia_sustituida_deja_de_ser_accesible(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionEnCorreccionFixture();
        $rutaAntigua = $devolucion->fotografia_ruta;
        Storage::disk('local')->assertExists($rutaAntigua);

        $fotoNueva = UploadedFile::fake()->image('foto_reemplazo.jpg', 800, 600);

        $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => $devolucion->receptor_nombre,
            'receptor_identificador' => $devolucion->receptor_identificador,
            'fecha_entrega' => $devolucion->fecha_entrega->format('Y-m-d'),
            'hora_entrega' => substr((string) $devolucion->hora_entrega, 0, 5),
            'explicacion_propiedad' => $devolucion->explicacion_propiedad,
            'fotografia' => $fotoNueva,
        ]);

        $devolucion->refresh();
        $rutaNueva = $devolucion->fotografia_ruta;

        // La ruta antigua debe haber sido eliminada del disco
        Storage::disk('local')->assertMissing($rutaAntigua);
        // La ruta nueva debe existir
        Storage::disk('local')->assertExists($rutaNueva);

        // La ruta de evidencia responde ahora con la nueva foto
        $responseEvidencia = $this->actingAs($autor)->get(route('devoluciones.evidencia', $devolucion->id));
        $responseEvidencia->assertOk();
        $this->assertEquals('foto_reemplazo.jpg', $devolucion->fotografia_nombre_original);
    }

    /**
     * 13. El historial conserva decisiones anteriores sin copias antiguas de fotografías ni datos.
     */
    public function test_13_el_historial_conserva_decisiones_sin_copias_antiguas(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionEnCorreccionFixture();

        $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => 'Eduardo Pech Canul',
            'receptor_identificador' => '20080123',
            'fecha_entrega' => $devolucion->fecha_entrega->format('Y-m-d'),
            'hora_entrega' => substr((string) $devolucion->hora_entrega, 0, 5),
            'explicacion_propiedad' => 'Explicación atendida.',
        ]);

        $decisiones = DecisionModeracion::where('publicacion_id', $publicacion->id)->get();
        $this->assertCount(1, $decisiones);

        $decision = $decisiones->first();
        $this->assertEquals(DecisionModeracion::TIPO_DEVOLUCION_CORRECCION, $decision->tipo_decision);
        $this->assertEquals('La fotografía está muy oscura y no se aprecia con claridad la credencial institucional del receptor.', $decision->motivo);

        // La decisión no almacena copias de fotografía ni campos privados del receptor
        $this->assertFalse(array_key_exists('fotografia_ruta', $decision->toArray()));
        $this->assertFalse(array_key_exists('receptor_nombre', $decision->toArray()));
    }

    /**
     * 14. Otro usuario no puede acceder al formulario ni editar la devolución ajena.
     */
    public function test_14_otro_usuario_no_puede_editar(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionEnCorreccionFixture();

        // Acceso al formulario por tercero -> 403
        $responseGet = $this->actingAs($otro)->get(route('devoluciones.edit', $devolucion->id));
        $responseGet->assertForbidden();

        // Intento de envío por tercero -> 403
        $responsePut = $this->actingAs($otro)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => 'Hacker Intruso',
            'receptor_identificador' => '00000000',
            'fecha_entrega' => $devolucion->fecha_entrega->format('Y-m-d'),
            'hora_entrega' => '10:00',
            'explicacion_propiedad' => 'Intento no autorizado de suplantación.',
        ]);
        $responsePut->assertForbidden();

        // Sin autenticación -> redirige a login
        $this->post(route('logout'));
        $this->get(route('devoluciones.edit', $devolucion->id))->assertRedirect(route('login'));
        $this->put(route('devoluciones.update', $devolucion->id), [])->assertRedirect(route('login'));
    }

    /**
     * 15. Una devolución aprobada no puede editarse por esta vía.
     */
    public function test_15_una_devolucion_aprobada_no_puede_editarse_por_esta_via(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionEnCorreccionFixture([], [
            'estado' => Devolucion::ESTADO_APROBADA,
            'motivo_correccion' => null,
        ]);

        $responseGet = $this->actingAs($autor)->get(route('devoluciones.edit', $devolucion->id));
        $responseGet->assertRedirect(route('devoluciones.show', $devolucion->id));
        $responseGet->assertSessionHas('error', 'Esta devolución ya fue aprobada y no puede modificarse.');

        $responsePut = $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => 'Intento Tras Aprobada',
            'receptor_identificador' => '20080123',
            'fecha_entrega' => $devolucion->fecha_entrega->format('Y-m-d'),
            'hora_entrega' => '10:00',
            'explicacion_propiedad' => 'Intento inválido.',
        ]);
        $responsePut->assertRedirect(route('devoluciones.show', $devolucion->id));
        $responsePut->assertSessionHas('error');
    }

    /**
     * 16. Un doble envío no genera duplicados ni reescribe si el estado ya cambió.
     */
    public function test_16_un_doble_envio_no_genera_duplicados(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionEnCorreccionFixture();

        // Primer envío exitoso
        $response1 = $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => 'Eduardo Pech Canul',
            'receptor_identificador' => '20080123',
            'fecha_entrega' => $devolucion->fecha_entrega->format('Y-m-d'),
            'hora_entrega' => '11:15',
            'explicacion_propiedad' => 'Explicación atendida en primer envío.',
        ]);
        $response1->assertRedirect(route('devoluciones.show', $devolucion->id));

        // Segundo envío inmediato cuando ya no está en 'Requiere corrección'
        $response2 = $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
            'receptor_nombre' => 'Segundo Intento Invalido',
            'receptor_identificador' => '20080123',
            'fecha_entrega' => $devolucion->fecha_entrega->format('Y-m-d'),
            'hora_entrega' => '11:15',
            'explicacion_propiedad' => 'Intento duplicado.',
        ]);

        $response2->assertRedirect(route('devoluciones.show', $devolucion->id));
        $response2->assertSessionHas('error');

        $this->assertDatabaseMissing('devoluciones', [
            'receptor_nombre' => 'Segundo Intento Invalido',
        ]);
    }

    /**
     * 17. Los errores conservan la última versión válida de los datos y de la fotografía.
     */
    public function test_17_los_errores_conservan_la_ultima_version_valida(): void
    {
        [$autor, $admin, $otro, $publicacion, $devolucion] = $this->crearDevolucionEnCorreccionFixture();
        $rutaOriginal = $devolucion->fotografia_ruta;
        Storage::disk('local')->assertExists($rutaOriginal);

        // Simulamos un fallo durante la actualización con mock o listener
        Devolucion::saving(function ($dev) {
            if ($dev->receptor_nombre === 'Provocar Fallo DB') {
                throw new \RuntimeException('Fallo simulado en almacenamiento de base de datos');
            }
        });

        $nuevaFoto = UploadedFile::fake()->image('nueva_foto_a_revertir.jpg', 800, 600);

        try {
            $response = $this->actingAs($autor)->put(route('devoluciones.update', $devolucion->id), [
                'receptor_nombre' => 'Provocar Fallo DB',
                'receptor_identificador' => '20080123',
                'fecha_entrega' => $devolucion->fecha_entrega->format('Y-m-d'),
                'hora_entrega' => '11:15',
                'explicacion_propiedad' => 'Explicación que fallará en la transacción.',
                'fotografia' => $nuevaFoto,
            ]);

            $response->assertRedirect(route('devoluciones.edit', $devolucion->id));
            $response->assertSessionHasErrors(['general']);
        } finally {
            // Limpiar listener para que no afecte otros tests
            Devolucion::flushEventListeners();
        }

        // La fotografía original debe permanecer intacta en el disco
        Storage::disk('local')->assertExists($rutaOriginal);

        // El registro en BD debe mantener los datos originales y su estado
        $devolucion->refresh();
        $this->assertEquals('Eduardo Pech Canul', $devolucion->receptor_nombre);
        $this->assertEquals(Devolucion::ESTADO_REQUIERE_CORRECCION, $devolucion->estado);
        $this->assertEquals($rutaOriginal, $devolucion->fotografia_ruta);
    }
}

