<?php

namespace Tests\Feature;

use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteEliminacionPermanenteHU11Task1Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper para asociar una fotografía base a la publicación.
     */
    protected function attachPhoto(Publicacion $publicacion): Fotografia
    {
        return Fotografia::create([
            'publicacion_id' => $publicacion->id,
            'ruta' => 'hallazgos/test_' . uniqid() . '.jpg',
            'nombre_original' => 'foto.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
            'es_principal' => true,
        ]);
    }

    /**
     * 1. En «Mis reportes», mostrar la opción «Eliminar permanentemente» únicamente en publicaciones propias cuyo estado sea «Cancelado».
     * 3. Al seleccionar la opción, presentar una ventana de confirmación que incluya folio, nombre, advertencia clara y botones Volver y Eliminar permanentemente.
     */
    public function test_eliminar_permanentemente_option_is_shown_only_for_cancelled_reports_of_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        $reporteCancelado = Publicacion::factory()->cancelado()->create([
            'user_id' => $user->id,
            'nombre' => 'Mochila Nike Azul',
            'folio' => 'OBJ-20261104-0001',
            'motivo_cancelacion' => 'Objeto entregado por error',
        ]);
        $this->attachPhoto($reporteCancelado);

        $response = $this->actingAs($user)->get(route('reportes.mis-reportes'));

        $response->assertStatus(200);

        // Opción visible
        $response->assertSee('Eliminar permanentemente');

        // Datos en la ventana de confirmación
        $response->assertSee('OBJ-20261104-0001');
        $response->assertSee('Mochila Nike Azul');

        // Advertencia clara de que la eliminación es permanente e irreversible y no podrá recuperarse
        $response->assertSee('permanente e irreversible');
        $response->assertSee('no podrán recuperarse');

        // Botones requeridos
        $response->assertSee('Volver');
        $response->assertSee('¿Eliminar reporte permanentemente?');
    }

    /**
     * 2. No mostrar esta opción para publicaciones pendientes.
     */
    public function test_eliminar_permanentemente_option_is_not_shown_for_pending_reports(): void
    {
        $user = User::factory()->create();

        $reportePendiente = Publicacion::factory()->pendiente()->create([
            'user_id' => $user->id,
            'nombre' => 'Calculadora Científica Casio',
        ]);
        $this->attachPhoto($reportePendiente);

        $response = $this->actingAs($user)->get(route('reportes.mis-reportes'));

        $response->assertStatus(200);
        $response->assertSee('Calculadora Científica Casio');
        $response->assertDontSee('Eliminar permanentemente');
    }

    /**
     * 2. No mostrar esta opción para publicaciones aprobadas y vigentes.
     */
    public function test_eliminar_permanentemente_option_is_not_shown_for_approved_vigente_reports(): void
    {
        $user = User::factory()->create();

        $ahora = Carbon::now(Publicacion::TIMEZONE);
        $reporteVigente = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Reloj Inteligente Garmin',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => $ahora,
            'fecha_aprobacion' => $ahora,
            'fecha_vencimiento' => $ahora->copy()->addDays(14),
        ]);
        $this->attachPhoto($reporteVigente);

        $response = $this->actingAs($user)->get(route('reportes.mis-reportes'));

        $response->assertStatus(200);
        $response->assertSee('Reloj Inteligente Garmin');
        $response->assertDontSee('Eliminar permanentemente');
    }

    /**
     * 2. No mostrar esta opción para publicaciones vencidas.
     */
    public function test_eliminar_permanentemente_option_is_not_shown_for_vencida_reports(): void
    {
        $user = User::factory()->create();

        $reporteVencido = Publicacion::factory()->expirado()->create([
            'user_id' => $user->id,
            'nombre' => 'Termo Metálico Rojo',
        ]);
        $this->attachPhoto($reporteVencido);

        $response = $this->actingAs($user)->get(route('reportes.mis-reportes'));

        $response->assertStatus(200);
        $response->assertSee('Termo Metálico Rojo');
        $response->assertDontSee('Eliminar permanentemente');
    }

    /**
     * 2. No mostrar esta opción para publicaciones en corrección (Requiere corrección y Corrección solicitada).
     */
    public function test_eliminar_permanentemente_option_is_not_shown_for_reports_in_correction(): void
    {
        $user = User::factory()->create();

        $repCorreccion1 = Publicacion::factory()->requiereCorreccion()->create([
            'user_id' => $user->id,
            'nombre' => 'Audífonos Sony Negros',
        ]);
        $this->attachPhoto($repCorreccion1);

        $repCorreccion2 = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Gafas de Sol Ray-Ban',
            'estado' => Publicacion::ESTADO_CORRECCION_SOLICITADA,
        ]);
        $this->attachPhoto($repCorreccion2);

        $response = $this->actingAs($user)->get(route('reportes.mis-reportes'));

        $response->assertStatus(200);
        $response->assertSee('Audífonos Sony Negros');
        $response->assertSee('Gafas de Sol Ray-Ban');
        $response->assertDontSee('Eliminar permanentemente');
    }

    /**
     * 2. No mostrar esta opción para otros estados (e.g., Devuelto).
     */
    public function test_eliminar_permanentemente_option_is_not_shown_for_other_states_like_devuelto(): void
    {
        $user = User::factory()->create();

        $repDevuelto = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Credencial Universitaria',
            'estado' => Publicacion::ESTADO_DEVUELTO,
        ]);
        $this->attachPhoto($repDevuelto);

        $response = $this->actingAs($user)->get(route('reportes.mis-reportes'));

        $response->assertStatus(200);
        $response->assertSee('Credencial Universitaria');
        $response->assertDontSee('Eliminar permanentemente');
    }

    /**
     * La opción no se muestra para publicaciones canceladas de otros usuarios.
     */
    public function test_eliminar_permanentemente_is_never_shown_for_cancelled_reports_of_other_users(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $repAjeno = Publicacion::factory()->cancelado()->create([
            'user_id' => $otherUser->id,
            'nombre' => 'Tablet Samsung Ajena',
            'folio' => 'OBJ-20261104-9999',
        ]);
        $this->attachPhoto($repAjeno);

        $response = $this->actingAs($user)->get(route('reportes.mis-reportes'));

        $response->assertStatus(200);
        $response->assertDontSee('Tablet Samsung Ajena');
        $response->assertDontSee('OBJ-20261104-9999');
        $response->assertDontSee('Eliminar permanentemente');
    }

    /**
     * 4. Si el usuario selecciona «Volver», cierra la ventana o cancela la operación, el reporte permanece sin modificaciones.
     */
    public function test_cancelled_report_remains_completely_unmodified_in_database(): void
    {
        $user = User::factory()->create();

        $reporteCancelado = Publicacion::factory()->cancelado()->create([
            'user_id' => $user->id,
            'nombre' => 'Chamarra Universitaria Verde',
            'folio' => 'OBJ-20261104-0002',
            'motivo_cancelacion' => 'Motivo registrado previamente',
        ]);
        $this->attachPhoto($reporteCancelado);

        $updatedAtOriginal = $reporteCancelado->updated_at;

        // El usuario consulta mis-reportes y visualiza la interfaz
        $response = $this->actingAs($user)->get(route('reportes.mis-reportes'));
        $response->assertStatus(200);

        // El reporte en la base de datos se conserva idéntico
        $reporteFresco = $reporteCancelado->fresh();
        $this->assertNotNull($reporteFresco);
        $this->assertEquals('Cancelado', $reporteFresco->estado);
        $this->assertEquals('OBJ-20261104-0002', $reporteFresco->folio);
        $this->assertEquals('Chamarra Universitaria Verde', $reporteFresco->nombre);
        $this->assertEquals('Motivo registrado previamente', $reporteFresco->motivo_cancelacion);
        $this->assertEquals($updatedAtOriginal->toDateTimeString(), $reporteFresco->updated_at->toDateTimeString());
    }

    /**
     * 6. Foco accesible y atributos ARIA en la ventana modal de confirmación.
     */
    public function test_confirmation_modal_contains_accessible_aria_attributes_and_keyboard_navigation_support(): void
    {
        $user = User::factory()->create();

        $reporteCancelado = Publicacion::factory()->cancelado()->create([
            'user_id' => $user->id,
            'nombre' => 'Llaves con Llavero Metálico',
            'folio' => 'OBJ-20261104-0003',
        ]);
        $this->attachPhoto($reporteCancelado);

        $response = $this->actingAs($user)->get(route('reportes.mis-reportes'));

        $response->assertStatus(200);
        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-modal="true"', false);
        $response->assertSee('aria-labelledby="modal-eliminar-title-' . $reporteCancelado->id . '"', false);
        $response->assertSee('aria-describedby="modal-eliminar-desc-' . $reporteCancelado->id . '"', false);
        $response->assertSee('confirmar-eliminacion-' . $reporteCancelado->id);
    }
}
