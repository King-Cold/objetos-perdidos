<?php

namespace Tests\Feature\Admin;

use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeracionPendientesTask071Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Prueba 1: Acceso permitido al Administrador / Bibliotecario.
     * Requerimiento 1 y 2: Apartado «Moderación de reportes» y autorización del servidor.
     */
    public function test_admin_and_librarian_can_access_moderation_list(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.moderacion.index'));

        $response->assertStatus(200);
        $response->assertSee('Moderación de reportes');
        $response->assertSee('Lista de reportes pendientes de revisión');

        // Verificar acceso en el dashboard administrativo
        $dashboardResponse = $this->actingAs($admin)->get(route('admin.dashboard'));
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Moderación de reportes');
        $dashboardResponse->assertSee(route('admin.moderacion.index'));
    }

    /**
     * Prueba 2: Acceso denegado a Usuario General.
     * Requerimiento 2: Permitir el acceso únicamente al Administrador/Bibliotecario.
     */
    public function test_general_user_cannot_access_moderation_list(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $response = $this->actingAs($user)->get(route('admin.moderacion.index'));

        $response->assertStatus(403);
        $response->assertSee('No tienes permiso para realizar esta acción');
    }

    /**
     * Prueba 3: Acceso denegado a visitantes sin sesión.
     * Requerimiento 2: Redirigir a inicio de sesión a usuarios no autenticados.
     */
    public function test_unauthenticated_visitor_cannot_access_moderation_list(): void
    {
        $response = $this->get(route('admin.moderacion.index'));

        $response->assertRedirect(route('login'));
    }

    /**
     * Prueba 4: Visualización correcta de folio, objeto, autor y fechas diferenciadas.
     * Requerimiento 3, 4, 6: Mostrar folio, objeto, autor, fecha captura y fecha último envío.
     * No incluir reportes que no estén pendientes de revisión.
     */
    public function test_displays_folio_object_author_and_dates_correctly(): void
    {
        $admin = User::factory()->admin()->create();

        $autor = User::factory()->create([
            'name' => 'Mariana Gomez Perez',
            'email' => 'mariana.gomez@merida.tecnm.mx',
        ]);

        $fechaCaptura = Carbon::parse('2026-10-25 10:30:00');
        $fechaEnvio = Carbon::parse('2026-10-28 16:45:00');

        $reportePendiente = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261025-0001',
            'nombre' => 'Calculadora Científica Casio fx-991LA',
            'titulo' => 'Calculadora Científica Casio fx-991LA',
            'descripcion' => 'Calculadora gris con funda negra olvidada en Edificio F',
            'categoria' => 'Electrónicos',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'disponible' => false,
            'fecha_captura' => $fechaCaptura,
            'fecha_envio_revision' => $fechaEnvio,
        ]);

        // Reporte aprobado (NO debe aparecer en la lista de pendientes)
        $reporteAprobado = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261020-0999',
            'nombre' => 'Mochila Azul Aprobada',
            'titulo' => 'Mochila Azul Aprobada',
            'descripcion' => 'Objeto ya aprobado y publicado',
            'categoria' => 'Mochilas',
            'estado' => 'Aprobada',
            'disponible' => true,
            'fecha_captura' => Carbon::now(),
            'fecha_envio_revision' => Carbon::now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.moderacion.index'));

        $response->assertStatus(200);

        // Encabezados de columna exigidos (Requerimiento 4)
        $response->assertSee('Folio del reporte');
        $response->assertSee('Nombre del objeto');
        $response->assertSee('Autor');
        $response->assertSee('Fecha de captura');
        $response->assertSee('Fecha y hora del último envío a revisión');
        $response->assertSee('Revisar');

        // Datos del reporte pendiente
        $response->assertSee('OBJ-20261025-0001');
        $response->assertSee('Calculadora Científica Casio fx-991LA');
        $response->assertSee('Mariana Gomez Perez');
        $response->assertSee('25/10/2026 10:30');
        $response->assertSee('28/10/2026 16:45');

        // El reporte aprobado no debe figurar en la lista de pendientes
        $response->assertDontSee('OBJ-20261020-0999');
        $response->assertDontSee('Mochila Azul Aprobada');
    }

    /**
     * Prueba 5: Ordenamiento ascendente por último envío a revisión.
     * Requerimiento 5: Ordenar los pendientes desde el envío a revisión más antiguo. Criterio de desempate estable.
     */
    public function test_orders_pending_reports_ascending_by_last_review_submission(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        // Reporte Intermedio (enviado hace 2 días)
        $repMedio = Publicacion::create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261029-0002',
            'nombre' => 'Objeto Envío Medio',
            'descripcion' => 'Envío intermedio',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'fecha_captura' => Carbon::parse('2026-10-29 10:00:00'),
            'fecha_envio_revision' => Carbon::parse('2026-10-29 10:00:00'),
        ]);

        // Reporte Antiguo (enviado hace 4 días - DEBE APARECER PRIMERO)
        $repAntiguo = Publicacion::create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261027-0001',
            'nombre' => 'Objeto Envío Más Antiguo',
            'descripcion' => 'Envío prioritario por antigüedad',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'fecha_captura' => Carbon::parse('2026-10-27 08:00:00'),
            'fecha_envio_revision' => Carbon::parse('2026-10-27 08:00:00'),
        ]);

        // Reporte Reciente (enviado hoy - DEBE APARECER AL FINAL)
        $repReciente = Publicacion::create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261031-0003',
            'nombre' => 'Objeto Envío Reciente',
            'descripcion' => 'Envío reciente',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'fecha_captura' => Carbon::parse('2026-10-31 12:00:00'),
            'fecha_envio_revision' => Carbon::parse('2026-10-31 12:00:00'),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.moderacion.index'));

        $response->assertStatus(200);

        $content = $response->getContent();
        $posAntiguo = strpos($content, 'OBJ-20261027-0001');
        $posMedio = strpos($content, 'OBJ-20261029-0002');
        $posReciente = strpos($content, 'OBJ-20261031-0003');

        $this->assertNotFalse($posAntiguo);
        $this->assertNotFalse($posMedio);
        $this->assertNotFalse($posReciente);

        // El más antiguo debe aparecer antes del medio, y el medio antes del reciente
        $this->assertTrue($posAntiguo < $posMedio, 'El reporte con envío más antiguo debe anteceder al intermedio');
        $this->assertTrue($posMedio < $posReciente, 'El reporte intermedio debe anteceder al más reciente');
    }

    /**
     * Prueba 6: Comportamiento de reportes corregidos y reenviados.
     * Requerimiento 7: Si un reporte fue corregido y reenviado, utilizar la fecha del último reenvío para su posición.
     */
    public function test_resubmitted_and_corrected_report_uses_last_resubmission_date(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        // Reporte A: capturado hace 5 días, inicialmente enviado hace 5 días
        $reporteA = Publicacion::create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261026-0001',
            'nombre' => 'Reporte A Reenviado',
            'descripcion' => 'Reporte que fue corregido y reenviado',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'fecha_captura' => Carbon::parse('2026-10-26 09:00:00'),
            'fecha_envio_revision' => Carbon::parse('2026-10-26 09:00:00'),
        ]);

        // Reporte B: capturado hace 3 días
        $reporteB = Publicacion::create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261028-0002',
            'nombre' => 'Reporte B Constante',
            'descripcion' => 'Reporte sin modificaciones',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'fecha_captura' => Carbon::parse('2026-10-28 11:00:00'),
            'fecha_envio_revision' => Carbon::parse('2026-10-28 11:00:00'),
        ]);

        // El usuario corrige el Reporte A hoy y lo reenvía a revisión
        $fechaReenvio = Carbon::parse('2026-10-31 15:00:00');
        $reporteA->update([
            'fecha_envio_revision' => $fechaReenvio,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.moderacion.index'));

        $content = $response->getContent();
        $posA = strpos($content, 'OBJ-20261026-0001');
        $posB = strpos($content, 'OBJ-20261028-0002');

        // Reporte B (28 de oct) ahora debe aparecer ANTES de Reporte A (reenviado el 31 de oct)
        $this->assertTrue($posB < $posA, 'Al ser reenviado hoy, el reporte A debe ubicarse después del reporte B');

        // Comprobar que en pantalla se diferencian su fecha de captura original de la de reenvío
        $response->assertSee('26/10/2026 09:00');
        $response->assertSee('31/10/2026 15:00');
    }

    /**
     * Prueba 7: Mensaje exacto cuando no existen pendientes.
     * Requerimiento 8: Mostrar exactamente: «No hay reportes pendientes de revisión».
     */
    public function test_displays_exact_message_when_no_pending_reports_exist(): void
    {
        $admin = User::factory()->admin()->create();

        // Base de datos sin ningún reporte pendiente
        $this->assertEquals(0, Publicacion::pendientesRevision()->count());

        $response = $this->actingAs($admin)->get(route('admin.moderacion.index'));

        $response->assertStatus(200);

        // Texto exacto requerido (Requerimiento 8)
        $response->assertSee('No hay reportes pendientes de revisión');
    }

    /**
     * Prueba 8: Correcta identificación del reporte al pulsar «Revisar».
     * Requerimiento 9: Botón «Revisar» que dirigirá al detalle del reporte correspondiente.
     */
    public function test_review_action_button_correctly_identifies_and_links_to_report(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $reporte = Publicacion::create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261031-7777',
            'nombre' => 'Memoria USB Kingston 64GB',
            'titulo' => 'Memoria USB Kingston 64GB',
            'descripcion' => 'Memoria USB metálica encontrada cerca del laboratorio 3',
            'categoria' => 'Electrónicos',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'fecha_captura' => Carbon::now(),
            'fecha_envio_revision' => Carbon::now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.moderacion.index'));

        $response->assertStatus(200);

        // Verifica que el enlace tenga el texto exacto «Revisar» y apunte a la ruta de revisión
        $expectedUrl = route('admin.moderacion.show', $reporte->id);
        $response->assertSee($expectedUrl);
        $response->assertSee('Revisar');

        // Al acceder a la ruta individual de revisión, se identifica correctamente el reporte
        $showResponse = $this->actingAs($admin)->get($expectedUrl);
        $showResponse->assertStatus(200);
        $showResponse->assertSee('OBJ-20261031-7777');
        $showResponse->assertSee('Memoria USB Kingston 64GB');
        $showResponse->assertSee($user->name);
    }

    /**
     * Prueba 9: Acceso denegado a la ruta de revisión individual a usuario general y visitante.
     * Requerimiento 2: Proteger las rutas y acciones mediante permisos reales del servidor.
     */
    public function test_show_route_is_strictly_protected_against_unauthorized_users(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $reporte = Publicacion::create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261031-8888',
            'nombre' => 'Cuaderno Profesional',
            'descripcion' => 'Cuaderno verde',
            'estado' => Publicacion::ESTADO_PENDIENTE,
        ]);

        // Usuario general recibe 403
        $generalResponse = $this->actingAs($user)->get(route('admin.moderacion.show', $reporte->id));
        $generalResponse->assertStatus(403);

        // Visitante sin sesión es redirigido a login
        auth()->logout();
        $guestResponse = $this->get(route('admin.moderacion.show', $reporte->id));
        $guestResponse->assertRedirect(route('login'));
    }
}

