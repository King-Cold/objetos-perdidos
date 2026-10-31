<?php

namespace Tests\Feature;

use App\Http\Requests\StoreHallazgoRequest;
use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReporteEnvioTask055Test extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Cache::flush();

        $this->userA = User::factory()->create([
            'name' => 'Usuario A',
            'email' => 'usera@campus.tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $this->userB = User::factory()->create([
            'name' => 'Usuario B',
            'email' => 'userb@campus.tecnm.mx',
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);
    }

    protected function validPayload(array $overrides = []): array
    {
        $tz = StoreHallazgoRequest::TIMEZONE;

        return array_merge([
            'form_token' => (string) Str::uuid(),
            'nombre' => 'Laptop HP Gris Plata',
            'descripcion' => 'Laptop de 14 pulgadas con funda protectora negra y calcomanía institucional en la tapa.',
            'fecha_hallazgo' => Carbon::now($tz)->subDay()->format('Y-m-d'),
            'hora_hallazgo' => '11:30',
            'area_zona' => 'Edificio C - Biblioteca',
            'ubicacion_especifica' => 'Segunda planta mesa de estudio número 8',
            'fotografias' => [
                UploadedFile::fake()->image('laptop1.jpg', 800, 600),
            ],
        ], $overrides);
    }

    /**
     * Requisito 1: El usuario recibe la confirmación del envío exitoso.
     */
    public function test_1_usuario_recibe_confirmacion_de_envio_exitoso(): void
    {
        $payload = $this->validPayload();

        $response = $this->actingAs($this->userA)->post(route('reportes.store'), $payload);

        $response->assertSessionDoesntHaveErrors();
        $response->assertSessionHas('status');
        $response->assertSessionHas('folio');
        $response->assertSessionHas('estado', Publicacion::ESTADO_PENDIENTE);
    }

    /**
     * Requisito 2: Se muestra el folio correspondiente al reporte guardado.
     */
    public function test_2_se_muestra_el_folio_correspondiente_al_reporte_guardado(): void
    {
        $payload = $this->validPayload();

        $response = $this->actingAs($this->userA)->post(route('reportes.store'), $payload);

        $publicacion = Publicacion::first();
        $this->assertNotNull($publicacion);

        $response->assertSessionHas('folio', $publicacion->folio);
        $this->assertMatchesRegularExpression('/^OBJ-\d{8}-\d{4}$/', $publicacion->folio);
    }

    /**
     * Requisito 3: El usuario es redirigido a «Mis reportes».
     */
    public function test_3_el_usuario_es_redirigido_a_mis_reportes(): void
    {
        $payload = $this->validPayload();

        // 3.1 Redirección HTML convencional
        $response = $this->actingAs($this->userA)->post(route('reportes.store'), $payload);
        $response->assertRedirect(route('reportes.mis-reportes'));

        // 3.2 Respuesta AJAX/JSON incluye la URL de redirección
        $ajaxPayload = $this->validPayload([
            'nombre' => 'Calculadora Científica Casio',
            'descripcion' => 'Calculadora científica color azul marino olvidada en la mesa del laboratorio.',
            'fotografias' => [UploadedFile::fake()->image('calc.png')],
        ]);

        $ajaxResponse = $this->actingAs($this->userA)
            ->postJson(route('reportes.store'), $ajaxPayload);

        $ajaxResponse->assertOk();
        $ajaxResponse->assertJson([
            'status' => 'success',
            'redirect_url' => route('reportes.mis-reportes'),
        ]);
    }

    /**
     * Requisito 4: El reporte aparece con sus datos correctamente recuperados en Mis reportes.
     */
    public function test_4_el_reporte_aparece_con_sus_datos_correctamente_recuperados_en_mis_reportes(): void
    {
        $payload = $this->validPayload();
        $this->actingAs($this->userA)->post(route('reportes.store'), $payload);

        $publicacion = Publicacion::first();

        $viewResponse = $this->actingAs($this->userA)->get(route('reportes.mis-reportes'));
        $viewResponse->assertOk();

        // Comprobación de datos recuperados en la vista
        $viewResponse->assertSee($publicacion->folio);
        $viewResponse->assertSee($publicacion->nombre);
        $viewResponse->assertSee('Pendiente de aprobación');
        $viewResponse->assertSee($publicacion->area_zona);
        $viewResponse->assertSee(route('fotografias.show', $publicacion->fotografias->first()->id));
    }

    /**
     * Requisito 5: Un usuario solamente puede consultar sus propios reportes y no los de otras personas.
     */
    public function test_5_un_usuario_solamente_puede_consultar_sus_propios_reportes(): void
    {
        // Usuario A registra un reporte
        $payloadA = $this->validPayload([
            'nombre' => 'Mochila de Usuario A',
            'descripcion' => 'Mochila color negro perteneciente al usuario A olvidada en el campus.',
        ]);
        $this->actingAs($this->userA)->post(route('reportes.store'), $payloadA);
        $pubA = Publicacion::where('user_id', $this->userA->id)->first();

        // Usuario B registra un reporte
        $payloadB = $this->validPayload([
            'nombre' => 'Audífonos de Usuario B',
            'descripcion' => 'Audífonos inalámbricos de color blanco pertenecientes al usuario B.',
        ]);
        $this->actingAs($this->userB)->post(route('reportes.store'), $payloadB);
        $pubB = Publicacion::where('user_id', $this->userB->id)->first();

        // Usuario A visita Mis reportes: ve el suyo y NO el de B
        $this->flushSession();
        $responseA = $this->actingAs($this->userA)->get(route('reportes.mis-reportes'));
        $responseA->assertSee($pubA->folio);
        $responseA->assertDontSee($pubB->folio);

        // Usuario A intenta acceder a la edición del reporte de B mediante URL -> 403
        $editResponse = $this->actingAs($this->userA)->get(route('reportes.edit', $pubB->id));
        $editResponse->assertForbidden();

        // Usuario A intenta acceder a la fotografía del reporte pendiente de B -> 403
        $fotoB = $pubB->fotografias->first();
        $fotoResponse = $this->actingAs($this->userA)->get(route('fotografias.show', $fotoB->id));
        $fotoResponse->assertForbidden();
    }

    /**
     * Requisito 6: Las fotografías no quedan disponibles mediante acceso público directo.
     */
    public function test_6_las_fotografias_no_quedan_disponibles_mediante_acceso_publico_directo(): void
    {
        $payload = $this->validPayload();
        $this->actingAs($this->userA)->post(route('reportes.store'), $payload);

        $foto = Fotografia::first();
        $this->assertNotNull($foto);

        // 1. Están en disco privado 'local', no en 'public'
        Storage::disk('local')->assertExists($foto->ruta);
        Storage::disk('public')->assertMissing($foto->ruta);

        // 2. Un visitante no autenticado (público) que intenta consultar la ruta recibe redirección/bloqueo
        auth()->logout();
        $guestResponse = $this->get(route('fotografias.show', $foto->id));
        $guestResponse->assertRedirect(route('login'));

        // 3. El autor autenticado SÍ puede visualizarla a través de la ruta controlada
        $authorResponse = $this->actingAs($this->userA)->get(route('fotografias.show', $foto->id));
        $authorResponse->assertOk();
    }

    /**
     * Requisito 7: Los envíos repetidos no generan registros duplicados (idempotencia).
     */
    public function test_7_los_envios_repetidos_no_generan_registros_duplicados(): void
    {
        $payload = $this->validPayload();

        // Primer envío
        $this->actingAs($this->userA)->post(route('reportes.store'), $payload);
        $this->assertDatabaseCount('publicaciones', 1);
        $this->assertDatabaseCount('fotografias', 1);

        // Segundo envío con el mismo payload / form_token (simulación de doble clic o reenvío)
        $res2 = $this->actingAs($this->userA)->post(route('reportes.store'), $payload);
        $res2->assertRedirect(route('reportes.mis-reportes'));

        // Se mantiene exactamente 1 publicación y 1 fotografía en base de datos
        $this->assertDatabaseCount('publicaciones', 1);
        $this->assertDatabaseCount('fotografias', 1);

        // No se acumularon archivos duplicados en disco
        $archivos = Storage::disk('local')->allFiles('hallazgos');
        $this->assertCount(1, $archivos);
    }

    /**
     * Requisito 8: Un usuario puede registrar un hallazgo diferente después de uno anterior.
     */
    public function test_8_un_usuario_puede_registrar_un_hallazgo_diferente_despues_de_uno_anterior(): void
    {
        // Primer hallazgo
        $payload1 = $this->validPayload([
            'form_token' => (string) Str::uuid(),
            'nombre' => 'Termo metálico azul',
            'descripcion' => 'Termo de acero inoxidable color azul marino con tapa negra encontrado en la cafetería.',
            'fotografias' => [UploadedFile::fake()->image('termo.jpg')],
        ]);
        $this->actingAs($this->userA)->post(route('reportes.store'), $payload1);

        // Segundo hallazgo diferente
        $payload2 = $this->validPayload([
            'form_token' => (string) Str::uuid(),
            'nombre' => 'Suéter escolar gris',
            'descripcion' => 'Suéter escolar abierto de color gris talla mediana con bordado de la institución en el pecho.',
            'fotografias' => [UploadedFile::fake()->image('sueter.jpg')],
        ]);
        $this->actingAs($this->userA)->post(route('reportes.store'), $payload2);

        $this->assertDatabaseCount('publicaciones', 2);
        $reportes = Publicacion::orderBy('id')->get();

        $this->assertEquals('OBJ-' . Carbon::now(StoreHallazgoRequest::TIMEZONE)->format('Ymd') . '-0001', $reportes[0]->folio);
        $this->assertEquals('OBJ-' . Carbon::now(StoreHallazgoRequest::TIMEZONE)->format('Ymd') . '-0002', $reportes[1]->folio);
    }

    /**
     * Requisito 9: Los errores durante el almacenamiento no dejan registros incompletos.
     */
    public function test_9_errores_durante_almacenamiento_no_dejan_registros_incompletos(): void
    {
        // Forzamos un fallo en base de datos durante la creación
        Publicacion::saving(function ($model) {
            if ($model->nombre === 'FALLO_PROVOCADO') {
                throw new \Exception('Simulación de error en base de datos');
            }
        });

        $payload = $this->validPayload([
            'nombre' => 'FALLO_PROVOCADO',
            'fotografias' => [UploadedFile::fake()->image('fallo.jpg')],
        ]);

        $response = $this->actingAs($this->userA)->post(route('reportes.store'), $payload);

        $response->assertSessionHas('error', 'Ocurrió un error al intentar registrar el reporte. Por favor, intenta nuevamente.');
        $this->assertDatabaseCount('publicaciones', 0);
        $this->assertDatabaseCount('fotografias', 0);
    }

    /**
     * Requisito 10: Los archivos temporales o almacenados parcialmente se eliminan cuando ocurre un fallo.
     */
    public function test_10_archivos_temporales_se_eliminan_cuando_ocurre_un_fallo(): void
    {
        // 1. Guardar primero un reporte exitoso legítimo
        $payloadLegitimo = $this->validPayload([
            'nombre' => 'Objeto Guardado Previamente',
            'descripcion' => 'Objeto que fue guardado sin problemas en una transacción anterior exitosa.',
            'fotografias' => [UploadedFile::fake()->image('exitoso.jpg')],
        ]);
        $this->actingAs($this->userA)->post(route('reportes.store'), $payloadLegitimo);
        $this->assertDatabaseCount('publicaciones', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('hallazgos'));

        // 2. Intentar guardar un segundo reporte que falla intencionalmente
        Publicacion::saving(function ($model) {
            if ($model->nombre === 'FALLO_CON_ARCHIVOS') {
                throw new \Exception('Simulación de fallo durante guardado');
            }
        });

        $payloadFallido = $this->validPayload([
            'form_token' => (string) Str::uuid(),
            'nombre' => 'FALLO_CON_ARCHIVOS',
            'descripcion' => 'Este intento debe fallar y limpiar únicamente sus archivos nuevos.',
            'fotografias' => [
                UploadedFile::fake()->image('archivo_que_debe_borrarse.png'),
            ],
        ]);

        $response = $this->actingAs($this->userA)->post(route('reportes.store'), $payloadFallido);

        // La base de datos conserva solo el primero
        $this->assertDatabaseCount('publicaciones', 1);

        // El disco conserva únicamente el archivo del reporte exitoso anterior,
        // habiendo eliminado el archivo de la transacción que falló
        $archivosRestantes = Storage::disk('local')->allFiles('hallazgos');
        $this->assertCount(1, $archivosRestantes);
        $fotoExistente = Fotografia::first();
        $this->assertEquals($fotoExistente->ruta, $archivosRestantes[0]);
    }
}
