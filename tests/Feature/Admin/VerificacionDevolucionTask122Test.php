<?php

namespace Tests\Feature\Admin;

use App\Events\DevolucionAprobada;
use App\Events\DevolucionCorreccionSolicitada;
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

class VerificacionDevolucionTask122Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function crearDevolucionFixture(array $pubAttrs = [], array $devAttrs = []): array
    {
        $autor = User::factory()->create(['role' => User::ROLE_USUARIO_GENERAL]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $otroAdmin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $usuarioGeneral = User::factory()->create(['role' => User::ROLE_USUARIO_GENERAL]);

        $ahora = Carbon::now(Publicacion::TIMEZONE);

        $publicacion = Publicacion::factory()->create(array_merge([
            'user_id' => $autor->id,
            'nombre' => 'Calculadora Casio fx-991LAX',
            'descripcion' => 'Calculadora científica color negro',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'devolucion_en_proceso' => true,
            'estado_devolucion' => Devolucion::ESTADO_PENDIENTE,
            'fecha_hallazgo' => $ahora->copy()->subDays(5)->format('Y-m-d'),
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

        // Fotografía de evidencia privada de devolución
        $fotoEvidencia = UploadedFile::fake()->image('evidencia_entrega.jpg', 600, 400);
        $rutaEvidencia = $fotoEvidencia->storeAs('devoluciones/' . $publicacion->id, 'evidencia.jpg', 'local');

        $devolucion = Devolucion::create(array_merge([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Eduardo Pech Canul',
            'receptor_identificador' => '20080123',
            'fecha_entrega' => $ahora->copy()->subDays(2)->format('Y-m-d'),
            'hora_entrega' => '11:15',
            'fecha_captura' => $ahora->copy()->subDays(1),
            'fecha_envio_revision' => $ahora->copy()->subDays(1),
            'explicacion_propiedad' => 'El receptor presentó su credencial vigente y describió las marcas de uso del reverso.',
            'fotografia_ruta' => $rutaEvidencia,
            'fotografia_nombre_original' => 'evidencia_entrega.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 20480,
            'estado' => Devolucion::ESTADO_PENDIENTE,
        ], $devAttrs));

        return [$admin, $otroAdmin, $autor, $usuarioGeneral, $publicacion, $devolucion];
    }

    /**
     * 1. Aprobación válida:
     * - Cambia devolución a «Verificada» (o equivalente)
     * - Cambia objeto a «Devuelto»
     * - Registra admin responsable y fecha
     * - Objeto fuera del muro
     */
    public function test_01_aprobacion_valida(): void
    {
        [$admin, $otroAdmin, $autor, $usuarioGeneral, $publicacion, $devolucion] = $this->crearDevolucionFixture();

        $response = $this->actingAs($admin)
            ->post(route('admin.devoluciones.aprobar', $devolucion->id), [
                'confirmacion_revision' => '1',
            ]);

        $response->assertRedirect(route('admin.devoluciones.show', $devolucion->id));
        $response->assertSessionHas('status', 'La devolución fue aprobada correctamente y el objeto se marcó como devuelto.');

        $devFresca = $devolucion->fresh();
        $this->assertTrue($devFresca->isAprobada());
        $this->assertEquals($admin->id, $devFresca->admin_id);
        $this->assertNotNull($devFresca->fecha_resolucion);

        $pubFresca = $publicacion->fresh();
        $this->assertEquals(Publicacion::ESTADO_DEVUELTO, $pubFresca->estado);
        $this->assertFalse((bool) $pubFresca->disponible);
        $this->assertFalse((bool) $pubFresca->devolucion_en_proceso);
    }

    /**
     * 2. Confirmación cancelada: No se realizan cambios si no se confirma la acción.
     */
    public function test_02_confirmacion_cancelada_no_realiza_cambios(): void
    {
        [$admin, $otroAdmin, $autor, $usuarioGeneral, $publicacion, $devolucion] = $this->crearDevolucionFixture();

        // El administrador consulta el detalle sin confirmar
        $response = $this->actingAs($admin)->get(route('admin.devoluciones.show', $devolucion->id));
        $response->assertOk();
        $response->assertSee('Aprobar devolución');
        $response->assertSee('Solicitar corrección');

        // Estado sigue intacto
        $this->assertEquals(Devolucion::ESTADO_PENDIENTE, $devolucion->fresh()->estado);
        $this->assertEquals(Publicacion::ESTADO_APROBADA, $publicacion->fresh()->estado);
    }

    /**
     * 3. Datos obligatorios incompletos: Rechaza aprobación si faltan campos indispensables.
     */
    public function test_03_rechaza_aprobacion_si_datos_obligatorios_incompletos(): void
    {
        [$admin, $otroAdmin, $autor, $usuarioGeneral, $publicacion, $devolucion] = $this->crearDevolucionFixture([], [
            'receptor_nombre' => '',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.devoluciones.aprobar', $devolucion->id), [
                'confirmacion_revision' => '1',
            ]);

        $response->assertRedirect(route('admin.devoluciones.show', $devolucion->id));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('incompletos', session('error'));

        $this->assertEquals(Devolucion::ESTADO_PENDIENTE, $devolucion->fresh()->estado);
    }

    /**
     * 4. Fotografía faltante o inaccesible: Rechaza aprobación si el archivo no existe en almacenamiento.
     */
    public function test_04_rechaza_aprobacion_si_fotografia_faltante_o_inaccesible(): void
    {
        [$admin, $otroAdmin, $autor, $usuarioGeneral, $publicacion, $devolucion] = $this->crearDevolucionFixture([], [
            'fotografia_ruta' => 'devoluciones/inexistente/foto_borrada.jpg',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.devoluciones.aprobar', $devolucion->id), [
                'confirmacion_revision' => '1',
            ]);

        $response->assertRedirect(route('admin.devoluciones.show', $devolucion->id));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('fotografía de evidencia no existe', session('error'));

        $this->assertEquals(Devolucion::ESTADO_PENDIENTE, $devolucion->fresh()->estado);
    }

    /**
     * 5. Fechas incoherentes: Rechaza aprobación si fecha de entrega es anterior al hallazgo o futura.
     */
    public function test_05_rechaza_aprobacion_si_fechas_incoherentes(): void
    {
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        // Caso A: Fecha entrega anterior al hallazgo
        [$admin, $otroAdmin, $autor, $usuarioGeneral, $pubA, $devA] = $this->crearDevolucionFixture(
            ['fecha_hallazgo' => $ahora->copy()->subDays(3)->format('Y-m-d')],
            ['fecha_entrega' => $ahora->copy()->subDays(5)->format('Y-m-d')]
        );

        $resA = $this->actingAs($admin)
            ->post(route('admin.devoluciones.aprobar', $devA->id), ['confirmacion_revision' => '1']);

        $resA->assertSessionHas('error');
        $this->assertStringContainsString('anterior a la fecha de hallazgo', session('error'));
        $this->assertEquals(Devolucion::ESTADO_PENDIENTE, $devA->fresh()->estado);

        // Caso B: Fecha entrega futura
        [$adminB, $otroB, $autorB, $uB, $pubB, $devB] = $this->crearDevolucionFixture(
            ['fecha_hallazgo' => $ahora->copy()->subDays(2)->format('Y-m-d')],
            ['fecha_entrega' => $ahora->copy()->addDays(2)->format('Y-m-d')]
        );

        $resB = $this->actingAs($adminB)
            ->post(route('admin.devoluciones.aprobar', $devB->id), ['confirmacion_revision' => '1']);

        $resB->assertSessionHas('error');
        $this->assertEquals(Devolucion::ESTADO_PENDIENTE, $devB->fresh()->estado);
    }

    /**
     * 6. Solicitud de corrección válida:
     * - Cambia a «Requiere corrección»
     * - Guarda motivo, admin y fecha
     * - Conserva datos y fotografía
     */
    public function test_06_solicitud_de_correccion_valida(): void
    {
        [$admin, $otroAdmin, $autor, $usuarioGeneral, $publicacion, $devolucion] = $this->crearDevolucionFixture();

        $motivo = 'La fotografía del comprobante de propiedad no es nítida, por favor captura una imagen con mejor iluminación.';

        $response = $this->actingAs($admin)
            ->post(route('admin.devoluciones.solicitar-correccion', $devolucion->id), [
                'motivo' => $motivo,
            ]);

        $response->assertRedirect(route('admin.devoluciones.show', $devolucion->id));
        $response->assertSessionHas('status', 'Solicitud de corrección registrada correctamente.');

        $devFresca = $devolucion->fresh();
        $this->assertEquals(Devolucion::ESTADO_REQUIERE_CORRECCION, $devFresca->estado);
        $this->assertEquals($motivo, $devFresca->motivo_correccion);
        $this->assertEquals($admin->id, $devFresca->admin_id);
        $this->assertNotNull($devFresca->fecha_resolucion);

        // Conserva datos del receptor y foto
        $this->assertEquals('Eduardo Pech Canul', $devFresca->receptor_nombre);
        $this->assertNotNull($devFresca->fotografia_ruta);
        $this->assertTrue(Storage::disk('local')->exists($devFresca->fotografia_ruta));
    }

    /**
     * 7. Motivo vacío o con espacios: Es rechazado y no modifica el estado.
     */
    public function test_07_rechaza_motivo_vacio_o_con_espacios(): void
    {
        [$admin, $otroAdmin, $autor, $usuarioGeneral, $publicacion, $devolucion] = $this->crearDevolucionFixture();

        // Vacío
        $resVacio = $this->actingAs($admin)
            ->from(route('admin.devoluciones.show', $devolucion->id))
            ->post(route('admin.devoluciones.solicitar-correccion', $devolucion->id), [
                'motivo' => '',
            ]);

        $resVacio->assertRedirect(route('admin.devoluciones.show', $devolucion->id));
        $resVacio->assertSessionHasErrors('motivo');

        // Solo espacios
        $resEspacios = $this->actingAs($admin)
            ->from(route('admin.devoluciones.show', $devolucion->id))
            ->post(route('admin.devoluciones.solicitar-correccion', $devolucion->id), [
                'motivo' => '      ',
            ]);

        $resEspacios->assertRedirect(route('admin.devoluciones.show', $devolucion->id));
        $resEspacios->assertSessionHasErrors('motivo');

        $this->assertEquals(Devolucion::ESTADO_PENDIENTE, $devolucion->fresh()->estado);
    }

    /**
     * 8. Permanencia fuera del muro:
     * Ni las aprobadas ni las que requieren corrección aparecen en el muro público.
     */
    public function test_08_permanencia_fuera_del_muro(): void
    {
        // Devolución A: Aprobada
        [$admin, $otroAdmin, $autor, $usuarioGeneral, $pubA, $devA] = $this->crearDevolucionFixture(['folio' => 'FOL-WALL-APROBADA']);
        $this->actingAs($admin)->post(route('admin.devoluciones.aprobar', $devA->id), ['confirmacion_revision' => '1']);

        // Devolución B: En corrección
        [$adminB, $otroB, $autorB, $uB, $pubB, $devB] = $this->crearDevolucionFixture(['folio' => 'FOL-WALL-CORRECCION']);
        $this->actingAs($adminB)->post(route('admin.devoluciones.solicitar-correccion', $devB->id), ['motivo' => 'Corrección requerida']);

        $resMuro = $this->get(route('muro'));
        $resMuro->assertDontSee('FOL-WALL-APROBADA');
        $resMuro->assertDontSee('FOL-WALL-CORRECCION');
    }

    /**
     * 9. Registro correcto del administrador y fecha:
     * Comprueba persistencia en base de datos.
     */
    public function test_09_registro_correcto_de_administrador_y_fecha(): void
    {
        [$admin, $otroAdmin, $autor, $usuarioGeneral, $publicacion, $devolucion] = $this->crearDevolucionFixture();

        $this->actingAs($admin)->post(route('admin.devoluciones.aprobar', $devolucion->id), ['confirmacion_revision' => '1']);

        $dev = $devolucion->fresh();
        $this->assertEquals($admin->id, $dev->admin_id);
        $this->assertInstanceOf(Carbon::class, $dev->fecha_resolucion);
    }

    /**
     * 10. Historial de decisiones:
     * Verifica que se cree el registro en DecisionModeracion.
     */
    public function test_10_registro_en_historial_de_decisiones(): void
    {
        [$admin, $otroAdmin, $autor, $usuarioGeneral, $publicacion, $devolucion] = $this->crearDevolucionFixture();

        $this->actingAs($admin)->post(route('admin.devoluciones.aprobar', $devolucion->id), ['confirmacion_revision' => '1']);

        $decision = DecisionModeracion::where('publicacion_id', $publicacion->id)->latest()->first();
        $this->assertNotNull($decision);
        $this->assertEquals($admin->id, $decision->user_id);
        $this->assertEquals(DecisionModeracion::TIPO_DEVOLUCION_APROBADA, $decision->tipo_decision);
    }

    /**
     * 11. Imposibilidad de aprobar dos veces:
     * La segunda aprobación es rechazada y no duplica decisiones.
     */
    public function test_11_imposibilidad_de_aprobar_dos_veces(): void
    {
        [$admin, $otroAdmin, $autor, $usuarioGeneral, $publicacion, $devolucion] = $this->crearDevolucionFixture();

        // Primera aprobación
        $res1 = $this->actingAs($admin)->post(route('admin.devoluciones.aprobar', $devolucion->id), ['confirmacion_revision' => '1']);
        $res1->assertSessionHas('status');

        $conteoDecisiones1 = DecisionModeracion::where('publicacion_id', $publicacion->id)->count();

        // Segunda aprobación
        $res2 = $this->actingAs($admin)->post(route('admin.devoluciones.aprobar', $devolucion->id), ['confirmacion_revision' => '1']);
        $res2->assertSessionHas('error', 'El estado de la devolución cambió. Consulta su información actual antes de continuar.');

        $conteoDecisiones2 = DecisionModeracion::where('publicacion_id', $publicacion->id)->count();
        $this->assertEquals($conteoDecisiones1, $conteoDecisiones2);
    }

    /**
     * 12. Conflicto entre administradores (concurrencia / pantalla desactualizada):
     * Muestra mensaje exacto: «El estado de la devolución cambió. Consulta su información actual antes de continuar».
     */
    public function test_12_conflicto_entre_administradores(): void
    {
        [$admin1, $admin2, $autor, $usuarioGeneral, $publicacion, $devolucion] = $this->crearDevolucionFixture();

        // Admin 1 aprueba la devolución
        $this->actingAs($admin1)->post(route('admin.devoluciones.aprobar', $devolucion->id), ['confirmacion_revision' => '1']);

        // Admin 2 intenta solicitar corrección con la pantalla desactualizada
        $responseAdmin2 = $this->actingAs($admin2)
            ->post(route('admin.devoluciones.solicitar-correccion', $devolucion->id), [
                'motivo' => 'Falta foto del reverso del objeto entregado.',
            ]);

        $responseAdmin2->assertRedirect(route('admin.devoluciones.show', $devolucion->id));
        $responseAdmin2->assertSessionHas('error', 'El estado de la devolución cambió. Consulta su información actual antes de continuar.');

        // El estado sigue siendo Aprobada/Verificada, no fue sobrescrito
        $this->assertTrue($devolucion->fresh()->isAprobada());
    }

    /**
     * 13. Acceso denegado a usuarios sin permiso:
     * Usuario general recibe 403 y visitante es redirigido a login.
     */
    public function test_13_acceso_denegado_a_usuarios_sin_permiso(): void
    {
        [$admin, $otroAdmin, $autor, $usuarioGeneral, $publicacion, $devolucion] = $this->crearDevolucionFixture();

        // Usuario general
        $this->actingAs($usuarioGeneral)
            ->post(route('admin.devoluciones.aprobar', $devolucion->id), ['confirmacion_revision' => '1'])
            ->assertForbidden();

        $this->actingAs($usuarioGeneral)
            ->post(route('admin.devoluciones.solicitar-correccion', $devolucion->id), ['motivo' => 'Prueba'])
            ->assertForbidden();

        // Visitante
        auth()->logout();
        $this->post(route('admin.devoluciones.aprobar', $devolucion->id), ['confirmacion_revision' => '1'])
            ->assertRedirect(route('login'));

        $this->post(route('admin.devoluciones.solicitar-correccion', $devolucion->id), ['motivo' => 'Prueba'])
            ->assertRedirect(route('login'));
    }

    /**
     * 14. Reversión de cambios ante errores:
     * Una falla en la transacción revierte los cambios completamente.
     */
    public function test_14_reversion_de_cambios_ante_errores(): void
    {
        [$admin, $otroAdmin, $autor, $usuarioGeneral, $publicacion, $devolucion] = $this->crearDevolucionFixture();

        $dispatcher = DecisionModeracion::getEventDispatcher();
        try {
            // Interceptamos la creación en DecisionModeracion para simular error inesperado
            DecisionModeracion::creating(function () {
                throw new \RuntimeException('Falla simulada de base de datos en transacción.');
            });

            $response = $this->actingAs($admin)
                ->post(route('admin.devoluciones.aprobar', $devolucion->id), ['confirmacion_revision' => '1']);

            $response->assertRedirect(route('admin.devoluciones.show', $devolucion->id));
            $response->assertSessionHas('error');

            // Rollback comprobado
            $this->assertEquals(Devolucion::ESTADO_PENDIENTE, $devolucion->fresh()->estado);
            $this->assertEquals(Publicacion::ESTADO_APROBADA, $publicacion->fresh()->estado);
        } finally {
            DecisionModeracion::setEventDispatcher($dispatcher);
        }
    }

    /**
     * 15. Integración con avisos si HU-14 está disponible:
     * Despacha los eventos de negocio desacoplados DevolucionAprobada y DevolucionCorreccionSolicitada.
     */
    public function test_15_integracion_con_avisos_despacha_eventos_de_negocio(): void
    {
        Event::fake([DevolucionAprobada::class, DevolucionCorreccionSolicitada::class]);

        [$admin, $otroAdmin, $autor, $usuarioGeneral, $pub1, $dev1] = $this->crearDevolucionFixture(['folio' => 'FOL-EV-1']);
        [$admin2, $otro2, $autor2, $u2, $pub2, $dev2] = $this->crearDevolucionFixture(['folio' => 'FOL-EV-2']);

        // Aprobación
        $this->actingAs($admin)->post(route('admin.devoluciones.aprobar', $dev1->id), ['confirmacion_revision' => '1']);
        Event::assertDispatched(DevolucionAprobada::class, function ($e) use ($dev1, $admin) {
            return $e->devolucion->id === $dev1->id && $e->admin->id === $admin->id;
        });

        // Corrección
        $this->actingAs($admin2)->post(route('admin.devoluciones.solicitar-correccion', $dev2->id), ['motivo' => 'Foto borrosa']);
        Event::assertDispatched(DevolucionCorreccionSolicitada::class, function ($e) use ($dev2, $admin2) {
            return $e->devolucion->id === $dev2->id && $e->admin->id === $admin2->id && $e->motivo === 'Foto borrosa';
        });
    }
}
