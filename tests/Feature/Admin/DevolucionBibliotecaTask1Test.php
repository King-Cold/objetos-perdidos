<?php

namespace Tests\Feature\Admin;

use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\Recepcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevolucionBibliotecaTask1Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Crea un escenario con un reporte bajo resguardo físico en biblioteca.
     */
    protected function crearEscenarioResguardo(array $overridesRecepcion = [], array $overridesReporte = []): array
    {
        $ahora = Carbon::create(2026, 11, 20, 11, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($ahora);

        $admin = User::factory()->admin()->create([
            'email' => 'admin.biblioteca@itmerida.mx',
            'name' => 'Lic. María González (Bibliotecaria)',
        ]);

        $autor = User::factory()->create([
            'email' => 'autor.estudiante@itmerida.mx',
            'name' => 'Carlos Estudiante',
        ]);

        $otroUsuario = User::factory()->create([
            'email' => 'otro.usuario@itmerida.mx',
            'name' => 'Ana Alumna',
        ]);

        $primeraAprobacion = Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE);
        $vencimiento = Carbon::create(2026, 11, 15, 10, 0, 0, Publicacion::TIMEZONE);

        $reporteData = array_merge([
            'user_id' => $autor->id,
            'nombre' => 'Laptop Lenovo ThinkPad T14',
            'descripcion' => 'Laptop negra con cargador y calcomanía ITM olvidada en biblioteca.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'area_zona' => 'Edificio D',
            'lugar_hallazgo' => 'Módulo de estudio D-2',
            'situacion_resguardo' => 'En biblioteca',
            'fecha_hallazgo' => Carbon::create(2026, 10, 30, 9, 30, 0, Publicacion::TIMEZONE)->toDateString(),
            'hora_hallazgo' => '09:30:00',
            'fecha_captura' => Carbon::create(2026, 10, 30, 10, 0, 0, Publicacion::TIMEZONE),
            'fecha_primera_aprobacion' => $primeraAprobacion,
            'fecha_aprobacion' => $primeraAprobacion,
            'fecha_vencimiento' => $vencimiento,
            'devolucion_en_proceso' => false,
            'estado_devolucion' => null,
            'tiene_incidencia' => false,
            'incidencia_abierta' => false,
        ], $overridesReporte);

        $reporte = Publicacion::create($reporteData);

        Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'publicaciones/foto_thinkpad.jpg',
            'nombre_original' => 'foto_thinkpad.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 120000,
            'es_principal' => true,
        ]);

        $recepcionData = array_merge([
            'publicacion_id' => $reporte->id,
            'user_id' => $admin->id,
            'actualizado_por_id' => $admin->id,
            'fecha_recepcion' => Carbon::create(2026, 11, 16, 9, 0, 0, Recepcion::TIMEZONE)->toDateString(),
            'hora_recepcion' => '09:00:00',
            'fecha_captura' => Carbon::create(2026, 11, 16, 9, 15, 0, Recepcion::TIMEZONE),
            'condicion_objeto' => 'En buen estado, pantalla intacta',
            'ubicacion_resguardo' => 'Estante B - Nivel 2',
            'observaciones' => 'Se recibe sin funda',
            'confirmacion_cotejo' => true,
            'fecha_limite_anual' => Carbon::create(2027, 11, 16, 9, 0, 0, Recepcion::TIMEZONE)->toDateString(),
        ], $overridesRecepcion);

        $recepcion = Recepcion::create($recepcionData);

        return [$admin, $autor, $otroUsuario, $reporte, $recepcion];
    }

    /**
     * CA-01: Opción «Registrar devolución desde biblioteca» disponible para administrador/bibliotecario.
     */
    public function test_01_opcion_disponible_para_administrador_en_inventario(): void
    {
        [$admin, $autor, $otroUsuario, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        // En el detalle del inventario de biblioteca
        $resShow = $this->actingAs($admin)->get(route('admin.inventario.show', $recepcion->id));
        $resShow->assertOk();
        $resShow->assertSee('Registrar devolución desde biblioteca');
        $resShow->assertSee(route('admin.devoluciones-biblioteca.create', $reporte->id));
    }

    /**
     * CA-01: Restricción de acceso para usuarios generales y visitantes (403 / Redirección).
     */
    public function test_02_acceso_restringido_a_usuarios_no_autorizados(): void
    {
        [$admin, $autor, $otroUsuario, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        // Visitante sin autenticación redirige a login
        $resVisitante = $this->get(route('admin.devoluciones-biblioteca.create', $reporte->id));
        $resVisitante->assertRedirect(route('login'));

        // Autor del reporte (Usuario general) recibe 403
        $resAutor = $this->actingAs($autor)->get(route('admin.devoluciones-biblioteca.create', $reporte->id));
        $resAutor->assertStatus(403);

        // Otro usuario general recibe 403
        $resOtro = $this->actingAs($otroUsuario)->get(route('admin.devoluciones-biblioteca.create', $reporte->id));
        $resOtro->assertStatus(403);
    }

    /**
     * CA-01: Disponible incluso si la publicación ha expirado o finalizó el periodo anual de resguardo.
     */
    public function test_03_disponible_con_publicacion_expirada_y_resguardo_anual_vencido(): void
    {
        [$admin, $autor, $otroUsuario, $reporte, $recepcion] = $this->crearEscenarioResguardo([
            'fecha_recepcion' => '2024-02-15',
            'fecha_limite_anual' => '2025-02-15',
        ], [
            'fecha_vencimiento' => Carbon::create(2024, 2, 10, 10, 0, 0, Publicacion::TIMEZONE),
        ]);

        // Simular que estamos en 2026 (tanto la publicación de 14 días como el año de resguardo están vencidos)
        $this->assertTrue($reporte->isVencida());
        $this->assertTrue($recepcion->isAnualVencido());
        $this->assertTrue($reporte->puedeRegistrarDevolucionDesdeBiblioteca());

        $response = $this->actingAs($admin)->get(route('admin.devoluciones-biblioteca.create', $reporte->id));
        $response->assertOk();
        $response->assertSee('Registrar devolución desde biblioteca');
        $response->assertSee('Resguardo vencido');
    }

    /**
     * CA-01: No disponible si el objeto ya tiene una salida registrada o está devuelto.
     */
    public function test_04_no_disponible_si_el_objeto_ya_esta_devuelto(): void
    {
        [$admin, $autor, $otroUsuario, $reporte, $recepcion] = $this->crearEscenarioResguardo([], [
            'estado' => Publicacion::ESTADO_DEVUELTO,
            'estado_devolucion' => 'Devuelto',
        ]);

        $this->assertFalse($reporte->puedeRegistrarDevolucionDesdeBiblioteca());

        $resShow = $this->actingAs($admin)->get(route('admin.inventario.show', $recepcion->id));
        $resShow->assertDontSee(route('admin.devoluciones-biblioteca.create', $reporte->id));

        $resCreate = $this->actingAs($admin)->get(route('admin.devoluciones-biblioteca.create', $reporte->id));
        $resCreate->assertRedirect(route('admin.inventario.index'));
        $resCreate->assertSessionHas('error');
    }

    /**
     * CA-02: Identificación del objeto sin permitir cambiar el objeto seleccionado.
     * Muestra folio, nombre, fotografías de referencia y ubicación interna.
     */
    public function test_05_identificacion_completa_del_objeto_y_ubicacion_interna(): void
    {
        [$admin, $autor, $otroUsuario, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        $response = $this->actingAs($admin)->get(route('admin.devoluciones-biblioteca.create', $reporte->id));
        $response->assertOk();

        // Folio y Nombre
        $response->assertSee($reporte->folio);
        $response->assertSee('Laptop Lenovo ThinkPad T14');

        // Ubicación interna en biblioteca
        $response->assertSee('Estante B - Nivel 2');
        $response->assertSee('Ubicación interna en biblioteca');

        // Fotografías de referencia
        $response->assertSee(route('fotografias.show', $reporte->fotografias->first()->id));

        // No permite cambiar el objeto seleccionado (no hay selector de otro reporte)
        $response->assertSee('Objeto fijo / No modificable');
        $response->assertDontSee('<select name="publicacion_id"', false);
    }

    /**
     * CA-04: El formulario privado contiene todos los campos obligatorios y vista previa de foto.
     */
    public function test_06_formulario_solicita_campos_obligatorios_y_evidencia_con_preview(): void
    {
        [$admin, $autor, $otroUsuario, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        $response = $this->actingAs($admin)->get(route('admin.devoluciones-biblioteca.create', $reporte->id));
        $response->assertOk();

        // Campos requeridos en la interfaz
        $response->assertSee('name="receptor_nombre"', false);
        $response->assertSee('name="receptor_identificador"', false);
        $response->assertSee('name="fecha_entrega"', false);
        $response->assertSee('name="hora_entrega"', false);
        $response->assertSee('name="explicacion_propiedad"', false);
        $response->assertSee('name="fotografia"', false);

        // Indicadores de vista previa y tipos JPG y PNG hasta 2 MB
        $response->assertSee('manejarVistaPreviaBiblioteca');
        $response->assertSee('eliminarFotoSeleccionadaBiblioteca');
        $response->assertSee('JPG o PNG');
        $response->assertDontSee('JPEG');
        $response->assertSee('2 MB');

        // Enlace de cancelación seguro hacia el resguardo institucional
        $response->assertSee(route('admin.inventario.show', $recepcion->id));
    }
}

