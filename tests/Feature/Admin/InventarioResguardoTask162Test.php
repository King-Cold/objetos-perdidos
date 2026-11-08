<?php

namespace Tests\Feature\Admin;

use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\Recepcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventarioResguardoTask162Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Crea un escenario con un reporte recibido bajo resguardo en biblioteca.
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

        $primeraAprobacion = Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE);
        $vencimiento = Carbon::create(2026, 11, 15, 10, 0, 0, Publicacion::TIMEZONE);

        $reporteData = array_merge([
            'user_id' => $autor->id,
            'nombre' => 'Laptop Dell Latitude 3420',
            'descripcion' => 'Laptop negra con cargador original olvidada en sala de estudio.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'area_zona' => 'Edificio C',
            'lugar_hallazgo' => 'Laboratorio C-2',
            'situacion_resguardo' => 'En biblioteca',
            'fecha_hallazgo' => Carbon::create(2026, 10, 30, 9, 30, 0, Publicacion::TIMEZONE)->toDateString(),
            'hora_hallazgo' => '09:30:00',
            'fecha_captura' => Carbon::create(2026, 10, 30, 10, 0, 0, Publicacion::TIMEZONE),
            'fecha_primera_aprobacion' => $primeraAprobacion,
            'fecha_aprobacion' => $primeraAprobacion,
            'fecha_vencimiento' => $vencimiento,
            'devolucion_en_proceso' => false,
            'estado_devolucion' => null,
            'version' => 1,
        ], $overridesReporte);

        $reporte = Publicacion::create($reporteData);

        // Fotografía asociada
        $foto = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'fotografias/laptop.jpg',
            'nombre_original' => 'laptop.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 204800,
            'es_principal' => true,
        ]);

        $recepcionData = array_merge([
            'publicacion_id' => $reporte->id,
            'user_id' => $admin->id,
            'fecha_recepcion' => '2026-11-18',
            'hora_recepcion' => '10:30',
            'fecha_captura' => Carbon::create(2026, 11, 19, 14, 0, 0, Publicacion::TIMEZONE),
            'condicion_objeto' => 'Laptop en buen estado con leves rayones en la tapa exterior.',
            'ubicacion_resguardo' => 'Estante Tecnológico A-2, Gaveta 4',
            'observaciones' => 'Observaciones administrativas confidenciales de custodia interna.',
            'confirmacion_cotejo' => true,
            'fecha_limite_anual' => '2027-11-18',
        ], $overridesRecepcion);

        $recepcion = Recepcion::create($recepcionData);

        return [$admin, $autor, $reporte, $recepcion, $foto, $ahora];
    }

    /**
     * 1. Inventario visible para administrador.
     */
    public function test_01_inventario_visible_para_administrador(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        $response = $this->actingAs($admin)->get(route('admin.inventario.index'));

        $response->assertOk();
        $response->assertSee('Inventario de biblioteca');
        $response->assertSee($reporte->folio);
        $response->assertSee('Laptop Dell Latitude 3420');
        $response->assertSee('Ver detalle');
    }

    /**
     * 2. Acceso rechazado a usuarios generales.
     */
    public function test_02_acceso_rechazado_a_usuarios_generales(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo();
        $usuarioGeneral = User::factory()->create(['role' => User::ROLE_USUARIO_GENERAL]);

        // Listado
        $resIndex = $this->actingAs($usuarioGeneral)->get(route('admin.inventario.index'));
        $resIndex->assertForbidden();

        // Detalle
        $resShow = $this->actingAs($usuarioGeneral)->get(route('admin.inventario.show', $recepcion->id));
        $resShow->assertForbidden();
    }

    /**
     * 3. Acceso rechazado a visitantes.
     */
    public function test_03_acceso_rechazado_a_visitantes(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        // Listado
        $resIndex = $this->get(route('admin.inventario.index'));
        $resIndex->assertRedirect(route('login'));

        // Detalle
        $resShow = $this->get(route('admin.inventario.show', $recepcion->id));
        $resShow->assertRedirect(route('login'));
    }

    /**
     * 4. Orden por recepción más antigua.
     */
    public function test_04_orden_por_recepcion_mas_antigua(): void
    {
        $ahora = Carbon::create(2026, 11, 20, 11, 0, 0, Publicacion::TIMEZONE);
        Carbon::setTestNow($ahora);

        $admin = User::factory()->admin()->create();
        $autor = User::factory()->create();

        // Objeto 1: Recepción el 10 de noviembre
        $rep1 = Publicacion::create([
            'user_id' => $autor->id,
            'nombre' => 'Objeto Mediano (10 Nov)',
            'descripcion' => 'Descripción del objeto mediano para prueba de orden.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'situacion_resguardo' => 'En biblioteca',
            'fecha_vencimiento' => '2026-11-05',
        ]);
        $rec1 = Recepcion::create([
            'publicacion_id' => $rep1->id,
            'user_id' => $admin->id,
            'fecha_recepcion' => '2026-11-10',
            'hora_recepcion' => '10:00',
            'fecha_captura' => $ahora,
            'condicion_objeto' => 'Buen estado',
            'ubicacion_resguardo' => 'Estante 2',
            'confirmacion_cotejo' => true,
            'fecha_limite_anual' => '2027-11-10',
        ]);

        // Objeto 2: Recepción el 15 de noviembre (el más reciente)
        $rep2 = Publicacion::create([
            'user_id' => $autor->id,
            'nombre' => 'Objeto Reciente (15 Nov)',
            'descripcion' => 'Descripción del objeto más reciente para prueba de orden.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'situacion_resguardo' => 'En biblioteca',
            'fecha_vencimiento' => '2026-11-05',
        ]);
        $rec2 = Recepcion::create([
            'publicacion_id' => $rep2->id,
            'user_id' => $admin->id,
            'fecha_recepcion' => '2026-11-15',
            'hora_recepcion' => '12:00',
            'fecha_captura' => $ahora,
            'condicion_objeto' => 'Buen estado',
            'ubicacion_resguardo' => 'Estante 3',
            'confirmacion_cotejo' => true,
            'fecha_limite_anual' => '2027-11-15',
        ]);

        // Objeto 3: Recepción el 02 de noviembre (el más antiguo, creado al final en la BD)
        $rep3 = Publicacion::create([
            'user_id' => $autor->id,
            'nombre' => 'Objeto Antiguo (02 Nov)',
            'descripcion' => 'Descripción del objeto más antiguo para prueba de orden.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'situacion_resguardo' => 'En biblioteca',
            'fecha_vencimiento' => '2026-11-05',
        ]);
        $rec3 = Recepcion::create([
            'publicacion_id' => $rep3->id,
            'user_id' => $admin->id,
            'fecha_recepcion' => '2026-11-02',
            'hora_recepcion' => '09:00',
            'fecha_captura' => $ahora,
            'condicion_objeto' => 'Buen estado',
            'ubicacion_resguardo' => 'Estante 1',
            'confirmacion_cotejo' => true,
            'fecha_limite_anual' => '2027-11-02',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.inventario.index'));
        $response->assertOk();

        // Debe aparecer en orden cronológico ascendente (desde la recepción más antigua)
        $response->assertSeeInOrder([
            'Objeto Antiguo (02 Nov)',
            'Objeto Mediano (10 Nov)',
            'Objeto Reciente (15 Nov)',
        ]);
    }

    /**
     * 5. Información correcta de cada objeto en el inventario.
     */
    public function test_05_informacion_correcta_de_cada_objeto(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        $response = $this->actingAs($admin)->get(route('admin.inventario.index'));

        $response->assertOk();
        $response->assertSee($reporte->folio);
        $response->assertSee('Laptop Dell Latitude 3420');
        $response->assertSee('18/11/2026'); // Fecha de recepción física
        $response->assertSee('Laptop en buen estado con leves rayones'); // Condición
        $response->assertSee('Estante Tecnológico A-2, Gaveta 4'); // Ubicación interna
        $response->assertSee('18/11/2027'); // Fecha límite de resguardo
        $response->assertSee('Bajo resguardo'); // Estado del resguardo
    }

    /**
     * 6. Imagen correspondiente al reporte.
     */
    public function test_06_imagen_correspondiente_al_reporte(): void
    {
        [$admin, $autor, $reporte, $recepcion, $foto] = $this->crearEscenarioResguardo();

        $response = $this->actingAs($admin)->get(route('admin.inventario.index'));

        $response->assertOk();
        // La URL de la fotografía debe pertenecer al recurso seguro de la foto
        $response->assertSee(route('fotografias.show', $foto->id));
    }

    /**
     * 7. Detalle del resguardo completo y distinguido.
     */
    public function test_07_detalle_del_resguardo(): void
    {
        [$admin, $autor, $reporte, $recepcion, $foto] = $this->crearEscenarioResguardo();

        $response = $this->actingAs($admin)->get(route('admin.inventario.show', $recepcion->id));

        $response->assertOk();

        // 1. Información del objeto
        $response->assertSee($reporte->folio);
        $response->assertSee('Laptop Dell Latitude 3420');
        $response->assertSee('Laptop negra con cargador original olvidada en sala de estudio.');
        $response->assertSee($autor->name);
        $response->assertSee(route('fotografias.show', $foto->id));

        // 2. Información de recepción
        $response->assertSee('18/11/2026'); // Fecha física
        $response->assertSee('10:30'); // Hora física
        $response->assertSee('19/11/2026'); // Fecha de captura en sistema
        $response->assertSee('14:00'); // Hora de captura
        $response->assertSee($admin->name); // Administrador responsable
        $response->assertSee('Laptop en buen estado con leves rayones en la tapa exterior.'); // Condición
        $response->assertSee('Estante Tecnológico A-2, Gaveta 4'); // Ubicación interna
        $response->assertSee('Observaciones administrativas confidenciales de custodia interna.'); // Observaciones
        $response->assertSee('18/11/2027'); // Fecha límite anual
        $response->assertSee('Bajo resguardo'); // Estado actual
    }

    /**
     * 8. Mensaje exacto cuando no existen registros.
     */
    public function test_08_mensaje_cuando_no_existen_registros(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.inventario.index'));

        $response->assertOk();
        $response->assertSee('No hay objetos bajo resguardo en biblioteca');
    }

    /**
     * 9. Conservación de folio y autor tras consultar inventario.
     */
    public function test_09_conservacion_de_folio_y_autor(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        $folioAntes = $reporte->folio;
        $autorIdAntes = $reporte->user_id;

        $this->actingAs($admin)->get(route('admin.inventario.index'));
        $this->actingAs($admin)->get(route('admin.inventario.show', $recepcion->id));

        $reporte->refresh();
        $this->assertSame($folioAntes, $reporte->folio);
        $this->assertSame($autorIdAntes, $reporte->user_id);
    }

    /**
     * 10. Autor puede consultar recepción y fecha sin editar.
     */
    public function test_10_autor_puede_consultar_recepcion_y_fecha_sin_editar(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        // 1. Autor consulta el detalle de su reporte
        $resShow = $this->actingAs($autor)->get(route('reportes.show', $reporte->id));
        $resShow->assertOk();
        $resShow->assertSee('En biblioteca');
        $resShow->assertSee('18/11/2026'); // Fecha física de recepción
        $resShow->assertSee('Objeto recibido en biblioteca');

        // 2. Autor consulta Mis Reportes
        $resMisReportes = $this->actingAs($autor)->get(route('reportes.mis-reportes'));
        $resMisReportes->assertOk();
        $resMisReportes->assertSee('En biblioteca');
        $resMisReportes->assertSee('18/11/2026');

        // 3. Intento de edición está estrictamente bloqueado
        $resEdit = $this->actingAs($autor)->get(route('reportes.edit', $reporte->id));
        $resEdit->assertRedirect(route('reportes.mis-reportes'));
        $resEdit->assertSessionHas('warning');
    }

    /**
     * 11. Ubicación interna protegida frente al autor y terceros.
     */
    public function test_11_ubicacion_interna_protegida(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo([
            'ubicacion_resguardo' => 'Estante Confidencial B-7, Gaveta Secreta',
        ]);
        $otroUsuario = User::factory()->create();

        // 1. Autor en su detalle privado no ve la ubicación interna
        $resAutor = $this->actingAs($autor)->get(route('reportes.show', $reporte->id));
        $resAutor->assertOk();
        $resAutor->assertDontSee('Estante Confidencial B-7, Gaveta Secreta');

        // 2. Autor en Mis Reportes no ve la ubicación interna
        $resMisReportes = $this->actingAs($autor)->get(route('reportes.mis-reportes'));
        $resMisReportes->assertOk();
        $resMisReportes->assertDontSee('Estante Confidencial B-7, Gaveta Secreta');

        // 3. Tercero no tiene acceso
        $resOtro = $this->actingAs($otroUsuario)->get(route('reportes.show', $reporte->id));
        $resOtro->assertForbidden();

        // 4. Muro público no muestra la ubicación interna
        $resMuro = $this->get(route('muro'));
        $resMuro->assertDontSee('Estante Confidencial B-7, Gaveta Secreta');
    }

    /**
     * 12. Observaciones administrativas protegidas frente al autor y terceros.
     */
    public function test_12_observaciones_administrativas_protegidas(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo([
            'observaciones' => 'Nota administrativa interna confidencial para el bibliotecario.',
        ]);

        // Autor en su detalle privado no ve las observaciones administrativas
        $resAutor = $this->actingAs($autor)->get(route('reportes.show', $reporte->id));
        $resAutor->assertOk();
        $resAutor->assertDontSee('Nota administrativa interna confidencial para el bibliotecario.');

        // Autor en Mis Reportes no las ve
        $resMisReportes = $this->actingAs($autor)->get(route('reportes.mis-reportes'));
        $resMisReportes->assertOk();
        $resMisReportes->assertDontSee('Nota administrativa interna confidencial para el bibliotecario.');

        // Administrador sí puede verlas en el detalle del inventario
        $resAdmin = $this->actingAs($admin)->get(route('admin.inventario.show', $recepcion->id));
        $resAdmin->assertOk();
        $resAdmin->assertSee('Nota administrativa interna confidencial para el bibliotecario.');
    }

    /**
     * 13. El objeto no vuelve al muro público.
     */
    public function test_13_el_objeto_no_vuelve_al_muro(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        $this->assertFalse($reporte->disponible);
        $this->assertFalse($reporte->isDisponible());

        // No aparece en el muro general
        $resMuro = $this->get(route('muro'));
        $resMuro->assertOk();
        $resMuro->assertDontSee($reporte->folio);

        // Búsqueda en el muro no lo muestra
        $resBusqueda = $this->get(route('muro', ['buscar' => 'Latitude 3420']));
        $resBusqueda->assertOk();
        $resBusqueda->assertDontSee($reporte->folio);

        // Detalle público no accesible
        $resDetalle = $this->get(route('publicaciones.show', $reporte->id));
        $resDetalle->assertRedirect();
        $resDetalle->assertSessionHas('warning', 'Esta publicación no está disponible para consulta');
    }

    /**
     * 14. El inventario no modifica los datos al consultarlo.
     */
    public function test_14_el_inventario_no_modifica_los_datos_al_consultarlo(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        $recepcionSnapshot = $recepcion->fresh()->toArray();
        $reporteSnapshot = $reporte->fresh()->toArray();

        // Múltiples consultas al listado y al detalle
        $this->actingAs($admin)->get(route('admin.inventario.index'));
        $this->actingAs($admin)->get(route('admin.inventario.show', $recepcion->id));
        $this->actingAs($admin)->get(route('admin.inventario.index'));

        $this->assertEquals($recepcionSnapshot, $recepcion->fresh()->toArray());
        $this->assertEquals($reporteSnapshot, $reporte->fresh()->toArray());
    }
}
