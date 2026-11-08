<?php

namespace Tests\Feature\Admin;

use App\Models\Devolucion;
use App\Models\Publicacion;
use App\Models\Recepcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeracionFiltrosEstadosTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_tabs_de_estados_en_gestion_de_objetos(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        // 1. Reporte Pendiente
        $repPendiente = Publicacion::create([
            'user_id' => $user->id,
            'nombre' => 'Calculadora Casio Pendiente',
            'descripcion' => 'Esperando moderación',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'disponible' => false,
        ]);

        // 2. Reporte Activo en muro
        $repActivo = Publicacion::create([
            'user_id' => $user->id,
            'nombre' => 'Mochila Activa en Muro',
            'descripcion' => 'Publicada en muro',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_aprobacion' => Carbon::now(),
            'fecha_vencimiento' => Carbon::now()->addDays(10),
            'situacion_resguardo' => 'En posesión del autor',
            'devolucion_en_proceso' => false,
        ]);

        // 3. Reporte Vencido
        $repVencido = Publicacion::create([
            'user_id' => $user->id,
            'nombre' => 'Audífonos Vencidos',
            'descripcion' => 'Vencidos hace 3 días',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'fecha_aprobacion' => Carbon::now()->subDays(17),
            'fecha_vencimiento' => Carbon::now()->subDays(3),
            'situacion_resguardo' => 'En posesión del autor',
            'devolucion_en_proceso' => false,
        ]);

        // 4. Reporte en Resguardo biblioteca
        $repResguardo = Publicacion::create([
            'user_id' => $user->id,
            'nombre' => 'Tableta en Biblioteca',
            'descripcion' => 'En resguardo institucional',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'situacion_resguardo' => 'En biblioteca',
            'devolucion_en_proceso' => false,
        ]);

        Recepcion::create([
            'publicacion_id' => $repResguardo->id,
            'user_id' => $admin->id,
            'fecha_recepcion' => Carbon::now()->subDays(5)->toDateString(),
            'hora_recepcion' => '10:00',
            'fecha_captura' => Carbon::now()->subDays(5),
            'condicion_objeto' => 'Buen estado',
            'ubicacion_resguardo' => 'Estante A-1',
            'confirmacion_cotejo' => true,
            'fecha_limite_anual' => Carbon::now()->addYear()->toDateString(),
        ]);

        // 5. Reporte Cancelado
        $repCancelado = Publicacion::create([
            'user_id' => $user->id,
            'nombre' => 'Libro Cancelado Duplicado',
            'descripcion' => 'Cancelado por duplicado',
            'estado' => Publicacion::ESTADO_CANCELADO,
            'disponible' => false,
            'fecha_cancelacion' => Carbon::now(),
        ]);

        // 6. Reporte en Proceso de Entrega/Devolución (NO debe aparecer en estas pestañas)
        $repEnEntrega = Publicacion::create([
            'user_id' => $user->id,
            'nombre' => 'Reloj en Proceso de Entrega',
            'descripcion' => 'Con devolución activa',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'devolucion_en_proceso' => true,
            'estado_devolucion' => 'Pendiente de verificación',
        ]);

        // Probar pestaña PENDIENTES (por defecto)
        $respPendientes = $this->actingAs($admin)->get(route('admin.moderacion.index'));
        $respPendientes->assertStatus(200);
        $respPendientes->assertSee('Calculadora Casio Pendiente');
        $respPendientes->assertDontSee('Mochila Activa en Muro');
        $respPendientes->assertDontSee('Reloj en Proceso de Entrega');

        // Probar pestaña ACTIVAS
        $respActivas = $this->actingAs($admin)->get(route('admin.moderacion.index', ['filtro' => 'activas']));
        $respActivas->assertStatus(200);
        $respActivas->assertSee('Mochila Activa en Muro');
        $respActivas->assertDontSee('Calculadora Casio Pendiente');
        $respActivas->assertDontSee('Reloj en Proceso de Entrega');

        // Probar pestaña VENCIDAS (Muestra botón «Registrar recepción»)
        $respVencidas = $this->actingAs($admin)->get(route('admin.moderacion.index', ['filtro' => 'vencidas']));
        $respVencidas->assertStatus(200);
        $respVencidas->assertSee('Audífonos Vencidos');
        $respVencidas->assertSee('Registrar recepción');
        $respVencidas->assertDontSee('Reloj en Proceso de Entrega');

        // Probar pestaña RESGUARDO
        $respResguardo = $this->actingAs($admin)->get(route('admin.moderacion.index', ['filtro' => 'resguardo']));
        $respResguardo->assertStatus(200);
        $respResguardo->assertSee('Tableta en Biblioteca');
        $respResguardo->assertSee('Ver resguardo');
        $respResguardo->assertDontSee('Reloj en Proceso de Entrega');

        // Probar pestaña CANCELADAS
        $respCanceladas = $this->actingAs($admin)->get(route('admin.moderacion.index', ['filtro' => 'canceladas']));
        $respCanceladas->assertStatus(200);
        $respCanceladas->assertSee('Libro Cancelado Duplicado');
        $respCanceladas->assertDontSee('Reloj en Proceso de Entrega');

        // Probar pestaña TODAS
        $respTodas = $this->actingAs($admin)->get(route('admin.moderacion.index', ['filtro' => 'todas']));
        $respTodas->assertStatus(200);
        $respTodas->assertSee('Calculadora Casio Pendiente');
        $respTodas->assertSee('Mochila Activa en Muro');
        $respTodas->assertSee('Audífonos Vencidos');
        $respTodas->assertSee('Tableta en Biblioteca');
        $respTodas->assertSee('Libro Cancelado Duplicado');
        // Excluye la que está en entrega
        $respTodas->assertDontSee('Reloj en Proceso de Entrega');
    }
}

