<?php

namespace Tests\Feature;

use App\Http\Requests\StoreHallazgoRequest;
use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReporteGuardadoTask054Test extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->user = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);
    }

    protected function validPayload(array $overrides = []): array
    {
        $tz = StoreHallazgoRequest::TIMEZONE;

        return array_merge([
            'nombre' => 'Mochila escolar negra',
            'descripcion' => 'Mochila escolar color negro con franjas grises y cierre metálico encontrada en pasillo.',
            'fecha_hallazgo' => Carbon::now($tz)->subDay()->format('Y-m-d'),
            'hora_hallazgo' => '14:30',
            'area_zona' => 'Edificio G - Sistemas',
            'ubicacion_especifica' => 'Segunda fila del aula G-102',
            'fotografias' => [
                UploadedFile::fake()->image('foto1.jpg', 800, 600),
                UploadedFile::fake()->image('foto2.png', 800, 600),
            ],
        ], $overrides);
    }

    /**
     * 1. Usuario autenticado puede registrar un hallazgo exitosamente.
     */
    public function test_usuario_autenticado_puede_registrar_un_hallazgo(): void
    {
        $payload = $this->validPayload();

        $response = $this->actingAs($this->user)->post(route('reportes.store'), $payload);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('folio');
        $response->assertSessionHas('status');
        $this->assertDatabaseCount('publicaciones', 1);
    }

    /**
     * 2. Se genera un folio único irrepetible con formato OBJ-YYYYMMDD-XXXX.
     */
    public function test_se_genera_un_folio_unico_irrepetible(): void
    {
        $now = Carbon::now(StoreHallazgoRequest::TIMEZONE);
        $expectedPrefix = 'OBJ-' . $now->format('Ymd') . '-';

        $payload1 = $this->validPayload();
        $this->actingAs($this->user)->post(route('reportes.store'), $payload1);

        $payload2 = $this->validPayload([
            'nombre' => 'Calculadora Casio fx-991',
            'descripcion' => 'Calculadora científica color negro encontrada sobre la mesa de trabajo de cómputo.',
            'fotografias' => [UploadedFile::fake()->image('calc.png')],
        ]);
        $this->actingAs($this->user)->post(route('reportes.store'), $payload2);

        $reportes = Publicacion::orderBy('id')->get();
        $this->assertCount(2, $reportes);

        $this->assertStringStartsWith($expectedPrefix, $reportes[0]->folio);
        $this->assertStringStartsWith($expectedPrefix, $reportes[1]->folio);
        $this->assertNotEquals($reportes[0]->folio, $reportes[1]->folio);
        $this->assertEquals($expectedPrefix . '0001', $reportes[0]->folio);
        $this->assertEquals($expectedPrefix . '0002', $reportes[1]->folio);
    }

    /**
     * 3. El reporte queda asociado estrictamente al usuario autenticado (ignora user_id manipulado).
     */
    public function test_el_reporte_queda_asociado_al_usuario_autenticado(): void
    {
        $otroUsuario = User::factory()->create();

        // Intento de suplantar autor enviando user_id de otro usuario
        $payload = $this->validPayload([
            'user_id' => $otroUsuario->id,
        ]);

        $this->actingAs($this->user)->post(route('reportes.store'), $payload);

        $publicacion = Publicacion::first();
        $this->assertNotNull($publicacion);
        $this->assertEquals($this->user->id, $publicacion->user_id);
        $this->assertNotEquals($otroUsuario->id, $publicacion->user_id);
    }

    /**
     * 4. Se guardan correctamente todos los datos del hallazgo en la base de datos.
     */
    public function test_se_guardan_correctamente_los_datos_del_hallazgo(): void
    {
        $payload = $this->validPayload([
            'nombre' => 'Audífonos Sony inalámbricos',
            'descripcion' => 'Audífonos inalámbricos de diadema color negro con almohadillas en buen estado.',
            'fecha_hallazgo' => Carbon::now(StoreHallazgoRequest::TIMEZONE)->subDays(2)->format('Y-m-d'),
            'hora_hallazgo' => '11:15',
            'area_zona' => 'Biblioteca Campus Poniente',
            'ubicacion_especifica' => 'Mesa de lectura 3 en planta baja',
        ]);

        $this->actingAs($this->user)->post(route('reportes.store'), $payload);

        $publicacion = Publicacion::first();
        $this->assertNotNull($publicacion);
        $this->assertEquals('Audífonos Sony inalámbricos', $publicacion->nombre);
        $this->assertEquals('Audífonos Sony inalámbricos', $publicacion->titulo);
        $this->assertEquals('Audífonos inalámbricos de diadema color negro con almohadillas en buen estado.', $publicacion->descripcion);
        $this->assertEquals(Carbon::now(StoreHallazgoRequest::TIMEZONE)->subDays(2)->format('Y-m-d'), $publicacion->fecha_hallazgo->format('Y-m-d'));
        $this->assertEquals('11:15', substr((string) $publicacion->hora_hallazgo, 0, 5));
        $this->assertEquals('Biblioteca Campus Poniente', $publicacion->area_zona);
        $this->assertEquals('Mesa de lectura 3 en planta baja', $publicacion->ubicacion_especifica);
    }

    /**
     * 5 y 6. Fecha del hallazgo se conserva independiente de la fecha y hora de captura generadas en el servidor.
     */
    public function test_fecha_hallazgo_se_conserva_independiente_de_fecha_y_hora_de_captura(): void
    {
        $fechaHallazgo = Carbon::now(StoreHallazgoRequest::TIMEZONE)->subDays(5)->format('Y-m-d');
        $payload = $this->validPayload([
            'fecha_hallazgo' => $fechaHallazgo,
        ]);

        $this->actingAs($this->user)->post(route('reportes.store'), $payload);

        $publicacion = Publicacion::first();
        $this->assertNotNull($publicacion);

        // La fecha del hallazgo es la fecha en que se encontró
        $this->assertEquals($fechaHallazgo, $publicacion->fecha_hallazgo->format('Y-m-d'));

        // La fecha y hora de captura corresponde a hoy en el servidor (America/Merida)
        $this->assertNotNull($publicacion->fecha_captura);
        $todayMerida = Carbon::now(StoreHallazgoRequest::TIMEZONE)->format('Y-m-d');
        $this->assertEquals($todayMerida, $publicacion->fecha_captura->format('Y-m-d'));
        $this->assertNotEquals($publicacion->fecha_hallazgo->format('Y-m-d'), $publicacion->fecha_captura->format('Y-m-d'));
    }

    /**
     * 7. Se almacenan físicamente las fotografías en storage/app/public/hallazgos y se asocian al reporte.
     */
    public function test_se_almacenan_las_fotografias_y_quedan_asociadas_al_reporte(): void
    {
        $payload = $this->validPayload([
            'fotografias' => [
                UploadedFile::fake()->image('prueba1.jpg', 600, 400),
                UploadedFile::fake()->image('prueba2.png', 600, 400),
            ],
        ]);

        $this->actingAs($this->user)->post(route('reportes.store'), $payload);

        $publicacion = Publicacion::first();
        $this->assertNotNull($publicacion);

        $fotos = $publicacion->fotografias;
        $this->assertCount(2, $fotos);

        foreach ($fotos as $foto) {
            $this->assertStringStartsWith('hallazgos/', $foto->ruta);
            Storage::disk('local')->assertExists($foto->ruta);
            $this->assertNotNull($foto->nombre_original);
            $this->assertNotNull($foto->tamano_bytes);
        }
    }

    /**
     * 8. Se respeta el límite de 1 a 5 fotografías.
     */
    public function test_respeta_limite_de_fotografias_de_una_a_cinco(): void
    {
        // 0 fotos -> Error
        $res0 = $this->actingAs($this->user)->post(route('reportes.store'), $this->validPayload([
            'fotografias' => [],
        ]));
        $res0->assertSessionHasErrors('fotografias');

        // 6 fotos -> Error
        $res6 = $this->actingAs($this->user)->post(route('reportes.store'), $this->validPayload([
            'fotografias' => [
                UploadedFile::fake()->image('f1.jpg'),
                UploadedFile::fake()->image('f2.jpg'),
                UploadedFile::fake()->image('f3.jpg'),
                UploadedFile::fake()->image('f4.jpg'),
                UploadedFile::fake()->image('f5.jpg'),
                UploadedFile::fake()->image('f6.jpg'),
            ],
        ]));
        $res6->assertSessionHasErrors('fotografias');

        // 3 fotos -> Éxito
        $res3 = $this->actingAs($this->user)->post(route('reportes.store'), $this->validPayload([
            'fotografias' => [
                UploadedFile::fake()->image('f1.jpg'),
                UploadedFile::fake()->image('f2.png'),
                UploadedFile::fake()->image('f3.jpg'),
            ],
        ]));
        $res3->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('publicaciones', 1);
        $this->assertDatabaseCount('fotografias', 3);
    }

    /**
     * 9. Se respeta que solo se aceptan formatos JPG y PNG y máximo 2 MB.
     */
    public function test_respeta_formatos_jpg_png_y_tamano_maximo_dos_megabytes(): void
    {
        // Formato no permitido (PDF)
        $resPdf = $this->actingAs($this->user)->post(route('reportes.store'), $this->validPayload([
            'fotografias' => [UploadedFile::fake()->create('doc.pdf', 500, 'application/pdf')],
        ]));
        $resPdf->assertSessionHasErrors('fotografias.0');

        // Formato no permitido (WEBP)
        $resWebp = $this->actingAs($this->user)->post(route('reportes.store'), $this->validPayload([
            'fotografias' => [UploadedFile::fake()->create('foto.webp', 500, 'image/webp')],
        ]));
        $resWebp->assertSessionHasErrors('fotografias.0');

        // Tamaño mayor a 2 MB (2500 KB > 2048 KB)
        $resPesada = $this->actingAs($this->user)->post(route('reportes.store'), $this->validPayload([
            'fotografias' => [UploadedFile::fake()->create('pesada.jpg', 2500, 'image/jpeg')],
        ]));
        $resPesada->assertSessionHasErrors('fotografias.0');
    }

    /**
     * 10 y 11. El estado inicial es exclusivamente 'Pendiente de aprobación' y no se inicia ninguna vigencia ni publicación.
     */
    public function test_estado_inicial_es_pendiente_de_aprobacion_sin_vigencia_ni_publicacion(): void
    {
        $this->actingAs($this->user)->post(route('reportes.store'), $this->validPayload());

        $publicacion = Publicacion::first();
        $this->assertNotNull($publicacion);

        // Estado inicial
        $this->assertEquals('Pendiente de aprobación', $publicacion->estado);

        // No está disponible para reclamación ni se ha confirmado en biblioteca
        $this->assertFalse($publicacion->disponible);
        $this->assertFalse($publicacion->isDisponible());

        // No aparece en el muro de objetos aprobados
        $muroResponse = $this->actingAs($this->user)->get(route('muro'));
        $muroResponse->assertDontSee($publicacion->nombre);
    }

    /**
     * 12. Usuario no autenticado no puede registrar reportes (redirigido a login).
     */
    public function test_usuario_no_autenticado_no_puede_registrar_reportes(): void
    {
        $response = $this->post(route('reportes.store'), $this->validPayload());

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('publicaciones', 0);
    }

    /**
     * 13. Un error durante el proceso realiza rollback y no deja registros ni archivos incompletos.
     */
    public function test_error_en_proceso_no_deja_registros_ni_archivos_incompletos(): void
    {
        // Forzamos un error en el evento creating de Publicacion
        Publicacion::saving(function ($model) {
            if ($model->nombre === 'FORZAR_ERROR_TRANSACCION') {
                throw new \Exception('Simulación de error en base de datos');
            }
        });

        $payload = $this->validPayload([
            'nombre' => 'FORZAR_ERROR_TRANSACCION',
            'fotografias' => [
                UploadedFile::fake()->image('archivo_huerfano.jpg'),
            ],
        ]);

        $response = $this->actingAs($this->user)->post(route('reportes.store'), $payload);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('publicaciones', 0);
        $this->assertDatabaseCount('fotografias', 0);

        // Comprobar que no quedaron archivos huérfanos en el disco
        $archivosEnStorage = Storage::disk('local')->allFiles('hallazgos');
        $this->assertEmpty($archivosEnStorage);
    }
}

