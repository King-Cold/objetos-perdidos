<?php

namespace Tests\Feature\Admin;

use App\Models\Devolucion;
use App\Models\Fotografia;
use App\Models\Incidencia;
use App\Models\Publicacion;
use App\Models\Recepcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncidenciasPublicacionExcepcionalTask174Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $autor;
    protected User $tercero;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::factory()->admin()->create([
            'name' => 'Admin Biblioteca Poniente',
            'email' => 'admin.excepcional@tecnm.mx',
        ]);

        $this->autor = User::factory()->create([
            'name' => 'Autor Hallazgo Recuperado',
            'email' => 'autor.excepcional@tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $this->tercero = User::factory()->create([
            'name' => 'Estudiante Consulta Muro',
            'email' => 'estudiante.muro@tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Crea un reporte con devolución incorrecta confirmada y recepción física en biblioteca registrada.
     *
     * @return array{0: Publicacion, 1: Devolucion, 2: Incidencia, 3: Recepcion}
     */
    private function crearCasoRecuperadoEnBiblioteca(
        string $folio = 'OBJ-20261001-0077',
        string $nombre = 'Calculadora Graficadora Texas Instruments',
        string $fechaPrimeraAprobacion = '2026-10-01 09:30:00',
        string $fechaRecepcion = '2026-10-20'
    ): array {
        $primeraAprob = Carbon::parse($fechaPrimeraAprobacion, Publicacion::TIMEZONE);

        $publicacion = Publicacion::create([
            'user_id' => $this->autor->id,
            'folio' => $folio,
            'nombre' => $nombre,
            'titulo' => $nombre,
            'descripcion' => 'Calculadora graficadora color gris oscuro con funda rígida deslizable.',
            'categoria' => 'Electrónicos',
            'area_zona' => 'Edificio D',
            'lugar_hallazgo' => 'Aula D4',
            'ubicacion_especifica' => 'Escritorio frontal',
            'fecha_hallazgo' => '2026-09-30',
            'hora_hallazgo' => '11:00',
            'fecha_primera_aprobacion' => $primeraAprob,
            'fecha_aprobacion' => $primeraAprob,
            'fecha_vencimiento' => Publicacion::calcularFechaVencimiento($primeraAprob),
            'estado' => 'En resguardo',
            'situacion_resguardo' => 'En biblioteca',
            'disponible' => false,
            'devolucion_en_proceso' => false,
            'incidencia_abierta' => true,
            'tiene_incidencia' => true,
            'estado_devolucion' => 'Recuperado en biblioteca',
        ]);

        Storage::disk('local')->put('publicaciones/foto_calculadora.jpg', 'contenido-foto-original');
        Fotografia::create([
            'publicacion_id' => $publicacion->id,
            'ruta' => 'publicaciones/foto_calculadora.jpg',
            'nombre_original' => 'foto_calculadora.jpg',
            'mime_type' => 'image/jpeg',
            'tamano' => 1024,
            'orden' => 1,
            'es_principal' => true,
        ]);

        $rutaEvidenciaIncorrecta = "devoluciones/{$publicacion->id}/evidencia_entrega_incorrecta.jpg";
        Storage::disk('local')->put($rutaEvidenciaIncorrecta, 'contenido-evidencia-entrega-incorrecta');

        $devolucionIncorrecta = Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $this->autor->id,
            'admin_id' => $this->admin->id,
            'receptor_nombre' => 'Receptor Equivocado',
            'receptor_identificador' => 'E21089999',
            'fecha_entrega' => '2026-10-05',
            'hora_entrega' => '13:00',
            'explicacion_propiedad' => 'Indicó que olvidó una calculadora similar en el edificio D.',
            'fotografia_ruta' => $rutaEvidenciaIncorrecta,
            'fotografia_nombre_original' => 'evidencia_entrega_incorrecta.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano' => 2048,
            'confirmacion_entrega' => true,
            'estado' => Devolucion::ESTADO_VERIFICADA,
            'fecha_captura' => Carbon::parse('2026-10-05 13:15:00', Publicacion::TIMEZONE),
            'fecha_resolucion' => Carbon::parse('2026-10-05 15:00:00', Publicacion::TIMEZONE),
        ]);

        $incidencia = Incidencia::create([
            'publicacion_id' => $publicacion->id,
            'devolucion_id' => $devolucionIncorrecta->id,
            'user_id' => $this->admin->id,
            'responsable_revision_id' => $this->admin->id,
            'motivo' => 'El verdadero dueño acreditó el número de serie grabado en la parte trasera.',
            'estado' => Incidencia::ESTADO_ESPERA_RECUPERACION,
            'resultado' => 'Recuperación física confirmada mediante recepción en biblioteca',
            'motivo_resolucion' => 'Se confirmó que el primer receptor tomó el objeto por confusión y lo entregó en biblioteca.',
            'solicitud_recuperacion' => 'Se solicitó llevar el objeto a biblioteca.',
            'recuperacion_confirmada' => true,
            'fecha_apertura' => Carbon::parse('2026-10-18 10:00:00', Publicacion::TIMEZONE),
            'fecha_revision' => Carbon::parse('2026-10-19 11:00:00', Publicacion::TIMEZONE),
            'fecha_recuperacion' => Carbon::parse($fechaRecepcion . ' 12:00:00', Publicacion::TIMEZONE),
        ]);

        $recepcion = Recepcion::create([
            'publicacion_id' => $publicacion->id,
            'incidencia_id' => $incidencia->id,
            'user_id' => $this->admin->id,
            'fecha_recepcion' => $fechaRecepcion,
            'hora_recepcion' => '12:00',
            'fecha_captura' => Carbon::parse($fechaRecepcion . ' 12:05:00', Publicacion::TIMEZONE),
            'condicion_objeto' => 'En buen estado con funda deslizable.',
            'ubicacion_resguardo' => 'Estante B-4, Gaveta 1',
            'observaciones' => 'Recibido físicamente en biblioteca tras aclaración de incidencia.',
            'confirmacion_cotejo' => true,
            'fecha_limite_anual' => Recepcion::calcularFechaLimiteAnual($fechaRecepcion, '12:00'),
        ]);

        $incidencia->update(['recepcion_id' => $recepcion->id]);

        return [$publicacion->fresh(), $devolucionIncorrecta->fresh(), $incidencia->fresh(), $recepcion->fresh()];
    }

    public function test_autoriza_publicacion_excepcional_de_14_dias_como_disponible_en_biblioteca_conservando_folio_primera_aprobacion_y_anio_resguardo(): void
    {
        $momentoAutorizacion = Carbon::create(2026, 11, 1, 10, 30, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($momentoAutorizacion);

        [$publicacion, $devolucion, $incidencia, $recepcion] = $this->crearCasoRecuperadoEnBiblioteca(
            'OBJ-20261001-0077',
            'Calculadora Graficadora Texas Instruments',
            '2026-10-01 09:30:00',
            '2026-10-20'
        );

        $folioOriginal = $publicacion->folio;
        $primeraAprobacionOriginal = $publicacion->fecha_primera_aprobacion->copy();
        $limiteAnualOriginal = $recepcion->fecha_limite_anual->toDateString();

        $response = $this->actingAs($this->admin)->post(
            route('admin.incidencias.publicacion-excepcional', $incidencia->id),
            [
                'nombre' => 'Calculadora Graficadora Texas Instruments (En Biblioteca)',
                'descripcion' => 'Calculadora graficadora color gris oscuro bajo resguardo en biblioteca para reclamo del propietario.',
                'motivo' => 'Contenido revisado y validado tras recepción física confirmada en biblioteca.',
                'confirmacion_revision_contenido' => '1',
            ]
        );

        $response->assertRedirect(route('admin.incidencias.show', $incidencia->id));
        $response->assertSessionHas('status');

        $publicacion->refresh();
        $recepcion->refresh();
        $incidencia->refresh();

        // 1. Mantiene el mismo folio
        $this->assertSame($folioOriginal, $publicacion->folio);

        // 2. Mantiene intacta la fecha de primera aprobación y registra fecha especial independiente + 14 días
        $this->assertSame(
            $primeraAprobacionOriginal->format('Y-m-d H:i:s'),
            $publicacion->fecha_primera_aprobacion->format('Y-m-d H:i:s')
        );
        $this->assertTrue((bool) $publicacion->publicacion_excepcional_autorizada);
        $this->assertNotNull($publicacion->fecha_publicacion_excepcional);
        $this->assertNotNull($publicacion->fecha_vencimiento_excepcional);
        $this->assertSame(
            $momentoAutorizacion->format('Y-m-d H:i:s'),
            $publicacion->fecha_publicacion_excepcional->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            $momentoAutorizacion->copy()->addDays(14)->format('Y-m-d H:i:s'),
            $publicacion->fecha_vencimiento_excepcional->format('Y-m-d H:i:s')
        );

        // 3. Mantiene el año de resguardo calculado desde la recepción (2026-10-20 -> 2027-10-20)
        $this->assertSame($limiteAnualOriginal, $recepcion->fecha_limite_anual->toDateString());
        $this->assertSame('2027-10-20', $recepcion->fecha_limite_anual->toDateString());

        // 4. Queda publicada como «Disponible en biblioteca» y visible en el muro y detalle público
        $this->assertSame(Publicacion::ESTADO_DISPONIBLE_BIBLIOTECA, $publicacion->estado);
        $this->assertTrue($publicacion->isDisponible());
        $this->assertTrue($publicacion->isPublicacionExcepcionalVigente());
        $this->assertSame('Disponible en biblioteca', $publicacion->disponibilidad_texto);

        $muroResponse = $this->actingAs($this->tercero)->get(route('muro'));
        $muroResponse->assertOk();
        $muroResponse->assertSee('Calculadora Graficadora Texas Instruments (En Biblioteca)');
        $muroResponse->assertSee('Disponible en biblioteca');

        $detalleResponse = $this->actingAs($this->tercero)->get(route('publicaciones.show', $publicacion->id));
        $detalleResponse->assertOk();
        $detalleResponse->assertSee($folioOriginal);
        $detalleResponse->assertSee('Disponible en biblioteca');
        $detalleResponse->assertSee('01/11/2026'); // Fecha especial de publicación excepcional
    }

    public function test_impide_autorizar_segunda_publicacion_excepcional_o_sin_recepcion_fisica_o_con_anio_resguardo_vencido(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 2, 11, 0, 0, Publicacion::TIMEZONE));

        [$publicacion, $devolucion, $incidencia, $recepcion] = $this->crearCasoRecuperadoEnBiblioteca();

        // 1. Autorizar la primera y única publicación excepcional
        $this->actingAs($this->admin)->post(
            route('admin.incidencias.publicacion-excepcional', $incidencia->id),
            [
                'nombre' => $publicacion->nombre,
                'descripcion' => $publicacion->descripcion,
                'motivo' => 'Primera y única autorización excepcional tras recepción física.',
                'confirmacion_revision_contenido' => '1',
            ]
        );

        $this->assertTrue((bool) $publicacion->fresh()->publicacion_excepcional_autorizada);

        // 2. Intentar autorizar una segunda publicación excepcional debe ser rechazado
        $segundaRespuesta = $this->actingAs($this->admin)->post(
            route('admin.incidencias.publicacion-excepcional', $incidencia->id),
            [
                'nombre' => $publicacion->nombre,
                'descripcion' => $publicacion->descripcion,
                'motivo' => 'Intento de segunda publicación excepcional.',
                'confirmacion_revision_contenido' => '1',
            ]
        );

        $segundaRespuesta->assertRedirect(route('admin.incidencias.show', $incidencia->id));
        $segundaRespuesta->assertSessionHasErrors('publicacion_excepcional');

        // 3. Caso sin recepción física confirmada no puede autorizar publicación excepcional
        [$pubSinRec, $devSinRec, $incSinRec, $recTemp] = $this->crearCasoRecuperadoEnBiblioteca(
            'OBJ-20261002-0088',
            'Audífonos Inalámbricos Sony'
        );
        $incSinRec->update(['recuperacion_confirmada' => false, 'recepcion_id' => null]);
        $recTemp->delete();

        $sinRecepcionResponse = $this->actingAs($this->admin)->post(
            route('admin.incidencias.publicacion-excepcional', $incSinRec->id),
            [
                'nombre' => $pubSinRec->nombre,
                'descripcion' => $pubSinRec->descripcion,
                'motivo' => 'Intento sin recepción física.',
                'confirmacion_revision_contenido' => '1',
            ]
        );

        $sinRecepcionResponse->assertRedirect(route('admin.incidencias.show', $incSinRec->id));
        $sinRecepcionResponse->assertSessionHasErrors('publicacion_excepcional');
        $this->assertFalse((bool) $pubSinRec->fresh()->publicacion_excepcional_autorizada);

        // 4. Caso con año de resguardo vencido no puede autorizar publicación excepcional
        [$pubAnualVencido, $devAnual, $incAnual, $recAnual] = $this->crearCasoRecuperadoEnBiblioteca(
            'OBJ-20250901-0099',
            'Termo Metálico Azul',
            '2025-09-01 09:00:00',
            '2025-09-15'
        );

        $anualVencidoResponse = $this->actingAs($this->admin)->post(
            route('admin.incidencias.publicacion-excepcional', $incAnual->id),
            [
                'nombre' => $pubAnualVencido->nombre,
                'descripcion' => $pubAnualVencido->descripcion,
                'motivo' => 'Intento con año de resguardo vencido.',
                'confirmacion_revision_contenido' => '1',
            ]
        );

        $anualVencidoResponse->assertRedirect(route('admin.incidencias.show', $incAnual->id));
        $anualVencidoResponse->assertSessionHasErrors('publicacion_excepcional');
        $this->assertFalse((bool) $pubAnualVencido->fresh()->publicacion_excepcional_autorizada);
    }

    public function test_al_vencer_los_14_dias_de_publicacion_excepcional_se_retira_del_muro_sin_sacar_del_inventario_de_biblioteca(): void
    {
        $inicioExcepcional = Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($inicioExcepcional);

        [$publicacion, $devolucion, $incidencia, $recepcion] = $this->crearCasoRecuperadoEnBiblioteca(
            'OBJ-20261001-0105',
            'Multímetro Digital Fluke Amarillo'
        );

        $this->actingAs($this->admin)->post(
            route('admin.incidencias.publicacion-excepcional', $incidencia->id),
            [
                'nombre' => $publicacion->nombre,
                'descripcion' => $publicacion->descripcion,
                'motivo' => 'Se autoriza publicación excepcional de 14 días tras recuperación física.',
                'confirmacion_revision_contenido' => '1',
            ]
        );

        // Día 13: sigue vigente en el muro
        Carbon::setTestNow($inicioExcepcional->copy()->addDays(13));
        $this->assertTrue($publicacion->fresh()->isDisponible());
        $this->get(route('muro'))->assertSee('Multímetro Digital Fluke Amarillo');

        // Día 14 cumplido (+1 minuto): vence la publicación excepcional
        Carbon::setTestNow($inicioExcepcional->copy()->addDays(14)->addMinute());
        $publicacion->refresh();

        $this->assertTrue($publicacion->isPublicacionExcepcionalVencida());
        $this->assertFalse($publicacion->isPublicacionExcepcionalVigente());
        $this->assertFalse($publicacion->isDisponible());

        // Se retira del muro público y del detalle público
        $this->get(route('muro'))->assertDontSee('Multímetro Digital Fluke Amarillo');
        $this->get(route('publicaciones.show', $publicacion->id))
            ->assertRedirect(route('muro'));

        // Sin sacar el objeto del inventario de biblioteca
        $inventarioResponse = $this->actingAs($this->admin)->get(route('admin.inventario.index'));
        $inventarioResponse->assertOk();
        $inventarioResponse->assertSee('OBJ-20261001-0105');
        $inventarioResponse->assertSee('Multímetro Digital Fluke Amarillo');
        $inventarioResponse->assertSee('Estante B-4, Gaveta 1');
    }

    public function test_devolucion_posterior_desde_biblioteca_registra_nuevo_evento_conservando_entrega_incorrecta_evidencia_e_incidencia_privadas(): void
    {
        $inicioExcepcional = Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($inicioExcepcional);

        [$publicacion, $devolucionIncorrecta, $incidencia, $recepcion] = $this->crearCasoRecuperadoEnBiblioteca(
            'OBJ-20261001-0112',
            'Reloj Inteligente Garmin Negro'
        );

        // Autorizar la publicación excepcional
        $this->actingAs($this->admin)->post(
            route('admin.incidencias.publicacion-excepcional', $incidencia->id),
            [
                'nombre' => $publicacion->nombre,
                'descripcion' => $publicacion->descripcion,
                'motivo' => 'Se autoriza difusión excepcional de 14 días en biblioteca.',
                'confirmacion_revision_contenido' => '1',
            ]
        );

        // Dos días después acude el verdadero propietario a la biblioteca
        $this->flushSession();
        Carbon::setTestNow($inicioExcepcional->copy()->addDays(2));

        $jpegMinimo = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
        $nuevaEvidencia = UploadedFile::fake()->createWithContent('evidencia_dueno_real.jpg', $jpegMinimo);

        $responseDevolucion = $this->actingAs($this->admin)->post(
            route('admin.devoluciones-biblioteca.store', $publicacion->id),
            [
                'receptor_nombre' => 'Propietario Legítimo Verificado',
                'receptor_identificador' => 'E20081234',
                'fecha_entrega' => '2026-11-03',
                'hora_entrega' => '09:30',
                'explicacion_propiedad' => 'Mostró factura original y vinculó el reloj con su teléfono en el mostrador.',
                'fotografia' => $nuevaEvidencia,
                'declaracion_sin_contradicciones' => '1',
            ]
        );

        $responseDevolucion->assertRedirect(route('admin.inventario.index'));
        $responseDevolucion->assertSessionHas('status');

        $publicacion->refresh();

        // 1. Se creó un NUEVO evento de devolución independiente (total = 2 devoluciones para este reporte)
        $devoluciones = Devolucion::where('publicacion_id', $publicacion->id)->orderBy('id', 'asc')->get();
        $this->assertCount(2, $devoluciones);

        $primeraDevolucion = $devoluciones->first();
        $segundaDevolucion = $devoluciones->last();

        // 2. La entrega incorrecta anterior y su evidencia se conservan intactas
        $this->assertSame($devolucionIncorrecta->id, $primeraDevolucion->id);
        $this->assertSame('Receptor Equivocado', $primeraDevolucion->receptor_nombre);
        Storage::disk('local')->assertExists($primeraDevolucion->fotografia_ruta);

        // 3. La nueva devolución desde biblioteca tiene sus propios datos y evidencia privada
        $this->assertSame('Propietario Legítimo Verificado', $segundaDevolucion->receptor_nombre);
        $this->assertSame('E20081234', $segundaDevolucion->receptor_identificador);
        Storage::disk('local')->assertExists($segundaDevolucion->fotografia_ruta);

        // 4. La incidencia y sus antecedentes se conservan intactos
        $this->assertDatabaseHas('incidencias', [
            'id' => $incidencia->id,
            'publicacion_id' => $publicacion->id,
            'devolucion_id' => $devolucionIncorrecta->id,
            'recuperacion_confirmada' => true,
        ]);

        // 5. El objeto pasa a estado «Devuelto» y sale del muro y del inventario activo
        $this->assertSame(Publicacion::ESTADO_DEVUELTO, $publicacion->estado);
        $this->assertFalse($publicacion->isDisponible());
        $this->get(route('muro'))->assertDontSee('Reloj Inteligente Garmin Negro');

        // 6. Los datos del receptor y las evidencias fotográficas de ambas devoluciones permanecen privados
        $this->actingAs($this->tercero)
            ->get(route('devoluciones.evidencia', $primeraDevolucion->id))
            ->assertForbidden();

        $this->actingAs($this->tercero)
            ->get(route('devoluciones.evidencia', $segundaDevolucion->id))
            ->assertForbidden();
    }
}
