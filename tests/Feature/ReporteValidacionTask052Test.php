<?php

namespace Tests\Feature;

use App\Http\Requests\StoreHallazgoRequest;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReporteValidacionTask052Test extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);
    }

    /**
     * Retorna una carga útil válida por defecto para pruebas.
     */
    protected function validPayload(array $overrides = []): array
    {
        Storage::fake('public');
        $tz = StoreHallazgoRequest::TIMEZONE;

        return array_merge([
            'nombre' => 'Calculadora Científica Casio',
            'descripcion' => 'Calculadora color negro con tapa deslizante encontrada en el laboratorio de cómputo.',
            'fecha_hallazgo' => Carbon::now($tz)->format('Y-m-d'),
            'hora_hallazgo' => null,
            'area_zona' => 'Edificio G - Sistemas',
            'ubicacion_especifica' => 'Laboratorio G-102 mesa 3',
            'fotografias' => [
                UploadedFile::fake()->image('foto1.jpg', 600, 400),
            ],
        ], $overrides);
    }

    /**
     * Requisito 2: Campos obligatorios no aceptan valores vacíos.
     */
    public function test_campos_obligatorios_no_aceptan_valores_vacios(): void
    {
        $response = $this->actingAs($this->user)->post(route('reportes.store'), []);

        $response->assertSessionHasErrors([
            'nombre',
            'descripcion',
            'fecha_hallazgo',
            'area_zona',
            'ubicacion_especifica',
        ]);
    }

    /**
     * Requisito 2: Campos obligatorios no aceptan únicamente espacios en blanco.
     */
    public function test_campos_obligatorios_no_aceptan_unicamente_espacios_en_blanco(): void
    {
        $response = $this->actingAs($this->user)->post(route('reportes.store'), [
            'nombre' => '     ',
            'descripcion' => '                                                                    ',
            'fecha_hallazgo' => '   ',
            'area_zona' => '   ',
            'ubicacion_especifica' => '     ',
        ]);

        $response->assertSessionHasErrors([
            'nombre',
            'descripcion',
            'fecha_hallazgo',
            'area_zona',
            'ubicacion_especifica',
        ]);
    }

    /**
     * Requisito 3: La descripción acepta exactamente 50 caracteres.
     */
    public function test_descripcion_acepta_exactamente_50_caracteres(): void
    {
        $exact50 = str_repeat('a', 50);

        $response = $this->actingAs($this->user)->post(route('reportes.store'), [
            'nombre' => 'Calculadora Casio fx-991',
            'descripcion' => $exact50,
            'fecha_hallazgo' => Carbon::now(StoreHallazgoRequest::TIMEZONE)->format('Y-m-d'),
            'area_zona' => 'Edificio G - Sistemas',
            'ubicacion_especifica' => 'Mesa central de cómputo',
        ]);

        $response->assertSessionDoesntHaveErrors('descripcion');
    }

    /**
     * Requisito 3: La descripción acepta exactamente 200 caracteres.
     */
    public function test_descripcion_acepta_exactamente_200_caracteres(): void
    {
        $exact200 = str_repeat('b', 200);

        $response = $this->actingAs($this->user)->post(route('reportes.store'), [
            'nombre' => 'Mochila negra con franjas',
            'descripcion' => $exact200,
            'fecha_hallazgo' => Carbon::now(StoreHallazgoRequest::TIMEZONE)->format('Y-m-d'),
            'area_zona' => 'Biblioteca Campus Poniente',
            'ubicacion_especifica' => 'Segunda planta estante 4',
        ]);

        $response->assertSessionDoesntHaveErrors('descripcion');
    }

    /**
     * Requisito 3: Se rechazan descripciones menores de 50 o mayores de 200 caracteres.
     */
    public function test_descripcion_rechaza_menores_de_50_o_mayores_de_200_caracteres(): void
    {
        // 49 caracteres (insuficiente)
        $short49 = str_repeat('c', 49);
        $resShort = $this->actingAs($this->user)->post(route('reportes.store'), [
            'nombre' => 'Libro de Cálculo',
            'descripcion' => $short49,
            'fecha_hallazgo' => Carbon::now(StoreHallazgoRequest::TIMEZONE)->format('Y-m-d'),
            'area_zona' => 'Edificio A - Dirección y Aulas',
            'ubicacion_especifica' => 'Banca exterior',
        ]);

        $resShort->assertSessionHasErrors([
            'descripcion' => 'La descripción debe contener al menos 50 caracteres.',
        ]);

        // 201 caracteres (excedente)
        $long201 = str_repeat('d', 201);
        $resLong = $this->actingAs($this->user)->post(route('reportes.store'), [
            'nombre' => 'Libro de Cálculo',
            'descripcion' => $long201,
            'fecha_hallazgo' => Carbon::now(StoreHallazgoRequest::TIMEZONE)->format('Y-m-d'),
            'area_zona' => 'Edificio A - Dirección y Aulas',
            'ubicacion_especifica' => 'Banca exterior',
        ]);

        $resLong->assertSessionHasErrors([
            'descripcion' => 'La descripción no debe superar los 200 caracteres.',
        ]);

        // 49 caracteres rodeados de espacios en blanco (debe recortarse y fallar)
        $paddedShort = '   ' . str_repeat('e', 49) . '   ';
        $resPadded = $this->actingAs($this->user)->post(route('reportes.store'), [
            'nombre' => 'Libro de Cálculo',
            'descripcion' => $paddedShort,
            'fecha_hallazgo' => Carbon::now(StoreHallazgoRequest::TIMEZONE)->format('Y-m-d'),
            'area_zona' => 'Edificio A - Dirección y Aulas',
            'ubicacion_especifica' => 'Banca exterior',
        ]);

        $resPadded->assertSessionHasErrors([
            'descripcion' => 'La descripción debe contener al menos 50 caracteres.',
        ]);
    }

    /**
     * Requisito 4: Se rechazan fechas futuras.
     */
    public function test_fecha_hallazgo_rechaza_fechas_futuras(): void
    {
        $futureDate = Carbon::now(StoreHallazgoRequest::TIMEZONE)->addDay()->format('Y-m-d');

        $response = $this->actingAs($this->user)->post(route('reportes.store'), [
            'nombre' => 'Termo azul metálico',
            'descripcion' => 'Termo azul metálico con calcomanía de la carrera de ingeniería en sistemas.',
            'fecha_hallazgo' => $futureDate,
            'area_zona' => 'Cafetería',
            'ubicacion_especifica' => 'Mesa de comedor',
        ]);

        $response->assertSessionHasErrors([
            'fecha_hallazgo' => 'La fecha del hallazgo no puede ser una fecha futura.',
        ]);
    }

    /**
     * Requisito 4: Se rechazan fechas anteriores al límite de 7 días.
     */
    public function test_fecha_hallazgo_rechaza_fechas_anteriores_al_limite_de_7_dias(): void
    {
        $past8Days = Carbon::now(StoreHallazgoRequest::TIMEZONE)->subDays(8)->format('Y-m-d');

        $response = $this->actingAs($this->user)->post(route('reportes.store'), [
            'nombre' => 'Audífonos Bluetooth negros',
            'descripcion' => 'Audífonos inalámbricos de color negro en estuche de carga rectangular.',
            'fecha_hallazgo' => $past8Days,
            'area_zona' => 'Edificio B - Laboratorios',
            'ubicacion_especifica' => 'Laboratorio de redes',
        ]);

        $response->assertSessionHasErrors([
            'fecha_hallazgo' => 'Solo se pueden registrar objetos encontrados durante los últimos 7 días.',
        ]);
    }

    /**
     * Requisito 4: Se acepta la fecha correspondiente al límite de 7 días.
     */
    public function test_fecha_hallazgo_acepta_fecha_limite_de_7_dias(): void
    {
        $exact7DaysAgo = Carbon::now(StoreHallazgoRequest::TIMEZONE)->subDays(7)->format('Y-m-d');

        $response = $this->actingAs($this->user)->post(route('reportes.store'), [
            'nombre' => 'Credencial de estudiante TecNM',
            'descripcion' => 'Credencial oficial de estudiante vigente extraviada en las bancas comunes.',
            'fecha_hallazgo' => $exact7DaysAgo,
            'area_zona' => 'Canchas y Explanada',
            'ubicacion_especifica' => 'Gradas de la cancha deportiva',
        ]);

        $response->assertSessionDoesntHaveErrors('fecha_hallazgo');
    }

    /**
     * Requisito 4: Se acepta la fecha actual (hoy).
     */
    public function test_fecha_hallazgo_acepta_fecha_actual(): void
    {
        $today = Carbon::now(StoreHallazgoRequest::TIMEZONE)->format('Y-m-d');

        $response = $this->actingAs($this->user)->post(route('reportes.store'), [
            'nombre' => 'Cuaderno profesional de cuadros',
            'descripcion' => 'Cuaderno profesional de cuadrícula grande con apuntes de programación web.',
            'fecha_hallazgo' => $today,
            'area_zona' => 'Edificio H - Sistemas',
            'ubicacion_especifica' => 'Aula H-102 en la primera fila',
        ]);

        $response->assertSessionDoesntHaveErrors('fecha_hallazgo');
    }

    /**
     * Requisito 5: La hora puede dejarse vacía.
     */
    public function test_hora_hallazgo_puede_dejarse_vacia(): void
    {
        $today = Carbon::now(StoreHallazgoRequest::TIMEZONE)->format('Y-m-d');

        $response = $this->actingAs($this->user)->post(route('reportes.store'), [
            'nombre' => 'Memoria USB Kingston 32GB',
            'descripcion' => 'Memoria USB plateada metálica encontrada conectada en una computadora del taller.',
            'fecha_hallazgo' => $today,
            'hora_hallazgo' => '',
            'area_zona' => 'Centro de Cómputo',
            'ubicacion_especifica' => 'Equipo número 15',
        ]);

        $response->assertSessionDoesntHaveErrors('hora_hallazgo');
    }

    /**
     * Requisito 5: Se rechazan horas con formato inválido.
     */
    public function test_hora_hallazgo_rechaza_formato_invalido(): void
    {
        $today = Carbon::now(StoreHallazgoRequest::TIMEZONE)->format('Y-m-d');

        $response = $this->actingAs($this->user)->post(route('reportes.store'), [
            'nombre' => 'Memoria USB Kingston 32GB',
            'descripcion' => 'Memoria USB plateada metálica encontrada conectada en una computadora del taller.',
            'fecha_hallazgo' => $today,
            'hora_hallazgo' => '25:99',
            'area_zona' => 'Centro de Cómputo',
            'ubicacion_especifica' => 'Equipo número 15',
        ]);

        $response->assertSessionHasErrors([
            'hora_hallazgo' => 'La hora debe tener un formato válido (HH:MM).',
        ]);
    }

    /**
     * Requisito 5: No se permite una hora futura cuando el hallazgo corresponde al día actual.
     */
    public function test_hora_hallazgo_no_permite_hora_futura_para_el_dia_actual(): void
    {
        $tz = StoreHallazgoRequest::TIMEZONE;
        $now = Carbon::now($tz);
        $today = $now->format('Y-m-d');

        // Si la hora actual es antes de las 23:00, sumamos 1 hora; si no, fijamos 23:59 con now atrasado
        if ($now->hour < 23) {
            $futureHour = $now->copy()->addHour()->format('H:i');
        } else {
            // Caso borde de fin de día: mockeando hora futura ficticia
            $futureHour = '23:59';
            Carbon::setTestNow(Carbon::create(2026, 10, 31, 10, 0, 0, $tz));
            $now = Carbon::now($tz);
            $today = $now->format('Y-m-d');
        }

        try {
            $response = $this->actingAs($this->user)->post(route('reportes.store'), [
                'nombre' => 'Cargador de laptop Lenovo',
                'descripcion' => 'Cargador tipo C de 65W negro con cable largo enrollado con cincho.',
                'fecha_hallazgo' => $today,
                'hora_hallazgo' => $futureHour,
                'area_zona' => 'Edificio G - Sistemas',
                'ubicacion_especifica' => 'Laboratorio de software',
            ]);

            $response->assertSessionHasErrors([
                'hora_hallazgo' => 'La hora del hallazgo no puede ser posterior a la hora actual.',
            ]);
        } finally {
            Carbon::setTestNow(); // Restaurar reloj
        }
    }

    /**
     * Requisito 5: Se permite una hora válida cuando el hallazgo corresponde a un día anterior.
     */
    public function test_hora_hallazgo_permite_hora_valida_para_dia_anterior(): void
    {
        $yesterday = Carbon::now(StoreHallazgoRequest::TIMEZONE)->subDay()->format('Y-m-d');

        $response = $this->actingAs($this->user)->post(route('reportes.store'), [
            'nombre' => 'Chamarra deportiva gris',
            'descripcion' => 'Chamarra con logotipo deportivo talla mediana dejada en las gradas.',
            'fecha_hallazgo' => $yesterday,
            'hora_hallazgo' => '23:45',
            'area_zona' => 'Canchas y Explanada',
            'ubicacion_especifica' => 'Gradas superiores',
        ]);

        $response->assertSessionDoesntHaveErrors('hora_hallazgo');
    }

    /**
     * Requisito 6: Los errores conservan los valores ingresados.
     */
    public function test_fallo_de_validacion_conserva_valores_ingresados(): void
    {
        $response = $this->actingAs($this->user)->post(route('reportes.store'), [
            'nombre' => 'Mochila roja',
            'descripcion' => 'Muy corta', // Falla por < 50 chars
            'fecha_hallazgo' => Carbon::now(StoreHallazgoRequest::TIMEZONE)->format('Y-m-d'),
            'area_zona' => 'Edificio G - Sistemas',
            'ubicacion_especifica' => 'Aula G-101',
        ]);

        $response->assertSessionHasErrors('descripcion');
        $this->assertEquals('Mochila roja', session('_old_input.nombre'));
        $this->assertEquals('Edificio G - Sistemas', session('_old_input.area_zona'));
        $this->assertEquals('Aula G-101', session('_old_input.ubicacion_especifica'));
    }

    /**
     * Requisito de validación: Datos válidos pasan sin errores de sesión y generan redirección con estatus.
     */
    public function test_validacion_exitosa_procesa_correctamente_sin_errores(): void
    {
        $payload = $this->validPayload();

        $response = $this->actingAs($this->user)->post(route('reportes.store'), $payload);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('status');
        $response->assertSessionHas('folio');
    }

    /**
     * Requisito Fotografías: Se exige al menos 1 fotografía del objeto.
     */
    public function test_fotografias_son_obligatorias_minimo_una(): void
    {
        $payload = $this->validPayload([
            'fotografias' => null,
        ]);

        $response = $this->actingAs($this->user)->post(route('reportes.store'), $payload);

        $response->assertSessionHasErrors([
            'fotografias' => 'Debes adjuntar al menos una fotografía del objeto.',
        ]);
    }

    /**
     * Requisito Fotografías: Se rechaza cuando se intentan subir más de 5 fotografías.
     */
    public function test_fotografias_rechaza_mas_de_cinco_fotos(): void
    {
        $payload = $this->validPayload([
            'fotografias' => [
                UploadedFile::fake()->image('foto1.jpg'),
                UploadedFile::fake()->image('foto2.jpg'),
                UploadedFile::fake()->image('foto3.jpg'),
                UploadedFile::fake()->image('foto4.jpg'),
                UploadedFile::fake()->image('foto5.jpg'),
                UploadedFile::fake()->image('foto6.jpg'),
            ],
        ]);

        $response = $this->actingAs($this->user)->post(route('reportes.store'), $payload);

        $response->assertSessionHasErrors([
            'fotografias' => 'No puedes subir más de 5 fotografías.',
        ]);
    }

    /**
     * Requisito Fotografías: Se acepta entre 1 y 5 fotografías válidas.
     */
    public function test_fotografias_acepta_entre_una_y_cinco_fotos(): void
    {
        // 1 foto
        $res1 = $this->actingAs($this->user)->post(route('reportes.store'), $this->validPayload([
            'fotografias' => [
                UploadedFile::fake()->image('foto1.jpg'),
            ],
        ]));
        $res1->assertSessionDoesntHaveErrors('fotografias');

        // 5 fotos
        $res5 = $this->actingAs($this->user)->post(route('reportes.store'), $this->validPayload([
            'fotografias' => [
                UploadedFile::fake()->image('foto1.jpg'),
                UploadedFile::fake()->image('foto2.jpg'),
                UploadedFile::fake()->image('foto3.jpg'),
                UploadedFile::fake()->image('foto4.jpg'),
                UploadedFile::fake()->image('foto5.jpg'),
            ],
        ]));
        $res5->assertSessionDoesntHaveErrors('fotografias');
    }

    /**
     * Requisito Fotografías: Se rechazan formatos que no sean JPG o PNG (por ejemplo WEBP o PDF).
     */
    public function test_fotografias_rechaza_archivos_no_imagen(): void
    {
        // Prueba con PDF
        $resPdf = $this->actingAs($this->user)->post(route('reportes.store'), $this->validPayload([
            'fotografias' => [
                UploadedFile::fake()->create('documento.pdf', 500, 'application/pdf'),
            ],
        ]));
        $resPdf->assertSessionHasErrors([
            'fotografias.0' => 'Las fotografías deben estar en formato JPG o PNG.',
        ]);

        // Prueba con WEBP (rechazado, solo JPG y PNG permitidos)
        $resWebp = $this->actingAs($this->user)->post(route('reportes.store'), $this->validPayload([
            'fotografias' => [
                UploadedFile::fake()->create('imagen.webp', 500, 'image/webp'),
            ],
        ]));
        $resWebp->assertSessionHasErrors([
            'fotografias.0' => 'Las fotografías deben estar en formato JPG o PNG.',
        ]);
    }

    /**
     * Requisito Fotografías: Se rechazan fotografías que excedan los 2 MB (2048 KB).
     */
    public function test_fotografias_rechaza_archivos_mayores_a_dos_megabytes(): void
    {
        $payload = $this->validPayload([
            'fotografias' => [
                UploadedFile::fake()->create('foto_pesada.jpg', 2500, 'image/jpeg'),
            ],
        ]);

        $response = $this->actingAs($this->user)->post(route('reportes.store'), $payload);

        $response->assertSessionHasErrors([
            'fotografias.0' => 'Cada fotografía no debe exceder los 2 MB.',
        ]);
    }

    /**
     * Requisito UI SweetAlert: La vista incluye la ventana emergente SweetAlert2 al recibir session('status').
     */
    public function test_vista_create_muestra_notificacion_sweetalert_cuando_existe_estatus(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['status' => 'Los datos y fechas del hallazgo fueron validados correctamente.'])
            ->get(route('reportes.create'));

        $response->assertOk();
        $response->assertSee('window.Swal.fire', false);
        $response->assertSee('Los datos y fechas del hallazgo fueron validados correctamente.');
    }

    /**
     * Requisito UI Fotografías: El formulario tiene enctype multipart y soporte para múltiples fotografías.
     */
    public function test_vista_create_contiene_formulario_multipart_y_campo_fotografias(): void
    {
        $response = $this->actingAs($this->user)->get(route('reportes.create'));

        $response->assertOk();
        $response->assertSee('enctype="multipart/form-data"', false);
        $response->assertSee('name="fotografias[]"', false);
        $response->assertSee('id="fotografia"', false);
    }
}
