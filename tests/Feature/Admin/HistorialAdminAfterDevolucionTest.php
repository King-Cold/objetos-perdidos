<?php

namespace Tests\Feature\Admin;

use App\Models\DecisionModeracion;
use App\Models\Devolucion;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HistorialAdminAfterDevolucionTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscenarioConDevolucionPendiente(): array
    {
        Storage::fake('local');

        $autor = User::factory()->create([
            'name' => 'Elena Castillo Pech',
            'email' => 'elena.castillo@campus.tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $admin = User::factory()->create([
            'name' => 'Lic. Martín Soberanis',
            'email' => 'martin.soberanis@biblioteca.tecnm.mx',
            'role' => User::ROLE_ADMIN,
        ]);

        $otroUsuario = User::factory()->create([
            'name' => 'Pedro Yam',
            'email' => 'pedro.yam@campus.tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $receptorConCuenta = User::factory()->create([
            'name' => 'Diana Moo',
            'email' => 'diana.moo@campus.tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $ahora = Carbon::now(Publicacion::TIMEZONE);
        $fechaCreacion = $ahora->copy()->subDays(5)->setTime(9, 0, 0);
        $fechaAprobacion = $ahora->copy()->subDays(4)->setTime(11, 0, 0);
        $fechaEntrega = $ahora->copy()->subDays(2)->format('Y-m-d');
        $fechaCapturaDev = $ahora->copy()->subDays(1)->setTime(14, 0, 0);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261106-9001',
            'nombre' => 'Audífonos Sony WH-1000XM4',
            'descripcion' => 'Audífonos inalámbricos negros con estuche rígido.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'devolucion_en_proceso' => true,
            'estado_devolucion' => Devolucion::ESTADO_PENDIENTE,
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_captura' => $fechaCreacion,
            'created_at' => $fechaCreacion,
            'fecha_hallazgo' => $ahora->copy()->subDays(6)->format('Y-m-d'),
            'hora_hallazgo' => '08:30',
            'fecha_primera_aprobacion' => $fechaAprobacion,
            'fecha_aprobacion' => $fechaAprobacion,
            'fecha_vencimiento' => $fechaAprobacion->copy()->addDays(14),
        ]);

        DecisionModeracion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_APROBADA,
            'motivo' => 'Reporte aprobado para publicación en el muro.',
            'fecha_decision' => $fechaAprobacion,
            'created_at' => $fechaAprobacion,
        ]);

        // Usar UploadedFile::fake()->create() para no requerir extensión GD
        $archivoEvidencia = UploadedFile::fake()->create('evidencia_entrega.jpg', 120, 'image/jpeg');
        $rutaEvidencia = $archivoEvidencia->storeAs('devoluciones/' . $publicacion->id, 'evidencia_entrega.jpg', 'local');

        $devolucion = Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Diana Moo',
            'receptor_identificador' => '20080555',
            'fecha_entrega' => $fechaEntrega,
            'hora_entrega' => '13:30',
            'fecha_captura' => $fechaCapturaDev,
            'fecha_envio_revision' => $fechaCapturaDev,
            'explicacion_propiedad' => 'Mostró el número de serie en la caja original y emparejó su teléfono.',
            'fotografia_ruta' => $rutaEvidencia,
            'fotografia_nombre_original' => 'evidencia_entrega.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 122880,
            'estado' => Devolucion::ESTADO_PENDIENTE,
        ]);

        return [$autor, $admin, $otroUsuario, $receptorConCuenta, $publicacion, $devolucion];
    }

    /**
     * 1 y 7. Al aprobar una devolución, el objeto se retira del muro público pero su historial
     * y antecedentes permanecen intactos y accesibles para el administrador.
     */
    public function test_administrador_puede_consultar_historial_completo_despues_de_aprobar_devolucion(): void
    {
        [$autor, $admin, $otroUsuario, $receptor, $publicacion, $devolucion] = $this->crearEscenarioConDevolucionPendiente();

        // El administrador aprueba la devolución
        $resAprobar = $this->actingAs($admin)->post(route('admin.devoluciones.aprobar', $devolucion->id));
        $resAprobar->assertRedirect(route('admin.devoluciones.show', $devolucion->id));

        $publicacion->refresh();
        $devolucion->refresh();

        // Verificar que está marcado como Devuelto y fuera del muro público
        $this->assertEquals(Publicacion::ESTADO_DEVUELTO, $publicacion->estado);
        $this->assertFalse((bool) $publicacion->disponible);
        $this->assertFalse($publicacion->isDisponible());

        // Verificar que no aparece en el muro público
        $resMuro = $this->get(route('muro'));
        $resMuro->assertOk();
        $resMuro->assertDontSee($publicacion->folio);

        // El administrador consulta el historial por la ruta administrativa y por la general
        foreach ([route('admin.reportes.historial', $publicacion->id), route('reportes.historial', $publicacion->id)] as $urlHistorial) {
            $resHistorial = $this->actingAs($admin)->get($urlHistorial);
            $resHistorial->assertOk();
            $resHistorial->assertSee('Historial del Objeto');
            $resHistorial->assertSee($publicacion->folio);
            $resHistorial->assertSee('Audífonos Sony WH-1000XM4');
            $resHistorial->assertSee('Devuelto');
            $resHistorial->assertSee('Fuera del muro');

            // Verificar que los antecedentes históricos siguen presentes en orden cronológico
            $resHistorial->assertSeeInOrder([
                'Creación',
                'Aprobación',
                'Devolución',
            ]);
            $resHistorial->assertSee('Diana Moo');
            $resHistorial->assertSee('Devolución física aprobada tras verificación y validación de evidencia.');
        }
    }

    /**
     * 2 y 3. El administrador puede localizar las devoluciones aprobadas en el módulo de
     * Entregas y Devoluciones y abrir su historial desde ahí o desde el detalle.
     */
    public function test_administrador_puede_localizar_devoluciones_aprobadas_y_acceder_al_historial(): void
    {
        [$autor, $admin, $otroUsuario, $receptor, $publicacion, $devolucion] = $this->crearEscenarioConDevolucionPendiente();

        // Aprobar la devolución
        $this->actingAs($admin)->post(route('admin.devoluciones.aprobar', $devolucion->id));

        // En el filtro por defecto ('pendientes'), ya no aparece en la cola pendiente
        $resPendientes = $this->actingAs($admin)->get(route('admin.devoluciones.index'));
        $resPendientes->assertOk();
        $resPendientes->assertDontSee($publicacion->folio);

        // En el filtro de 'aprobadas', el administrador puede localizar la devolución aprobada y su enlace al historial
        $resAprobadas = $this->actingAs($admin)->get(route('admin.devoluciones.index', ['filtro' => 'aprobadas']));
        $resAprobadas->assertOk();
        $resAprobadas->assertSee('Devoluciones aprobadas');
        $resAprobadas->assertSee($publicacion->folio);
        $resAprobadas->assertSee('Audífonos Sony WH-1000XM4');
        $resAprobadas->assertSee(route('admin.reportes.historial', $publicacion->id));

        // En el filtro 'todas', también aparece con enlace a su historial
        $resTodas = $this->actingAs($admin)->get(route('admin.devoluciones.index', ['filtro' => 'todas']));
        $resTodas->assertOk();
        $resTodas->assertSee($publicacion->folio);
        $resTodas->assertSee(route('admin.reportes.historial', $publicacion->id));

        // En el detalle administrativo de la devolución aprobada también aparece el botón Ver historial
        $resDetalleDev = $this->actingAs($admin)->get(route('admin.devoluciones.show', $devolucion->id));
        $resDetalleDev->assertOk();
        $resDetalleDev->assertSee(route('admin.reportes.historial', $publicacion->id));
        $resDetalleDev->assertSee('Ver historial');
    }

    /**
     * 6. El autor original conserva el acceso al historial de su objeto desde «Mis reportes»
     * después de que la devolución fue aprobada.
     */
    public function test_autor_original_conserva_acceso_al_historial_desde_mis_reportes_tras_aprobar_devolucion(): void
    {
        [$autor, $admin, $otroUsuario, $receptor, $publicacion, $devolucion] = $this->crearEscenarioConDevolucionPendiente();

        $this->actingAs($admin)->post(route('admin.devoluciones.aprobar', $devolucion->id));

        // El autor ve su objeto en «Mis reportes» con el botón Consultar historial
        $resMisReportes = $this->actingAs($autor)->get(route('reportes.mis-reportes'));
        $resMisReportes->assertOk();
        $resMisReportes->assertSee($publicacion->folio);
        $resMisReportes->assertSee(route('reportes.historial', $publicacion->id));

        // El autor puede abrir el historial completo
        $resHistorial = $this->actingAs($autor)->get(route('reportes.historial', $publicacion->id));
        $resHistorial->assertOk();
        $resHistorial->assertSee($publicacion->folio);
        $resHistorial->assertSee('Devuelto');
        $resHistorial->assertSeeInOrder(['Creación', 'Aprobación', 'Devolución']);
    }

    /**
     * 4 y 8. Bloqueo de usuarios no autorizados (visitantes, otros autores y receptores)
     * tanto en `reportes.historial` como en `admin.reportes.historial` tras aprobar la devolución.
     */
    public function test_usuarios_no_autorizados_estan_bloqueados_del_historial_tras_aprobar_devolucion(): void
    {
        [$autor, $admin, $otroUsuario, $receptor, $publicacion, $devolucion] = $this->crearEscenarioConDevolucionPendiente();

        $this->actingAs($admin)->post(route('admin.devoluciones.aprobar', $devolucion->id));

        // 1. Visitante no autenticado es redirigido a login en ambas rutas
        auth()->logout();
        $this->get(route('reportes.historial', $publicacion->id))->assertRedirect(route('login'));
        $this->get(route('admin.reportes.historial', $publicacion->id))->assertRedirect(route('login'));

        // 2. Otro usuario general recibe 403 en ambas rutas
        $this->actingAs($otroUsuario)->get(route('reportes.historial', $publicacion->id))->assertForbidden();
        $this->actingAs($otroUsuario)->get(route('admin.reportes.historial', $publicacion->id))->assertForbidden();

        // 3. El receptor del objeto tampoco adquiere permiso para consultar el historial ni la ruta admin
        $this->actingAs($receptor)->get(route('reportes.historial', $publicacion->id))->assertForbidden();
        $this->actingAs($receptor)->get(route('admin.reportes.historial', $publicacion->id))->assertForbidden();

        // 4. El autor original puede usar reportes.historial pero no la ruta exclusiva de rol admin
        $this->actingAs($autor)->get(route('admin.reportes.historial', $publicacion->id))->assertForbidden();
    }
}
