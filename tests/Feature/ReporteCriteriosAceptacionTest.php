<?php

namespace Tests\Feature;

use App\Http\Requests\StoreHallazgoRequest;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReporteCriteriosAceptacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * Criterio 1: Advertencias visibles dentro del formulario debajo del área de captura
     * y antes del botón de envío.
     */
    public function test_avisos_de_publicacion_y_fotografias_se_muestran_visibles_en_el_formulario(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $response = $this->actingAs($user)->get(route('reportes.create'));

        $response->assertStatus(200);

        // Aviso sobre publicación
        $response->assertSee('Aviso sobre publicación:');
        $response->assertSee('Antes de enviar tu reporte, considera que el nombre del objeto, la fotografía principal y la descripción completa podrán ser visibles públicamente cuando la publicación sea aprobada.');

        // Aviso sobre fotografías
        $response->assertSee('Aviso sobre fotografías:');
        $response->assertSee('Evita subir fotografías que contengan datos personales legibles. Reserva algunas características del objeto que puedan servir para comprobar que te pertenece.');

        // Verificar la ubicación de los avisos
        $content = $response->getContent();
        $posAvisoPublicacion = strpos($content, 'Antes de enviar tu reporte, considera');
        $posAvisoFotos = strpos($content, 'Evita subir fotografías que contengan datos');
        $posBotonEnviar = strpos($content, 'id="btn-enviar-revision"');

        $this->assertNotFalse($posAvisoPublicacion);
        $this->assertNotFalse($posAvisoFotos);
        $this->assertNotFalse($posBotonEnviar);
        $this->assertTrue($posAvisoFotos < $posBotonEnviar, 'El aviso de fotografías debe estar en el área de fotografías.');
        $this->assertTrue($posAvisoPublicacion > $posBotonEnviar, 'El aviso de publicación debe ubicarse debajo de los botones de acción.');
    }

    /**
     * Criterio 3: Permitir registrar dos hallazgos con el mismo nombre por diferentes usuarios.
     */
    public function test_se_pueden_registrar_reportes_con_el_mismo_nombre_por_distintos_usuarios(): void
    {
        $userA = User::factory()->create(['role' => User::ROLE_USUARIO_GENERAL]);
        $userB = User::factory()->create(['role' => User::ROLE_USUARIO_GENERAL]);

        $tz = StoreHallazgoRequest::TIMEZONE;
        $mismoNombre = 'Termo de acero inoxidable';

        // Reporte de Usuario A
        $resA = $this->actingAs($userA)->post(route('reportes.store'), [
            'nombre' => $mismoNombre,
            'descripcion' => 'Termo color azul con calcomanías encontrado en las gradas de la cancha techada.',
            'fecha_hallazgo' => Carbon::now($tz)->subDay()->format('Y-m-d'),
            'area_zona' => 'Canchas deportivas',
            'ubicacion_especifica' => 'Gradas oriente',
            'fotografias' => [UploadedFile::fake()->image('termo_a.jpg')->size(200)],
            'form_token' => (string) Str::uuid(),
        ]);
        $resA->assertRedirect(route('reportes.mis-reportes'));

        // Reporte de Usuario B con el mismo nombre de objeto
        $resB = $this->actingAs($userB)->post(route('reportes.store'), [
            'nombre' => $mismoNombre,
            'descripcion' => 'Termo color negro con tapa hermética olvidado sobre una mesa de la cafetería central.',
            'fecha_hallazgo' => Carbon::now($tz)->subDay()->format('Y-m-d'),
            'area_zona' => 'Cafetería',
            'ubicacion_especifica' => 'Mesa 8',
            'fotografias' => [UploadedFile::fake()->image('termo_b.png')->size(250)],
            'form_token' => (string) Str::uuid(),
        ]);
        $resB->assertRedirect(route('reportes.mis-reportes'));

        $reportes = Publicacion::where('nombre', $mismoNombre)->get();
        $this->assertCount(2, $reportes);
        $this->assertNotEquals($reportes[0]->folio, $reportes[1]->folio);
        $this->assertNotEquals($reportes[0]->user_id, $reportes[1]->user_id);
    }

    /**
     * Criterio 3: Permitir registrar dos hallazgos distintos con el mismo nombre por el mismo usuario.
     */
    public function test_se_pueden_registrar_dos_hallazgos_distintos_con_el_mismo_nombre_por_el_mismo_usuario(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USUARIO_GENERAL]);
        $tz = StoreHallazgoRequest::TIMEZONE;
        $mismoNombre = 'Audífonos inalámbricos blancos';

        // Primer hallazgo registrado
        $res1 = $this->actingAs($user)->post(route('reportes.store'), [
            'nombre' => $mismoNombre,
            'descripcion' => 'Audífonos inalámbricos estuche color blanco con detalles grises hallados en biblioteca.',
            'fecha_hallazgo' => Carbon::now($tz)->subDays(2)->format('Y-m-d'),
            'area_zona' => 'Biblioteca',
            'ubicacion_especifica' => 'Estante de ingeniería',
            'fotografias' => [UploadedFile::fake()->image('audifono1.jpg')->size(200)],
            'form_token' => (string) Str::uuid(),
        ]);
        $res1->assertRedirect(route('reportes.mis-reportes'));

        // Segundo hallazgo distinto (distinta ubicación y descripción) con nuevo form_token
        $res2 = $this->actingAs($user)->post(route('reportes.store'), [
            'nombre' => $mismoNombre,
            'descripcion' => 'Audífonos inalámbricos blancos tipo diadema encontrados en el aula magna del campus.',
            'fecha_hallazgo' => Carbon::now($tz)->subDay()->format('Y-m-d'),
            'area_zona' => 'Aula Magna',
            'ubicacion_especifica' => 'Fila H asiento 12',
            'fotografias' => [UploadedFile::fake()->image('audifono2.png')->size(250)],
            'form_token' => (string) Str::uuid(),
        ]);
        $res2->assertRedirect(route('reportes.mis-reportes'));

        $reportes = Publicacion::where('user_id', $user->id)
            ->where('nombre', $mismoNombre)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $reportes);
        $this->assertNotEquals($reportes[0]->folio, $reportes[1]->folio);
        $this->assertEquals('Biblioteca', $reportes[0]->area_zona);
        $this->assertEquals('Aula Magna', $reportes[1]->area_zona);
    }

    /**
     * Criterio 3: Protección contra reenvíos duplicados de una misma solicitud sigue activa.
     */
    public function test_proteccion_contra_reenvios_duplicados_sigue_activa(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USUARIO_GENERAL]);
        $tz = StoreHallazgoRequest::TIMEZONE;
        $token = (string) Str::uuid();

        $payload = [
            'nombre' => 'Calculadora Casio fx-991',
            'descripcion' => 'Calculadora científica con tapa color negro encontrada en el salón de cómputo.',
            'fecha_hallazgo' => Carbon::now($tz)->subDay()->format('Y-m-d'),
            'area_zona' => 'Edificio G',
            'ubicacion_especifica' => 'Mesa 4',
            'fotografias' => [UploadedFile::fake()->image('casio.jpg')->size(200)],
            'form_token' => $token,
        ];

        // Primer envío
        $this->actingAs($user)->post(route('reportes.store'), $payload);

        // Segundo envío idéntico con el mismo token (simula doble clic o reenvío del navegador)
        $this->actingAs($user)->post(route('reportes.store'), $payload);

        // Debe existir únicamente 1 registro
        $this->assertEquals(1, Publicacion::where('user_id', $user->id)->count());
    }
}

