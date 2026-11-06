<?php

namespace Tests\Feature;

use App\Models\Devolucion;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HistorialAccesoEncabezadoTask1Test extends TestCase
{
    use RefreshDatabase;

    private function crearEscenarioBase(array $pubAttrs = []): array
    {
        $autor = User::factory()->create([
            'name' => 'María Pérez López',
            'email' => 'maria.perez@campus.tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $admin = User::factory()->create([
            'name' => 'Lic. Roberto Gómez',
            'email' => 'roberto.gomez@biblioteca.tecnm.mx',
            'role' => User::ROLE_ADMIN,
        ]);

        $otroUsuario = User::factory()->create([
            'name' => 'Juan Alcocer',
            'email' => 'juan.alcocer@campus.tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $ahora = Carbon::now(Publicacion::TIMEZONE);

        $publicacion = Publicacion::factory()->create(array_merge([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-7777',
            'nombre' => 'Calculadora Científica Casio fx-991',
            'descripcion' => 'Calculadora de color negro encontrada en el aula E1.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_captura' => $ahora->copy()->subDays(2),
            'fecha_hallazgo' => $ahora->copy()->subDays(3)->format('Y-m-d'),
            'hora_hallazgo' => '10:30',
            'fecha_primera_aprobacion' => $ahora->copy()->subDays(1),
            'fecha_aprobacion' => $ahora->copy()->subDays(1),
            'fecha_vencimiento' => $ahora->copy()->addDays(13),
            'area_zona' => 'Edificio E',
            'ubicacion_especifica' => 'Aula E1',
        ], $pubAttrs));

        return [$autor, $admin, $otroUsuario, $publicacion];
    }

    /**
     * Criterio: Acceso al historial.
     * El autor deberá poder abrir el historial de sus objetos desde «Mis reportes».
     */
    public function test_autor_original_puede_ver_enlace_en_mis_reportes_y_abrir_historial(): void
    {
        [$autor, $admin, $otroUsuario, $publicacion] = $this->crearEscenarioBase();

        // 1. En «Mis reportes» aparece el enlace al historial
        $resMisReportes = $this->actingAs($autor)->get(route('reportes.mis-reportes'));
        $resMisReportes->assertOk();
        $resMisReportes->assertSee(route('reportes.historial', $publicacion->id));
        $resMisReportes->assertSee('Consultar historial');

        // 2. Puede abrir exitosamente la pantalla del historial privado
        $response = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $response->assertOk();
        $response->assertSee('Historial del Objeto');
        $response->assertSee($publicacion->folio);
    }

    /**
     * Criterio: Acceso al historial.
     * El administrador/bibliotecario deberá poder acceder desde sus apartados de gestión.
     */
    public function test_administrador_o_bibliotecario_puede_acceder_desde_gestion(): void
    {
        [$autor, $admin, $otroUsuario, $publicacion] = $this->crearEscenarioBase([
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'disponible' => false,
            'fecha_envio_revision' => Carbon::now(Publicacion::TIMEZONE),
        ]);

        // 1. En la lista de moderación de gestión administrativa aparece el enlace al historial
        $resModeracionIndex = $this->actingAs($admin)->get(route('admin.moderacion.index'));
        $resModeracionIndex->assertOk();
        $resModeracionIndex->assertSee(route('reportes.historial', $publicacion->id));

        // 2. En el detalle de moderación de gestión administrativa aparece el enlace al historial
        $resModeracionShow = $this->actingAs($admin)->get(route('admin.moderacion.show', $publicacion->id));
        $resModeracionShow->assertOk();
        $resModeracionShow->assertSee(route('reportes.historial', $publicacion->id));

        // 3. El administrador puede abrir exitosamente el historial privado
        $response = $this->actingAs($admin)->get(route('reportes.historial', $publicacion->id));
        $response->assertOk();
        $response->assertSee('Historial del Objeto');
        $response->assertSee($publicacion->folio);
    }

    /**
     * Criterio: Protección del historial.
     * Los visitantes no autenticados no deberán acceder al historial y son redirigidos al login.
     */
    public function test_visitante_no_autenticado_es_redirigido_al_login(): void
    {
        [$autor, $admin, $otroUsuario, $publicacion] = $this->crearEscenarioBase();

        $response = $this->get(route('reportes.historial', $publicacion->id));
        $response->assertRedirect(route('login'));
    }

    /**
     * Criterio: Protección del historial.
     * Otros autores y usuarios no autorizados reciben 403 Forbidden.
     */
    public function test_otro_usuario_autor_no_puede_acceder_al_historial_ajeno(): void
    {
        [$autor, $admin, $otroUsuario, $publicacion] = $this->crearEscenarioBase();

        $response = $this->actingAs($otroUsuario)->get(route('reportes.historial', $publicacion->id));
        $response->assertStatus(403);
    }

    /**
     * Criterio: Entregas realizadas por biblioteca / Protección a receptores.
     * La persona receptora no obtendrá acceso al historial por el hecho de haber recibido el objeto.
     */
    public function test_receptor_con_cuenta_no_obtiene_acceso_al_historial(): void
    {
        Storage::fake('local');
        [$autor, $admin, $otroUsuario, $publicacion] = $this->crearEscenarioBase([
            'estado' => Publicacion::ESTADO_DEVUELTO,
            'disponible' => false,
            'devolucion_en_proceso' => false,
        ]);

        $usuarioReceptor = User::factory()->create([
            'name' => 'Fernando Cocom Cauich',
            'email' => 'fernando.cocom@campus.tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $fotoEvidencia = UploadedFile::fake()->image('evidencia.jpg', 400, 300);
        $ruta = $fotoEvidencia->storeAs('devoluciones/' . $publicacion->id, 'evidencia.jpg', 'local');

        Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Fernando Cocom Cauich',
            'receptor_identificador' => '18080123',
            'fecha_entrega' => Carbon::now(Publicacion::TIMEZONE)->format('Y-m-d'),
            'hora_entrega' => '11:00',
            'fecha_captura' => Carbon::now(Publicacion::TIMEZONE),
            'explicacion_propiedad' => 'Presentó factura electrónica y credencial institucional.',
            'fotografia_ruta' => $ruta,
            'estado' => Devolucion::ESTADO_VERIFICADA,
        ]);

        // El usuario receptor autenticado intenta acceder al historial del objeto devuelto
        $response = $this->actingAs($usuarioReceptor)->get(route('reportes.historial', $publicacion->id));
        $response->assertStatus(403);
    }

    /**
     * Criterio: Identificación y situación actual.
     * El historial deberá mostrar el folio, nombre del objeto, estado de revisión,
     * disponibilidad y situación de resguardo.
     */
    public function test_encabezado_muestra_folio_objeto_estado_disponibilidad_y_resguardo(): void
    {
        [$autor, $admin, $otroUsuario, $publicacion] = $this->crearEscenarioBase([
            'folio' => 'OBJ-20261106-1234',
            'nombre' => 'Mochila Victorinox Negra',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
        ]);

        $response = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $response->assertOk();

        // 1. Folio único
        $response->assertSee('OBJ-20261106-1234');
        $response->assertSee('Folio del reporte');

        // 2. Nombre del objeto
        $response->assertSee('Mochila Victorinox Negra');
        $response->assertSee('Nombre del objeto');

        // 3. Estado de revisión
        $response->assertSee('Estado de revisión');
        $response->assertSee('Aprobada');

        // 4. Disponibilidad
        $response->assertSee('Disponibilidad');
        $response->assertSee('Disponible en el muro');

        // 5. Situación de resguardo
        $response->assertSee('Situación de resguardo');
        $response->assertSee('En posesión del autor');
    }

    /**
     * Criterio: Identificación y situación actual.
     * Los datos actuales deben estar claramente diferenciados de los eventos históricos.
     */
    public function test_datos_actuales_estan_claramente_diferenciados_de_eventos_historicos(): void
    {
        [$autor, $admin, $otroUsuario, $publicacion] = $this->crearEscenarioBase();

        $response = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $response->assertOk();

        // Sección destacada de datos actuales
        $response->assertSee('Situación Actual del Objeto');
        $response->assertSee('Condición y datos vigentes en el sistema, diferenciados de los eventos históricos');

        // Sección separada de eventos históricos
        $response->assertSee('Historial de Eventos Registrados');
        $response->assertSee('Sucesos cronológicos registrados en la vida del objeto');
    }

    /**
     * Criterio: Independencia del muro / Conservación.
     * El historial continúa disponible aunque la publicación esté expirada/vencida.
     */
    public function test_historial_continua_disponible_si_publicacion_esta_vencida(): void
    {
        $ahora = Carbon::now(Publicacion::TIMEZONE);
        [$autor, $admin, $otroUsuario, $publicacion] = $this->crearEscenarioBase([
            'fecha_vencimiento' => $ahora->copy()->subDays(5),
            'disponible' => false,
        ]);

        $this->assertTrue($publicacion->isVencida());
        $this->assertFalse($publicacion->isDisponible());

        // El autor puede seguir consultando el historial
        $resAutor = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $resAutor->assertOk();
        $resAutor->assertSee('Fuera del muro');

        // El administrador también puede seguir consultando el historial
        $resAdmin = $this->actingAs($admin)->get(route('reportes.historial', $publicacion->id));
        $resAdmin->assertOk();
        $resAdmin->assertSee('Fuera del muro');
    }

    /**
     * Criterio: Independencia del muro / Conservación.
     * El historial continúa disponible aunque la publicación esté cancelada.
     */
    public function test_historial_continua_disponible_si_publicacion_esta_cancelada(): void
    {
        [$autor, $admin, $otroUsuario, $publicacion] = $this->crearEscenarioBase([
            'estado' => Publicacion::ESTADO_CANCELADO,
            'disponible' => false,
            'motivo_cancelacion' => 'El autor canceló el reporte porque fue entregado directamente.',
            'fecha_cancelacion' => Carbon::now(Publicacion::TIMEZONE),
        ]);

        $this->assertTrue($publicacion->isCancelado());

        // El autor puede seguir consultando el historial
        $resAutor = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $resAutor->assertOk();
        $resAutor->assertSee('Cancelado');
        $resAutor->assertSee('Fuera del muro');

        // El administrador puede consultar el historial
        $resAdmin = $this->actingAs($admin)->get(route('reportes.historial', $publicacion->id));
        $resAdmin->assertOk();
        $resAdmin->assertSee('Cancelado');
    }

    /**
     * Criterio: Independencia del muro / Conservación.
     * El historial continúa disponible aunque la publicación esté devuelta.
     */
    public function test_historial_continua_disponible_si_publicacion_esta_devuelta(): void
    {
        [$autor, $admin, $otroUsuario, $publicacion] = $this->crearEscenarioBase([
            'estado' => Publicacion::ESTADO_DEVUELTO,
            'disponible' => false,
        ]);

        $response = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $response->assertOk();
        $response->assertSee('Devuelto');
        $response->assertSee('Fuera del muro');
    }

    /**
     * Criterio: Consulta sin cambios.
     * Consultar el historial no deberá cambiar estados, fechas, responsables ni periodos de vigencia.
     */
    public function test_consultar_el_historial_no_modifica_datos_fechas_ni_vigencia(): void
    {
        [$autor, $admin, $otroUsuario, $publicacion] = $this->crearEscenarioBase();

        // Capturamos el estado original antes de la consulta
        $estadoOriginal = $publicacion->estado;
        $disponibleOriginal = $publicacion->disponible;
        $resguardoOriginal = $publicacion->situacion_resguardo;
        $capturaOriginal = $publicacion->fecha_captura;
        $vencimientoOriginal = $publicacion->fecha_vencimiento;
        $updatedAtOriginal = $publicacion->updated_at;

        // Autor consulta el historial
        $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id))->assertOk();

        // Admin consulta el historial
        $this->actingAs($admin)->get(route('reportes.historial', $publicacion->id))->assertOk();

        // Refrescamos modelo desde la base de datos
        $publicacion->refresh();

        $this->assertSame($estadoOriginal, $publicacion->estado);
        $this->assertSame($disponibleOriginal, $publicacion->disponible);
        $this->assertSame($resguardoOriginal, $publicacion->situacion_resguardo);
        $this->assertEquals($capturaOriginal, $publicacion->fecha_captura);
        $this->assertEquals($vencimientoOriginal, $publicacion->fecha_vencimiento);
        $this->assertEquals($updatedAtOriginal, $publicacion->updated_at);
    }
}

