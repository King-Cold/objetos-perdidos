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

class DevolucionTask4Test extends TestCase
{
    use RefreshDatabase;

    private function crearDevolucionConEvidencia(array $attributes = []): array
    {
        Storage::fake('local');
        $autor = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $tercero = User::factory()->create();

        $ahora = Carbon::now(Publicacion::TIMEZONE);
        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Mochila Nike Azul',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'devolucion_en_proceso' => true,
            'estado_devolucion' => Devolucion::ESTADO_PENDIENTE,
            'fecha_vencimiento' => $ahora->copy()->addDays(5),
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
        ]);

        $foto = UploadedFile::fake()->image('foto_evidencia.jpg', 600, 400);
        $ruta = $foto->storeAs('devoluciones/' . $publicacion->id, 'evidencia.jpg', 'local');

        $devolucion = Devolucion::create(array_merge([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Alejandro Morales Dzul',
            'receptor_identificador' => '19070555',
            'fecha_entrega' => $ahora->copy()->subDays(1)->format('Y-m-d'),
            'hora_entrega' => '12:00',
            'fecha_captura' => $ahora,
            'explicacion_propiedad' => 'El receptor presentó comprobante y credencial institucional vigente.',
            'fotografia_ruta' => $ruta,
            'fotografia_nombre_original' => 'foto_evidencia.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 10240,
            'estado' => Devolucion::ESTADO_PENDIENTE,
        ], $attributes));

        return [$autor, $admin, $tercero, $publicacion, $devolucion];
    }

    /**
     * CA-09, CA-13: El autor original puede consultar el seguimiento privado y la evidencia.
     */
    public function test_autor_original_puede_consultar_seguimiento_y_fotografia(): void
    {
        [$autor, $admin, $tercero, $publicacion, $devolucion] = $this->crearDevolucionConEvidencia();

        // 1. Consulta del seguimiento
        $response = $this->actingAs($autor)->get(route('devoluciones.show', $devolucion->id));
        $response->assertOk();
        $response->assertSee('Seguimiento de Devolución');
        $response->assertSee('Alejandro Morales Dzul');
        $response->assertSee('19070555');
        $response->assertSee($publicacion->folio);
        $response->assertSee('Pendiente de verificación');
        $response->assertSee(route('devoluciones.evidencia', $devolucion->id));

        // 2. Consulta de la fotografía de evidencia
        $responseFoto = $this->actingAs($autor)->get(route('devoluciones.evidencia', $devolucion->id));
        $responseFoto->assertOk();
        $responseFoto->assertHeader('Content-Type', 'image/jpeg');
    }

    /**
     * CA-09, CA-13: El administrador/bibliotecario puede consultar el seguimiento privado y la evidencia.
     */
    public function test_administrador_bibliotecario_puede_consultar_seguimiento_y_fotografia(): void
    {
        [$autor, $admin, $tercero, $publicacion, $devolucion] = $this->crearDevolucionConEvidencia();

        // Consulta del seguimiento
        $response = $this->actingAs($admin)->get(route('devoluciones.show', $devolucion->id));
        $response->assertOk();
        $response->assertSee('Alejandro Morales Dzul');

        // Consulta de la evidencia
        $responseFoto = $this->actingAs($admin)->get(route('devoluciones.evidencia', $devolucion->id));
        $responseFoto->assertOk();
    }

    /**
     * CA-09: Un usuario tercero autenticado recibe 403 Forbidden.
     */
    public function test_usuario_tercero_recibe_403_al_consultar_seguimiento_y_evidencia(): void
    {
        [$autor, $admin, $tercero, $publicacion, $devolucion] = $this->crearDevolucionConEvidencia();

        $response = $this->actingAs($tercero)->get(route('devoluciones.show', $devolucion->id));
        $response->assertStatus(403);

        $responseFoto = $this->actingAs($tercero)->get(route('devoluciones.evidencia', $devolucion->id));
        $responseFoto->assertStatus(403);
    }

    /**
     * CA-09: Visitante no autenticado no puede acceder.
     */
    public function test_visitante_no_autenticado_es_redirigido_al_login(): void
    {
        [$autor, $admin, $tercero, $publicacion, $devolucion] = $this->crearDevolucionConEvidencia();

        $response = $this->get(route('devoluciones.show', $devolucion->id));
        $response->assertRedirect(route('login'));

        $responseFoto = $this->get(route('devoluciones.evidencia', $devolucion->id));
        $responseFoto->assertRedirect(route('login'));
    }

    /**
     * CA-09: Ser receptor del objeto no otorga acceso aunque esa persona tenga cuenta de usuario.
     */
    public function test_receptor_con_cuenta_no_obtiene_acceso_al_seguimiento_ni_evidencia(): void
    {
        [$autor, $admin, $tercero, $publicacion, $devolucion] = $this->crearDevolucionConEvidencia();

        // Creamos una cuenta de usuario con el mismo nombre e identificador que el receptor registrado
        $usuarioReceptor = User::factory()->create([
            'name' => 'Alejandro Morales Dzul',
            'email' => 'alejandro.morales@campus.tecnm.mx',
        ]);

        $response = $this->actingAs($usuarioReceptor)->get(route('devoluciones.show', $devolucion->id));
        $response->assertStatus(403);

        $responseFoto = $this->actingAs($usuarioReceptor)->get(route('devoluciones.evidencia', $devolucion->id));
        $responseFoto->assertStatus(403);
    }

    /**
     * CA-13: Una devolución registrada oportunamente continúa consultable y en revisión
     * aunque termine la vigencia de la publicación, sin volver al muro.
     */
    public function test_devolucion_registrada_continua_en_revision_tras_vencimiento(): void
    {
        [$autor, $admin, $tercero, $publicacion, $devolucion] = $this->crearDevolucionConEvidencia();

        // Simulamos que la vigencia del reporte ya venció
        $publicacion->update([
            'fecha_vencimiento' => Carbon::now(Publicacion::TIMEZONE)->subDays(3),
        ]);

        $this->assertTrue($publicacion->isVencida());

        // El autor aún puede consultar el seguimiento privado
        $response = $this->actingAs($autor)->get(route('devoluciones.show', $devolucion->id));
        $response->assertOk();
        $response->assertSee('Pendiente de verificación');

        // La publicación sigue fuera del muro y del detalle general
        $this->assertFalse($publicacion->isDisponible());
        $responseMuro = $this->get(route('muro'));
        $responseMuro->assertDontSee('Mochila Nike Azul');

        $responseDetalleGeneral = $this->get(route('publicaciones.show', $publicacion->id));
        $responseDetalleGeneral->assertRedirect(route('muro'));
    }

    /**
     * CA-07, CA-13: El enlace al seguimiento de devolución aparece en «Mis Reportes» y en el detalle privado.
     */
    public function test_enlaces_al_seguimiento_aparecen_en_mis_reportes_y_detalle_privado(): void
    {
        [$autor, $admin, $tercero, $publicacion, $devolucion] = $this->crearDevolucionConEvidencia();

        // 1. En Mis Reportes
        $responseMisReportes = $this->actingAs($autor)->get(route('reportes.mis-reportes'));
        $responseMisReportes->assertOk();
        $responseMisReportes->assertSee(route('devoluciones.show', $devolucion->id));
        $responseMisReportes->assertSee('Ver seguimiento de devolución');

        // 2. En el detalle privado del reporte
        $responseDetalle = $this->actingAs($autor)->get(route('reportes.show', $publicacion->id));
        $responseDetalle->assertOk();
        $responseDetalle->assertSee(route('devoluciones.show', $devolucion->id));
        $responseDetalle->assertSee('Ver seguimiento de devolución');
    }
}

