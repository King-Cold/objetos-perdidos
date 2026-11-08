<?php

namespace Tests\Feature\Admin;

use App\Models\DecisionModeracion;
use App\Models\Devolucion;
use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\Recepcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DevolucionBibliotecaTask3Test extends TestCase
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

        Storage::fake('local');

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
            'nombre' => 'Mochila Nike Negra con Laptop',
            'descripcion' => 'Mochila deportiva con compartimento para laptop.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'area_zona' => 'Edificio D',
            'lugar_hallazgo' => 'Laboratorio D-4',
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
            'ruta' => 'publicaciones/foto_mochila.jpg',
            'nombre_original' => 'foto_mochila.jpg',
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
            'condicion_objeto' => 'En buen estado',
            'ubicacion_resguardo' => 'Estante Mochilas M-1',
            'observaciones' => 'Contiene libreta y cargador',
            'confirmacion_cotejo' => true,
            'fecha_limite_anual' => Carbon::create(2027, 11, 16, 9, 0, 0, Recepcion::TIMEZONE)->toDateString(),
        ], $overridesRecepcion);

        $recepcion = Recepcion::create($recepcionData);

        return [$admin, $autor, $reporte, $recepcion];
    }

    protected function payloadValido(): array
    {
        return [
            'receptor_nombre' => 'Alejandro Gómez Rosado',
            'receptor_identificador' => '19080999',
            'fecha_entrega' => '2026-11-20',
            'hora_entrega' => '10:30',
            'explicacion_propiedad' => 'El receptor presentó su credencial institucional, detalló los cuadernos del interior y desbloqueó la laptop.',
            'declaracion_sin_contradicciones' => '1',
            'fotografia' => UploadedFile::fake()->create('evidencia_entrega.jpg', 400, 'image/jpeg'),
        ];
    }

    /**
     * CA-06: Confirmación de la devolución e información de que saldrá del inventario activo.
     */
    public function test_01_vista_incluye_confirmacion_previa_e_informacion_de_salida(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        $response = $this->actingAs($admin)->get(route('admin.devoluciones-biblioteca.create', $reporte->id));
        $response->assertOk();

        // Advertencia modal en la vista
        $response->assertSee('¿Confirmar devolución desde biblioteca?');
        $response->assertSee('El objeto quedará registrado en estado institucional');
        $response->assertSee('Devuelto');
        $response->assertSee('Saldrá inmediatamente del inventario activo');
        $response->assertSee('No requerirá una segunda aprobación administrativa (cierre directo)');
    }

    /**
     * CA-07: Cierre directo - Registra devolución, salida física, fechas y responsable sin segunda aprobación.
     */
    public function test_02_cierre_directo_registra_devolucion_y_salida_correctamente(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        $payload = $this->payloadValido();

        $response = $this->actingAs($admin)->post(route('admin.devoluciones-biblioteca.store', $reporte->id), $payload);

        // Redirige al inventario con el mensaje exacto exigido por CA-07
        $response->assertRedirect(route('admin.inventario.index'));
        $response->assertSessionHas('status', 'La devolución desde biblioteca se registró correctamente.');

        // Verifica actualización en BD del objeto: estado 'Devuelto' y fuera de inventario/muro
        $reporteFresh = $reporte->fresh();
        $this->assertEquals(Publicacion::ESTADO_DEVUELTO, $reporteFresh->estado);
        $this->assertEquals('Devuelto', $reporteFresh->estado_devolucion);
        $this->assertFalse((bool) $reporteFresh->disponible);
        $this->assertFalse((bool) $reporteFresh->devolucion_en_proceso);

        // Verifica que la devolución quedó como 'Verificada' (cierre directo sin segunda aprobación)
        $this->assertDatabaseHas('devoluciones', [
            'publicacion_id' => $reporte->id,
            'user_id' => $admin->id,
            'admin_id' => $admin->id,
            'receptor_nombre' => 'Alejandro Gómez Rosado',
            'receptor_identificador' => '19080999',
            'fecha_entrega' => '2026-11-20',
            'estado' => Devolucion::ESTADO_VERIFICADA,
        ]);

        // Verifica decisión en moderación
        $this->assertDatabaseHas('decisiones_moderacion', [
            'publicacion_id' => $reporte->id,
            'user_id' => $admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_DEVOLUCION_BIBLIOTECA,
        ]);
    }

    /**
     * CA-07, CA-11: El objeto devuelto desaparece del inventario activo y del muro público.
     */
    public function test_03_objeto_devuelto_desaparece_de_inventario_activo_y_muro(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        $payload = $this->payloadValido();
        $this->actingAs($admin)->post(route('admin.devoluciones-biblioteca.store', $reporte->id), $payload);

        // 1. Inventario activo: no debe figurar en el inventario activo
        $resInventario = $this->actingAs($admin)->get(route('admin.inventario.index'));
        $resInventario->assertOk();
        $resInventario->assertDontSee($reporte->folio);

        // 2. Muro público: no debe aparecer en el muro
        $resMuro = $this->get(route('muro'));
        $resMuro->assertOk();
        $resMuro->assertDontSee($reporte->folio);

        // 3. No permite nuevas entregas sobre el mismo resguardo
        $this->assertFalse($reporte->fresh()->puedeRegistrarDevolucionDesdeBiblioteca());
    }

    /**
     * CA-10: Errores y prevención de duplicados o cambios incompletos.
     */
    public function test_04_bloquea_intento_repetido_o_duplicado(): void
    {
        [$admin, $autor, $reporte, $recepcion] = $this->crearEscenarioResguardo();

        $payload = $this->payloadValido();

        // Primer envío exitoso
        $res1 = $this->actingAs($admin)->post(route('admin.devoluciones-biblioteca.store', $reporte->id), $payload);
        $res1->assertRedirect(route('admin.inventario.index'));

        // Intento duplicado inmediato (segundo clic o intento repetido)
        $res2 = $this->actingAs($admin)->post(route('admin.devoluciones-biblioteca.store', $reporte->id), $payload);
        $res2->assertRedirect(route('admin.inventario.index'));
        $res2->assertSessionHas('error');

        // Solo debe existir 1 registro de devolución en la base de datos
        $this->assertEquals(1, Devolucion::where('publicacion_id', $reporte->id)->count());
    }
}

