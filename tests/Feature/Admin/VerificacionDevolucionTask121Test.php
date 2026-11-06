<?php

namespace Tests\Feature\Admin;

use App\Models\Devolucion;
use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerificacionDevolucionTask121Test extends TestCase
{
    use RefreshDatabase;

    private function crearDevolucionFixture(array $pubAttrs = [], array $devAttrs = []): array
    {
        Storage::fake('local');

        $autor = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $usuarioGeneral = User::factory()->create(['role' => User::ROLE_USUARIO_GENERAL]);

        $ahora = Carbon::now(Publicacion::TIMEZONE);

        $publicacion = Publicacion::factory()->create(array_merge([
            'user_id' => $autor->id,
            'nombre' => 'Calculadora Casio fx-991LAX',
            'descripcion' => 'Calculadora científica color negro con tapa plateada',
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

        return [$admin, $autor, $usuarioGeneral, $publicacion, $devolucion];
    }

    /**
     * 1. El administrador puede consultar la cola.
     */
    public function test_01_administrador_puede_consultar_la_cola(): void
    {
        [$admin, $autor, $usuarioGeneral, $publicacion, $devolucion] = $this->crearDevolucionFixture();

        $response = $this->actingAs($admin)->get(route('admin.devoluciones.index'));

        $response->assertOk();
        $response->assertSee('Verificación de devoluciones');
        $response->assertSee('Cola de devoluciones pendientes de verificación');
        $response->assertSee('Entregas y Devoluciones');
        $response->assertSee($publicacion->folio);
        $response->assertSee($publicacion->nombre);
        $response->assertSee($autor->name);
        $response->assertSee('Pendiente de verificación');
        $response->assertSee(route('admin.devoluciones.show', $devolucion->id));
    }

    /**
     * 2. Los registros aparecen ordenados correctamente (del más antiguo al más reciente).
     */
    public function test_02_registros_aparecen_ordenados_correctamente_antiguo_a_reciente(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        // Registro A: Más antiguo (hace 4 días)
        [$adminA, $autorA, $uA, $pubA, $devA] = $this->crearDevolucionFixture(
            ['folio' => 'FOL-001', 'nombre' => 'Objeto Primero Antiguo'],
            ['fecha_captura' => $ahora->copy()->subDays(4), 'fecha_envio_revision' => $ahora->copy()->subDays(4)]
        );

        // Registro B: Intermedio (hace 2 días)
        [$adminB, $autorB, $uB, $pubB, $devB] = $this->crearDevolucionFixture(
            ['folio' => 'FOL-002', 'nombre' => 'Objeto Segundo Intermedio'],
            ['fecha_captura' => $ahora->copy()->subDays(2), 'fecha_envio_revision' => $ahora->copy()->subDays(2)]
        );

        // Registro C: Más reciente (hace 1 hora)
        [$adminC, $autorC, $uC, $pubC, $devC] = $this->crearDevolucionFixture(
            ['folio' => 'FOL-003', 'nombre' => 'Objeto Tercero Reciente'],
            ['fecha_captura' => $ahora->copy()->subHour(), 'fecha_envio_revision' => $ahora->copy()->subHour()]
        );

        $response = $this->actingAs($admin)->get(route('admin.devoluciones.index'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'FOL-001',
            'FOL-002',
            'FOL-003',
        ]);
    }

    /**
     * 3. El último reenvío determina el orden.
     */
    public function test_03_ultimo_reenvio_determina_el_orden(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        // Registro A: Capturado hace 6 días, pero reenviado hoy
        [$adminA, $autorA, $uA, $pubA, $devA] = $this->crearDevolucionFixture(
            ['folio' => 'FOL-REENVIADO-HOY', 'nombre' => 'Mochila Reenviada'],
            [
                'fecha_captura' => $ahora->copy()->subDays(6),
                'fecha_envio_revision' => $ahora->copy(), // Reenviado hoy
            ]
        );

        // Registro B: Capturado y enviado hace 3 días, sin reenvío posterior
        [$adminB, $autorB, $uB, $pubB, $devB] = $this->crearDevolucionFixture(
            ['folio' => 'FOL-ENVIADO-HACE-3-DIAS', 'nombre' => 'Audífonos Pendientes'],
            [
                'fecha_captura' => $ahora->copy()->subDays(3),
                'fecha_envio_revision' => $ahora->copy()->subDays(3),
            ]
        );

        $response = $this->actingAs($admin)->get(route('admin.devoluciones.index'));

        $response->assertOk();
        // El de hace 3 días es más antiguo que el reenviado hoy, por lo que debe aparecer primero
        $response->assertSeeInOrder([
            'FOL-ENVIADO-HACE-3-DIAS',
            'FOL-REENVIADO-HOY',
        ]);
    }

    /**
     * 4. Las devoluciones aprobadas no aparecen.
     */
    public function test_04_devoluciones_aprobadas_no_aparecen(): void
    {
        [$admin, $autor, $usuarioGeneral, $pubPendiente, $devPendiente] = $this->crearDevolucionFixture(
            ['folio' => 'FOL-PENDIENTE-01'],
            ['estado' => Devolucion::ESTADO_PENDIENTE]
        );

        [$admin2, $autor2, $usuario2, $pubAprobada, $devAprobada] = $this->crearDevolucionFixture(
            ['folio' => 'FOL-APROBADA-02'],
            ['estado' => Devolucion::ESTADO_VERIFICADA]
        );

        $response = $this->actingAs($admin)->get(route('admin.devoluciones.index'));

        $response->assertOk();
        $response->assertSee('FOL-PENDIENTE-01');
        $response->assertDontSee('FOL-APROBADA-02');
    }

    /**
     * 5. Las devoluciones que requieren corrección no aparecen.
     */
    public function test_05_devoluciones_que_requieren_correccion_no_aparecen(): void
    {
        [$admin, $autor, $usuarioGeneral, $pubPendiente, $devPendiente] = $this->crearDevolucionFixture(
            ['folio' => 'FOL-PENDIENTE-02'],
            ['estado' => Devolucion::ESTADO_PENDIENTE]
        );

        [$admin2, $autor2, $usuario2, $pubCorregir, $devCorregir] = $this->crearDevolucionFixture(
            ['folio' => 'FOL-CORRECCION-03'],
            ['estado' => 'Requiere corrección']
        );

        $response = $this->actingAs($admin)->get(route('admin.devoluciones.index'));

        $response->assertOk();
        $response->assertSee('FOL-PENDIENTE-02');
        $response->assertDontSee('FOL-CORRECCION-03');
    }

    /**
     * 6. Se muestra el mensaje cuando la cola está vacía: «No hay devoluciones pendientes de verificación».
     */
    public function test_06_se_muestra_mensaje_cuando_la_cola_esta_vacia(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.devoluciones.index'));

        $response->assertOk();
        $response->assertSee('No hay devoluciones pendientes de verificación');
    }

    /**
     * 7. El detalle corresponde al reporte correcto.
     */
    public function test_07_detalle_corresponde_al_reporte_correcto(): void
    {
        [$admin, $autorA, $uA, $pubA, $devA] = $this->crearDevolucionFixture(
            ['folio' => 'FOL-DETALLE-A', 'nombre' => 'Laptop HP Gris'],
            [
                'receptor_nombre' => 'Ana Karen Pool',
                'receptor_identificador' => '18080999',
                'explicacion_propiedad' => 'La persona desbloqueó la laptop con su contraseña en presencia del autor.',
            ]
        );

        [$adminB, $autorB, $uB, $pubB, $devB] = $this->crearDevolucionFixture(
            ['folio' => 'FOL-DETALLE-B', 'nombre' => 'Mochila Roja Puma'],
            [
                'receptor_nombre' => 'Carlos Tun Santos',
                'receptor_identificador' => '21080444',
            ]
        );

        $response = $this->actingAs($admin)->get(route('admin.devoluciones.show', $devA->id));

        $response->assertOk();
        $response->assertSee('FOL-DETALLE-A');
        $response->assertSee('Laptop HP Gris');
        $response->assertSee('Ana Karen Pool');
        $response->assertSee('18080999');
        $response->assertSee('La persona desbloqueó la laptop con su contraseña en presencia del autor.');

        // Asegurar que no mezcla datos del reporte B
        $response->assertDontSee('FOL-DETALLE-B');
        $response->assertDontSee('Carlos Tun Santos');
    }

    /**
     * 8. Las fechas de entrega y registro se distinguen claramente.
     */
    public function test_08_las_fechas_de_entrega_y_registro_se_distinguen(): void
    {
        [$admin, $autor, $u, $pub, $dev] = $this->crearDevolucionFixture([], [
            'fecha_entrega' => '2026-11-02',
            'hora_entrega' => '14:45',
            'fecha_captura' => Carbon::create(2026, 11, 4, 18, 20, 0, Publicacion::TIMEZONE),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.devoluciones.show', $dev->id));

        $response->assertOk();
        $response->assertSee('Fecha de entrega física');
        $response->assertSee('Fecha de registro');
        $response->assertSee($dev->fecha_entrega_texto);
        $response->assertSee($dev->fecha_captura_texto);
    }

    /**
     * 9. Un usuario general no puede acceder.
     */
    public function test_09_un_usuario_general_no_puede_acceder(): void
    {
        [$admin, $autor, $usuarioGeneral, $pub, $dev] = $this->crearDevolucionFixture();

        // Acceso a la cola
        $responseCola = $this->actingAs($usuarioGeneral)->get(route('admin.devoluciones.index'));
        $responseCola->assertForbidden();

        // Acceso al detalle administrativo
        $responseDetalle = $this->actingAs($usuarioGeneral)->get(route('admin.devoluciones.show', $dev->id));
        $responseDetalle->assertForbidden();

        // No debe ver el enlace en su menú de navegación
        $responseMisReportes = $this->actingAs($usuarioGeneral)->get(route('reportes.mis-reportes'));
        $responseMisReportes->assertDontSee('Entregas y Devoluciones');
    }

    /**
     * 10. Un visitante no puede acceder.
     */
    public function test_10_un_visitante_no_puede_acceder(): void
    {
        [$admin, $autor, $usuarioGeneral, $pub, $dev] = $this->crearDevolucionFixture();

        // Acceso a la cola
        $responseCola = $this->get(route('admin.devoluciones.index'));
        $responseCola->assertRedirect(route('login'));

        // Acceso al detalle administrativo
        $responseDetalle = $this->get(route('admin.devoluciones.show', $dev->id));
        $responseDetalle->assertRedirect(route('login'));
    }

    /**
     * 11. Los archivos privados no quedan expuestos.
     */
    public function test_11_los_archivos_privados_no_quedan_expuestos(): void
    {
        [$admin, $autor, $usuarioGeneral, $pub, $dev] = $this->crearDevolucionFixture();

        // Visitante es redirigido al login
        $resVisitante = $this->get(route('devoluciones.evidencia', $dev->id));
        $resVisitante->assertRedirect(route('login'));

        // Usuario general ajeno recibe 403 Forbidden
        $resUsuario = $this->actingAs($usuarioGeneral)->get(route('devoluciones.evidencia', $dev->id));
        $resUsuario->assertForbidden();

        // Administrador tiene acceso legítimo
        $resAdmin = $this->actingAs($admin)->get(route('devoluciones.evidencia', $dev->id));
        $resAdmin->assertOk();
        $resAdmin->assertHeader('Content-Type', 'image/jpeg');
    }

    /**
     * 12. Los registros clasificados como cierre directo de biblioteca, si existen, quedan excluidos.
     */
    public function test_12_registros_clasificados_como_cierre_directo_de_biblioteca_quedan_excluidos(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        // Reporte con entrega normal del autor
        [$admin1, $autor1, $u1, $pubAutor, $devAutor] = $this->crearDevolucionFixture(
            ['folio' => 'FOL-AUTOR-RESGUARDO', 'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR]
        );

        // Reporte clasificado como resguardo confirmado de biblioteca (HU-18 / cierre directo)
        [$admin2, $autor2, $u2, $pubBiblioteca, $devBiblioteca] = $this->crearDevolucionFixture(
            ['folio' => 'FOL-BIBLIOTECA-RESGUARDO', 'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO]
        );

        $response = $this->actingAs($admin)->get(route('admin.devoluciones.index'));

        $response->assertOk();
        $response->assertSee('FOL-AUTOR-RESGUARDO');
        $response->assertDontSee('FOL-BIBLIOTECA-RESGUARDO');
    }
}
