<?php

namespace Tests\Feature;

use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteConsultaTask061Test extends TestCase
{
    use RefreshDatabase;

    /**
     * CA-01: El usuario con sesión activa dispone de una opción visible «Mis reportes» en navegación.
     */
    public function test_authenticated_user_has_visible_mis_reportes_link_in_navigation(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $response = $this->actingAs($user)->get(route('muro'));

        $response->assertStatus(200);
        $response->assertSee(route('reportes.mis-reportes'));
        $response->assertSee('Mis Reportes');
    }

    /**
     * CA-02 & CT-02: El apartado muestra únicamente los reportes del usuario autenticado.
     */
    public function test_mis_reportes_shows_only_own_reports(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $miReporte = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Mi Mochila Negra Propia',
        ]);

        $reporteAjeno = Publicacion::factory()->create([
            'user_id' => $otherUser->id,
            'nombre' => 'Cartera Ajena No Visible',
        ]);

        $response = $this->actingAs($user)->get(route('reportes.mis-reportes'));

        $response->assertStatus(200);
        $response->assertSee('Mi Mochila Negra Propia');
        $response->assertDontSee('Cartera Ajena No Visible');
    }

    /**
     * CA-02: Muestra reportes propios incluso cuando ya no estén publicados en el muro.
     */
    public function test_mis_reportes_shows_own_reports_even_if_not_published_on_muro(): void
    {
        $user = User::factory()->create();

        $reportePendiente = Publicacion::factory()->pendiente()->create([
            'user_id' => $user->id,
            'nombre' => 'Calculadora Pendiente Fuera del Muro',
        ]);

        $reporteCancelado = Publicacion::factory()->cancelado()->create([
            'user_id' => $user->id,
            'nombre' => 'Audífonos Cancelados Fuera del Muro',
        ]);

        $response = $this->actingAs($user)->get(route('reportes.mis-reportes'));

        $response->assertStatus(200);
        $response->assertSee('Calculadora Pendiente Fuera del Muro');
        $response->assertSee('Audífonos Cancelados Fuera del Muro');
        $response->assertSee('Fuera del muro');
    }

    /**
     * CA-03: Cada reporte muestra folio, nombre del objeto, estado de revisión, disponibilidad, resguardo y vigencia.
     */
    public function test_each_report_card_shows_required_metadata_and_actions(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261031-7788',
            'nombre' => 'Reloj Casio Plateado',
            'estado' => 'Aprobada',
            'disponible' => true,
            'situacion_resguardo' => 'En posesión del autor',
            'fecha_primera_aprobacion' => Carbon::now(Publicacion::TIMEZONE),
            'fecha_vencimiento' => Carbon::now(Publicacion::TIMEZONE)->addDays(14),
        ]);

        $response = $this->actingAs($user)->get(route('reportes.mis-reportes'));

        $response->assertStatus(200);
        $response->assertSee('OBJ-20261031-7788');
        $response->assertSee('Reloj Casio Plateado');
        $response->assertSee('Aprobada');
        $response->assertSee('Disponible en el muro');
        $response->assertSee('En posesión del autor');
        $response->assertSee('Editar reporte');
    }

    /**
     * CA-04: Si nunca fue aprobado, mostrar exactamente «Aún no inicia su vigencia».
     */
    public function test_unapproved_report_shows_aun_no_inicia_su_vigencia(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->pendiente()->create([
            'user_id' => $user->id,
            'nombre' => 'Termo Metálico Azul',
            'fecha_primera_aprobacion' => null,
            'fecha_vencimiento' => null,
        ]);

        $response = $this->actingAs($user)->get(route('reportes.mis-reportes'));

        $response->assertStatus(200);
        $response->assertSee('Aún no inicia su vigencia');
    }

    /**
     * CA-05: Si no existen reportes propios, mostrar texto exacto y enlace a «Encontré un objeto».
     */
    public function test_empty_state_shows_exact_text_and_link_to_create(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('reportes.mis-reportes'));

        $response->assertStatus(200);
        $response->assertSee('Aún no has registrado objetos encontrados');
        $response->assertSee('Encontré un objeto');
        $response->assertSee(route('reportes.create'));
    }

    /**
     * CA-06: Cada registro permite abrir su detalle privado para consultar datos, fotografías y situación actual.
     */
    public function test_author_can_open_private_report_detail(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261031-9999',
            'nombre' => 'Laptop HP Gris',
            'descripcion' => 'Laptop encontrada en la sala de cómputo con calcomanías de Linux.',
            'area_zona' => 'Edificio C',
            'ubicacion_especifica' => 'Laboratorio 3, mesa del fondo',
            'situacion_resguardo' => 'Bajo resguardo confirmado de biblioteca',
        ]);

        Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/laptop1.jpg',
            'nombre_original' => 'laptop1.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
            'es_principal' => true,
        ]);

        $response = $this->actingAs($user)->get(route('reportes.show', $reporte->id));

        $response->assertStatus(200);
        $response->assertSee('OBJ-20261031-9999');
        $response->assertSee('Laptop HP Gris');
        $response->assertSee('Laboratorio 3, mesa del fondo');
        $response->assertSee('Bajo resguardo confirmado de biblioteca');
        $response->assertSee('laptop1.jpg');
    }

    /**
     * CA-06 & CT-01: Usuario ajeno no puede abrir el detalle privado de otro usuario (403 Forbidden).
     */
    public function test_general_user_cannot_view_another_users_report_detail(): void
    {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();

        $reporte = Publicacion::factory()->create([
            'user_id' => $author->id,
            'nombre' => 'Reporte Confidencial del Autor',
        ]);

        $response = $this->actingAs($otherUser)->get(route('reportes.show', $reporte->id));

        $response->assertStatus(403);
    }

    /**
     * CA-07 & CA-08: Autor puede consultar motivo vigente y observaciones administrativas cuando requiere corrección.
     */
    public function test_author_can_view_administrative_observations_when_correction_requested(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->requiereCorreccion('Favor de subir una fotografía más nítida del número de serie')->create([
            'user_id' => $user->id,
            'nombre' => 'Cámara Canon EOS',
        ]);

        // Verificación en listado de Mis Reportes (CA-07)
        $resList = $this->actingAs($user)->get(route('reportes.mis-reportes'));
        $resList->assertStatus(200);
        $resList->assertSee('Requiere corrección');
        $resList->assertSee('Favor de subir una fotografía más nítida del número de serie');

        // Verificación en detalle privado del reporte (CA-07)
        $resDetail = $this->actingAs($user)->get(route('reportes.show', $reporte->id));
        $resDetail->assertStatus(200);
        $resDetail->assertSee('Solicitud de Corrección por Biblioteca');
        $resDetail->assertSee('Favor de subir una fotografía más nítida del número de serie');
    }

    /**
     * CA-08: Administrador puede consultar el detalle del reporte con sus observaciones.
     */
    public function test_administrator_can_view_report_detail_and_observations(): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->create();

        $reporte = Publicacion::factory()->requiereCorreccion('Observación técnica de biblioteca para el autor')->create([
            'user_id' => $author->id,
            'nombre' => 'Calculadora Texas Instruments',
        ]);

        $response = $this->actingAs($admin)->get(route('reportes.show', $reporte->id));

        $response->assertStatus(200);
        $response->assertSee('Observación técnica de biblioteca para el autor');
    }

    /**
     * CA-08: Las observaciones administrativas NUNCA aparecen en vistas públicas del muro ni en publicaciones.show.
     */
    public function test_administrative_observations_are_never_leaked_to_public_views(): void
    {
        $author = User::factory()->create();

        // Creamos una publicación con observaciones internas registradas
        $reporte = Publicacion::factory()->create([
            'user_id' => $author->id,
            'nombre' => 'Bicicleta de Montaña Roja',
            'estado' => 'Aprobada',
            'disponible' => true,
            'observaciones_admin' => 'SECRETO_INTERNO_BIBLIOTECA_OBSERVACION',
            'motivo_correccion' => 'SECRETO_INTERNO_BIBLIOTECA_MOTIVO',
        ]);

        // 1. Consulta pública en el Muro (/muro)
        $resMuro = $this->get(route('muro'));
        $resMuro->assertStatus(200);
        $resMuro->assertSee('Bicicleta de Montaña Roja');
        $resMuro->assertDontSee('SECRETO_INTERNO_BIBLIOTECA_OBSERVACION');
        $resMuro->assertDontSee('SECRETO_INTERNO_BIBLIOTECA_MOTIVO');

        // 2. Consulta pública en el Detalle General de publicación (/publicaciones/{id})
        $resPub = $this->get(route('publicaciones.show', $reporte->id));
        $resPub->assertStatus(200);
        $resPub->assertSee('Bicicleta de Montaña Roja');
        $resPub->assertDontSee('SECRETO_INTERNO_BIBLIOTECA_OBSERVACION');
        $resPub->assertDontSee('SECRETO_INTERNO_BIBLIOTECA_MOTIVO');
    }

    /**
     * CT-01: Visitante sin sesión es redirigido a login al intentar acceder a mis-reportes o detalle.
     */
    public function test_unauthenticated_visitor_is_redirected_to_login(): void
    {
        $reporte = Publicacion::factory()->create();

        $resList = $this->get(route('reportes.mis-reportes'));
        $resList->assertRedirect(route('login'));

        $resShow = $this->get(route('reportes.show', $reporte->id));
        $resShow->assertRedirect(route('login'));
    }
}

