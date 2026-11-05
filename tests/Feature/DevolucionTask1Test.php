<?php

namespace Tests\Feature;

use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevolucionTask1Test extends TestCase
{
    use RefreshDatabase;

    /**
     * CA-01: La opción «Registrar devolución» está disponible para el autor con sesión activa
     * cuando la publicación está aprobada, disponible, vigente, bajo su resguardo, sin devoluciones activas ni incidencias.
     */
    public function test_autor_puede_ver_boton_registrar_devolucion_en_mis_reportes_y_detalle(): void
    {
        $autor = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => Carbon::now(Publicacion::TIMEZONE)->subDays(2),
            'fecha_vencimiento' => Carbon::now(Publicacion::TIMEZONE)->addDays(12),
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'devolucion_en_proceso' => false,
            'incidencia_abierta' => false,
            'tiene_incidencia' => false,
            'estado_devolucion' => null,
        ]);

        // Ver en Mis reportes
        $responseMisReportes = $this->actingAs($autor)->get(route('reportes.mis-reportes'));
        $responseMisReportes->assertOk();
        $responseMisReportes->assertSee(route('devoluciones.create', $publicacion->id));
        $responseMisReportes->assertSee('Registrar devolución');

        // Ver en Detalle privado
        $responseDetalle = $this->actingAs($autor)->get(route('reportes.show', $publicacion->id));
        $responseDetalle->assertOk();
        $responseDetalle->assertSee(route('devoluciones.create', $publicacion->id));
        $responseDetalle->assertSee('Registrar devolución');
    }

    /**
     * CA-01, CA-02: El autor autorizado puede acceder por URL al formulario y ve los datos y advertencias correspondientes.
     */
    public function test_autor_puede_acceder_al_formulario_y_ve_campos_e_identificacion_inmutable(): void
    {
        $autor = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Calculadora Casio fx-991',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => Carbon::now(Publicacion::TIMEZONE)->subDays(1),
            'fecha_vencimiento' => Carbon::now(Publicacion::TIMEZONE)->addDays(13),
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'devolucion_en_proceso' => false,
            'incidencia_abierta' => false,
            'tiene_incidencia' => false,
            'estado_devolucion' => null,
        ]);

        $response = $this->actingAs($autor)->get(route('devoluciones.create', $publicacion->id));

        $response->assertOk();
        // CA-02: Folio y nombre del objeto
        $response->assertSee($publicacion->folio);
        $response->assertSee('Calculadora Casio fx-991');
        // CA-02: Advertencia sobre entrega física realizada
        $response->assertSee('Registra esta devolución únicamente si ya entregaste físicamente el objeto');
        // CA-03: Campos requeridos con asterisco
        $response->assertSee('Nombre completo del receptor');
        $response->assertSee('Identificador institucional del receptor');
        $response->assertSee('Fecha real de entrega');
        $response->assertSee('Hora real de entrega');
        $response->assertSee('Explicación de cómo se comprobó la propiedad');
        $response->assertSee('Fotografía de evidencia de la entrega');
        // CA-04: Admite matrícula o docente
        $response->assertSee('Admite matrícula de estudiante o identificador institucional de docente/empleado');
        // CA-06: Indicaciones de fotografía
        $response->assertSee('Formatos JPG o PNG de hasta 2 MB');
        $response->assertSee('Sustituir fotografía');
    }

    /**
     * CA-01: Visitante no autenticado es redirigido al inicio de sesión al intentar acceder al formulario.
     */
    public function test_visitante_no_autenticado_es_redirigido_al_login(): void
    {
        $publicacion = Publicacion::factory()->create([
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
        ]);

        $response = $this->get(route('devoluciones.create', $publicacion->id));
        $response->assertRedirect(route('login'));
    }

    /**
     * CA-01, CA-09: Un usuario autenticado que no es el autor original recibe 403 Forbidden.
     */
    public function test_usuario_que_no_es_el_autor_recibe_403_al_intentar_acceder(): void
    {
        $autor = User::factory()->create();
        $otroUsuario = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_vencimiento' => Carbon::now(Publicacion::TIMEZONE)->addDays(5),
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'devolucion_en_proceso' => false,
        ]);

        $response = $this->actingAs($otroUsuario)->get(route('devoluciones.create', $publicacion->id));
        $response->assertStatus(403);
    }

    /**
     * CA-01: Si la publicación no está aprobada (ej. pendiente de aprobación), el autor no puede registrar devolución.
     */
    public function test_bloquea_acceso_si_publicacion_no_esta_aprobada(): void
    {
        $autor = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'disponible' => false,
        ]);

        $response = $this->actingAs($autor)->get(route('devoluciones.create', $publicacion->id));
        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('warning');
    }

    /**
     * CA-01, CA-12: Si la publicación ha vencido, muestra el mensaje específico y bloquea acceso.
     */
    public function test_bloquea_acceso_y_muestra_mensaje_especifico_si_publicacion_esta_vencida(): void
    {
        $autor = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => Carbon::now(Publicacion::TIMEZONE)->subDays(15),
            'fecha_vencimiento' => Carbon::now(Publicacion::TIMEZONE)->subMinutes(5),
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
        ]);

        $response = $this->actingAs($autor)->get(route('devoluciones.create', $publicacion->id));
        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('warning', 'La publicación ha vencido. Si entregaste el objeto antes del vencimiento y no registraste la devolución, contacta a biblioteca e indica el folio del reporte.');
    }

    /**
     * CA-01: Si el objeto ya está bajo resguardo confirmado de biblioteca, no puede devolverse por el autor.
     */
    public function test_bloquea_acceso_si_objeto_esta_bajo_resguardo_confirmado_de_biblioteca(): void
    {
        $autor = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_vencimiento' => Carbon::now(Publicacion::TIMEZONE)->addDays(5),
            'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO,
        ]);

        $response = $this->actingAs($autor)->get(route('devoluciones.create', $publicacion->id));
        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('warning');
    }

    /**
     * CA-01, CA-11: Si ya existe una devolución activa o en proceso, no se permite abrir el registro de otra.
     */
    public function test_bloquea_acceso_si_existe_devolucion_activa(): void
    {
        $autor = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_vencimiento' => Carbon::now(Publicacion::TIMEZONE)->addDays(5),
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'devolucion_en_proceso' => true,
        ]);

        $response = $this->actingAs($autor)->get(route('devoluciones.create', $publicacion->id));
        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('warning');
    }

    /**
     * CA-01: Si existe una incidencia abierta, se bloquea el acceso.
     */
    public function test_bloquea_acceso_si_existe_incidencia_abierta(): void
    {
        $autor = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_vencimiento' => Carbon::now(Publicacion::TIMEZONE)->addDays(5),
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'incidencia_abierta' => true,
        ]);

        $response = $this->actingAs($autor)->get(route('devoluciones.create', $publicacion->id));
        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('warning');
    }
}

