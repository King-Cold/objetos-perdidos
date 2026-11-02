<?php

namespace Tests\Feature;

use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MuroFiltroFechaTask103Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function crearPublicacionAprobada(array $atributos = []): Publicacion
    {
        $autor = User::factory()->create();

        return Publicacion::create(array_merge([
            'user_id' => $autor->id,
            'nombre' => 'Objeto de prueba',
            'titulo' => 'Objeto de prueba',
            'descripcion' => 'Descripción completa del objeto de prueba para validación de filtros.',
            'fecha_hallazgo' => Carbon::now(Publicacion::TIMEZONE)->toDateString(),
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'version' => 1,
            'fecha_primera_aprobacion' => Carbon::now(Publicacion::TIMEZONE),
            'fecha_aprobacion' => Carbon::now(Publicacion::TIMEZONE),
            'fecha_vencimiento' => Carbon::now(Publicacion::TIMEZONE)->addDays(14),
            'devolucion_en_proceso' => false,
            'incidencia_abierta' => false,
        ], $atributos));
    }

    /**
     * Prueba 1: Filtrado por fecha exacta.
     */
    public function test_filtrado_por_fecha_exacta(): void
    {
        $pub1 = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0001',
            'nombre' => 'Calculadora Científica Casio',
            'fecha_hallazgo' => '2026-10-25',
        ]);

        $pub2 = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0002',
            'nombre' => 'Paraguas Plegable Negro',
            'fecha_hallazgo' => '2026-10-28',
        ]);

        $response = $this->get('/muro?fecha_hallazgo=2026-10-25');

        $response->assertOk();
        $response->assertSee('Calculadora Científica Casio');
        $response->assertDontSee('Paraguas Plegable Negro');
    }

    /**
     * Prueba 2: Exclusión de fechas diferentes.
     */
    public function test_exclusion_de_fechas_diferentes(): void
    {
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0003',
            'nombre' => 'Cuaderno Profesional Cuadriculado',
            'fecha_hallazgo' => '2026-10-20',
        ]);

        $response = $this->get('/muro?fecha_hallazgo=2026-10-22');

        $response->assertOk();
        $response->assertDontSee('Cuaderno Profesional Cuadriculado');
    }

    /**
     * Prueba 3: Uso exclusivo de la fecha del hallazgo.
     * No debe coincidir si la fecha enviada corresponde a fecha_captura, fecha_aprobacion, created_at, etc.
     */
    public function test_uso_exclusivo_de_la_fecha_del_hallazgo(): void
    {
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0004',
            'nombre' => 'Memoria USB Kingston 64GB',
            'fecha_hallazgo' => '2026-10-15',
            'fecha_captura' => '2026-10-25 10:00:00',
            'fecha_aprobacion' => '2026-10-26 12:00:00',
            'fecha_primera_aprobacion' => '2026-10-26 12:00:00',
            'fecha_envio_revision' => '2026-10-25 11:00:00',
        ]);

        // Si buscamos por 2026-10-25 (fecha_captura o envio), NO debe aparecer
        $responseCaptura = $this->get('/muro?fecha_hallazgo=2026-10-25');
        $responseCaptura->assertOk();
        $responseCaptura->assertDontSee('Memoria USB Kingston 64GB');

        // Si buscamos por 2026-10-26 (fecha_aprobacion), NO debe aparecer
        $responseAprobacion = $this->get('/muro?fecha_hallazgo=2026-10-26');
        $responseAprobacion->assertOk();
        $responseAprobacion->assertDontSee('Memoria USB Kingston 64GB');

        // Si buscamos por 2026-10-15 (fecha_hallazgo), SÍ debe aparecer
        $responseHallazgo = $this->get('/muro?fecha_hallazgo=2026-10-15');
        $responseHallazgo->assertOk();
        $responseHallazgo->assertSee('Memoria USB Kingston 64GB');
    }

    /**
     * Prueba 4: Rechazo de fechas inexistentes (como 31 de febrero).
     */
    public function test_rechazo_de_fechas_inexistentes(): void
    {
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0005',
            'nombre' => 'Audífonos Bluetooth Sony',
            'fecha_hallazgo' => '2026-10-20',
        ]);

        $response = $this->get('/muro?fecha_hallazgo=2026-02-31');

        $response->assertOk();
        $response->assertSee('Selecciona una fecha válida que no sea posterior a hoy');
    }

    /**
     * Prueba 5: Rechazo de fechas futuras.
     */
    public function test_rechazo_de_fechas_futuras(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 2, 10, 0, 0, Publicacion::TIMEZONE));

        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0006',
            'nombre' => 'Gorra deportiva gris',
            'fecha_hallazgo' => '2026-11-02',
        ]);

        // Intentar buscar con mañana (2026-11-03)
        $response = $this->get('/muro?fecha_hallazgo=2026-11-03');

        $response->assertOk();
        $response->assertSee('Selecciona una fecha válida que no sea posterior a hoy');
    }

    /**
     * Prueba 6: Aceptación de la fecha actual institucional.
     */
    public function test_aceptacion_de_la_fecha_actual_institucional(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 2, 14, 30, 0, Publicacion::TIMEZONE));

        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0007',
            'nombre' => 'Reloj Digital Casio',
            'fecha_hallazgo' => '2026-11-02',
        ]);

        $response = $this->get('/muro?fecha_hallazgo=2026-11-02');

        $response->assertOk();
        $response->assertDontSee('Selecciona una fecha válida que no sea posterior a hoy');
        $response->assertSee('Reloj Digital Casio');
    }

    /**
     * Prueba 7: Aceptación de fechas antiguas (mayores a 7 días).
     * El filtro del muro permite consultar objetos históricos sin la restricción de 7 días de captura.
     */
    public function test_aceptacion_de_fechas_antiguas(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 2, 12, 0, 0, Publicacion::TIMEZONE));

        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0008',
            'nombre' => 'Libro de Álgebra Lineal',
            'fecha_hallazgo' => '2026-09-15', // Más de 45 días atrás
        ]);

        $response = $this->get('/muro?fecha_hallazgo=2026-09-15');

        $response->assertOk();
        $response->assertDontSee('Selecciona una fecha válida que no sea posterior a hoy');
        $response->assertSee('Libro de Álgebra Lineal');
    }

    /**
     * Prueba 8: Búsqueda únicamente por texto.
     */
    public function test_busqueda_unicamente_por_texto(): void
    {
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0009',
            'nombre' => 'Chamarra Universitaria TecNM',
            'descripcion' => 'Color guinda con mangas blancas',
            'fecha_hallazgo' => '2026-10-18',
        ]);

        $response = $this->get('/muro?buscar=Chamarra');

        $response->assertOk();
        $response->assertSee('Chamarra Universitaria TecNM');
    }

    /**
     * Prueba 9: Búsqueda únicamente por fecha.
     */
    public function test_busqueda_unicamente_por_fecha(): void
    {
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0010',
            'nombre' => 'Termo Metálico Plateado',
            'fecha_hallazgo' => '2026-10-21',
        ]);

        $response = $this->get('/muro?fecha_hallazgo=2026-10-21');

        $response->assertOk();
        $response->assertSee('Termo Metálico Plateado');
    }

    /**
     * Prueba 10: Búsqueda combinada de texto y fecha.
     * Ejemplo obligatorio del requerimiento:
     * Reporte A: «Termo», «Color azul», Fecha: 20 Oct.
     * Reporte B: «Termo», «Color azul», Fecha: 21 Oct.
     * Reporte C: «Mochila», «Color azul», Fecha: 20 Oct.
     * Búsqueda: «termo azul» + 20 Oct -> Debe aparecer ÚNICAMENTE el Reporte A.
     */
    public function test_busqueda_combinada_de_texto_y_fecha(): void
    {
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0011',
            'nombre' => 'Termo Yeti Aislado',
            'descripcion' => 'Recipiente de color azul térmico',
            'fecha_hallazgo' => '2026-10-20',
        ]);

        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0012',
            'nombre' => 'Termo Stanley Clásico',
            'descripcion' => 'Recipiente de color azul térmico',
            'fecha_hallazgo' => '2026-10-21',
        ]);

        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0013',
            'nombre' => 'Mochila Escolar Chenson',
            'descripcion' => 'Mochila de color azul térmico',
            'fecha_hallazgo' => '2026-10-20',
        ]);

        $response = $this->get('/muro?buscar=termo+azul&fecha_hallazgo=2026-10-20');

        $response->assertOk();
        $response->assertSee('Termo Yeti Aislado'); // Reporte A
        $response->assertDontSee('Termo Stanley Clásico'); // Reporte B
        $response->assertDontSee('Mochila Escolar Chenson'); // Reporte C
    }

    /**
     * Prueba 11: Exclusión de resultados que cumplen solo una condición.
     */
    public function test_exclusion_de_resultados_que_cumplen_solo_una_condicion(): void
    {
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0014',
            'nombre' => 'Lentes de sol oscuros',
            'fecha_hallazgo' => '2026-10-10',
        ]);

        // Coincide texto pero no fecha
        $responseTexto = $this->get('/muro?buscar=Lentes&fecha_hallazgo=2026-10-11');
        $responseTexto->assertOk();
        $responseTexto->assertDontSee('Lentes de sol oscuros');

        // Coincide fecha pero no texto
        $responseFecha = $this->get('/muro?buscar=Sombrilla&fecha_hallazgo=2026-10-10');
        $responseFecha->assertOk();
        $responseFecha->assertDontSee('Lentes de sol oscuros');
    }

    /**
     * Prueba 12: Ambos filtros vacíos (retorna todo el muro disponible).
     */
    public function test_ambos_filtros_vacios(): void
    {
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0015',
            'nombre' => 'Libreta Universitaria',
        ]);

        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0016',
            'nombre' => 'Estuche de Geometría',
        ]);

        $response = $this->get('/muro?buscar=&fecha_hallazgo=');

        $response->assertOk();
        $response->assertSee('Libreta Universitaria');
        $response->assertSee('Estuche de Geometría');
    }

    /**
     * Prueba 13: Texto compuesto únicamente por espacios.
     */
    public function test_texto_compuesto_unicamente_por_espacios(): void
    {
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0017',
            'nombre' => 'Llaves con Llavero Acrílico',
            'fecha_hallazgo' => '2026-10-22',
        ]);

        // Espacios combinados con fecha válida
        $responseConFecha = $this->get('/muro?buscar=%20%20%20&fecha_hallazgo=2026-10-22');
        $responseConFecha->assertOk();
        $responseConFecha->assertSee('Llaves con Llavero Acrílico');

        // Solo espacios sin fecha (ausencia total de filtros)
        $responseSoloEspacios = $this->get('/muro?buscar=%20%20%20');
        $responseSoloEspacios->assertOk();
        $responseSoloEspacios->assertSee('Llaves con Llavero Acrílico');
    }

    /**
     * Prueba 14: Validación en servidor de parámetros manipulados.
     */
    public function test_validacion_en_servidor_de_parametros_manipulados(): void
    {
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0018',
            'nombre' => 'Credencial de Elector INE',
        ]);

        $responseInvalido = $this->get('/muro?fecha_hallazgo=fecha-invalida');
        $responseInvalido->assertOk();
        $responseInvalido->assertSee('Selecciona una fecha válida que no sea posterior a hoy');

        $responseFormato = $this->get('/muro?fecha_hallazgo=2026/10/25');
        $responseFormato->assertOk();
        $responseFormato->assertSee('Selecciona una fecha válida que no sea posterior a hoy');

        $responseMesInexistente = $this->get('/muro?fecha_hallazgo=2026-13-01');
        $responseMesInexistente->assertOk();
        $responseMesInexistente->assertSee('Selecciona una fecha válida que no sea posterior a hoy');

        // Conserva el texto ingresado cuando la fecha es inválida
        $responseTextoConservado = $this->get('/muro?buscar=calculadora&fecha_hallazgo=fecha-invalida');
        $responseTextoConservado->assertOk();
        $responseTextoConservado->assertSee('value="calculadora"', false);
        $responseTextoConservado->assertSee('Selecciona una fecha válida que no sea posterior a hoy');
    }

    /**
     * Prueba 15: Comportamiento correcto en zona horaria America/Merida.
     */
    public function test_comportamiento_correcto_en_america_merida(): void
    {
        // 23:30 horas en Mérida el 2 de Noviembre de 2026 (en UTC ya sería 3 de Noviembre 05:30)
        Carbon::setTestNow(Carbon::create(2026, 11, 2, 23, 30, 0, Publicacion::TIMEZONE));

        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0019',
            'nombre' => 'Gafete Institucional',
            'fecha_hallazgo' => '2026-11-02',
        ]);

        // Consultar con 2026-11-02 debe ser aceptado como "hoy"
        $responseHoy = $this->get('/muro?fecha_hallazgo=2026-11-02');
        $responseHoy->assertOk();
        $responseHoy->assertDontSee('Selecciona una fecha válida que no sea posterior a hoy');
        $responseHoy->assertSee('Gafete Institucional');

        // Consultar con 2026-11-03 debe ser rechazado como fecha futura
        $responseManana = $this->get('/muro?fecha_hallazgo=2026-11-03');
        $responseManana->assertOk();
        $responseManana->assertSee('Selecciona una fecha válida que no sea posterior a hoy');
    }

    /**
     * Prueba 16: Ausencia de modificaciones a los reportes.
     */
    public function test_ausencia_de_modificaciones_a_los_reportes(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0020',
            'nombre' => 'Carpeta Ejecutiva',
            'fecha_hallazgo' => '2026-10-15',
        ]);

        $updatedAtOriginal = $pub->updated_at;

        $this->get('/muro?fecha_hallazgo=2026-10-15');

        $pub->refresh();
        $this->assertEquals($updatedAtOriginal->toDateTimeString(), $pub->updated_at->toDateTimeString());
        $this->assertEquals(Publicacion::ESTADO_APROBADA, $pub->estado);
    }

    /**
     * Prueba 17: Exclusión de publicaciones no autorizadas.
     */
    public function test_exclusion_de_publicaciones_no_autorizadas(): void
    {
        // Publicación con la misma fecha pero estado Pendiente de aprobación
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0021',
            'nombre' => 'Anillo de Graduación',
            'fecha_hallazgo' => '2026-10-25',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'disponible' => false,
        ]);

        // Publicación cancelada
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0022',
            'nombre' => 'Collar de Plata',
            'fecha_hallazgo' => '2026-10-25',
            'estado' => Publicacion::ESTADO_CANCELADO,
            'disponible' => false,
        ]);

        $response = $this->get('/muro?fecha_hallazgo=2026-10-25');
        $response->assertOk();
        $response->assertDontSee('Anillo de Graduación');
        $response->assertDontSee('Collar de Plata');
    }
}
