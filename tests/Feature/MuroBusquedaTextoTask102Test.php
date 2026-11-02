<?php

namespace Tests\Feature;

use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MuroBusquedaTextoTask102Test extends TestCase
{
    use RefreshDatabase;

    private function crearPublicacionAprobada(array $atributos = []): Publicacion
    {
        $autor = User::factory()->create();

        return Publicacion::create(array_merge([
            'user_id' => $autor->id,
            'nombre' => 'Objeto de prueba',
            'titulo' => 'Objeto de prueba',
            'descripcion' => 'Descripción completa del objeto de prueba con detalles generales.',
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
     * Prueba 1: Búsqueda por nombre.
     */
    public function test_busqueda_por_nombre(): void
    {
        $pub1 = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0001',
            'nombre' => 'Termo Stanley',
            'descripcion' => 'Recipiente de acero inoxidable',
        ]);

        $pub2 = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0002',
            'nombre' => 'Mochila Nike',
            'descripcion' => 'Mochila deportiva escolar',
        ]);

        $response = $this->get('/muro?buscar=Termo');

        $response->assertOk();
        $response->assertSee('Termo Stanley');
        $response->assertDontSee('Mochila Nike');
    }

    /**
     * Prueba 2: Búsqueda por descripción.
     */
    public function test_busqueda_por_descripcion(): void
    {
        $pub1 = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0003',
            'nombre' => 'Botella deportiva',
            'descripcion' => 'Recipiente metálico con tapa hermética color gris',
        ]);

        $pub2 = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0004',
            'nombre' => 'Gorra de béisbol',
            'descripcion' => 'Gorra de tela azul con visera curva',
        ]);

        $response = $this->get('/muro?buscar=metálico');

        $response->assertOk();
        $response->assertSee('Botella deportiva');
        $response->assertDontSee('Gorra de béisbol');
    }

    /**
     * Prueba 3: Búsqueda ignorando mayúsculas y minúsculas.
     */
    public function test_busqueda_ignorando_mayusculas(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0005',
            'nombre' => 'Calculadora Casio',
            'descripcion' => 'Dispositivo electrónico en sala de cómputo',
        ]);

        // Consulta en mayúsculas sostenidas
        $resMayus = $this->get('/muro?buscar=CALCULADORA');
        $resMayus->assertOk();
        $resMayus->assertSee('Calculadora Casio');

        // Consulta en minúsculas
        $resMinus = $this->get('/muro?buscar=calculadora');
        $resMinus->assertOk();
        $resMinus->assertSee('Calculadora Casio');
    }

    /**
     * Prueba 4: Búsqueda ignorando acentos (diacríticos).
     */
    public function test_busqueda_ignorando_acentos(): void
    {
        $pub1 = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0006',
            'nombre' => 'Termo clásico',
            'descripcion' => 'Recipiente térmico para café',
        ]);

        // «TERMÓ» debe encontrar «Termo clásico»
        $res1 = $this->get('/muro?buscar=TERMÓ');
        $res1->assertOk();
        $res1->assertSee('Termo clásico');

        // «cafe» debe encontrar «café»
        $res2 = $this->get('/muro?buscar=cafe');
        $res2->assertOk();
        $res2->assertSee('Termo clásico');

        // «cálculo» debe encontrar publicación con «calculo»
        $pubCalculo = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0007',
            'nombre' => 'Libro de calculo vectorial',
            'descripcion' => 'Libro de texto pasta dura',
        ]);

        $res3 = $this->get('/muro?buscar=cálculo');
        $res3->assertOk();
        $res3->assertSee('Libro de calculo vectorial');
    }

    /**
     * Prueba 5: Coincidencias parciales dentro de las palabras.
     */
    public function test_coincidencias_parciales(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0008',
            'nombre' => 'Termo Stanley',
            'descripcion' => 'Mochila marca Samsonite',
        ]);

        // «ter» debe encontrar «Termo»
        $res1 = $this->get('/muro?buscar=ter');
        $res1->assertOk();
        $res1->assertSee('Termo Stanley');

        // «samson» debe encontrar «Samsonite»
        $res2 = $this->get('/muro?buscar=samson');
        $res2->assertOk();
        $res2->assertSee('Termo Stanley');
    }

    /**
     * Prueba 6: Dos términos distribuidos entre nombre y descripción.
     */
    public function test_dos_terminos_distribuidos_entre_nombre_y_descripcion(): void
    {
        // Nombre: «Termo», Descripción: «Recipiente de color azul»
        $pubMatch = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0009',
            'nombre' => 'Termo',
            'descripcion' => 'Recipiente de color azul',
        ]);

        // Otro reporte que solo tiene «Termo» pero no «azul»
        $pubNoMatch = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0010',
            'nombre' => 'Termo rojo',
            'descripcion' => 'Recipiente de aluminio brillante',
        ]);

        // Búsqueda «termo azul»
        $response = $this->get('/muro?buscar=termo+azul');
        $response->assertOk();
        $response->assertSee('Recipiente de color azul');
        $response->assertDontSee('Recipiente de aluminio brillante');
    }

    /**
     * Prueba 7: Tres o más términos en diferente orden.
     */
    public function test_tres_o_mas_terminos_en_diferente_orden(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0011',
            'nombre' => 'Mochila deportiva',
            'descripcion' => 'Marca Nike color rojo con cierres negros',
        ]);

        // Búsqueda en orden inverso o alterado: «rojo deportiva nike»
        $response = $this->get('/muro?buscar=rojo+deportiva+nike');
        $response->assertOk();
        $response->assertSee('Mochila deportiva');
    }

    /**
     * Prueba 8: Exclusión cuando falta alguno de los términos requeridos (condición AND).
     */
    public function test_exclusion_cuando_falta_alguno_de_los_terminos(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0012',
            'nombre' => 'Termo',
            'descripcion' => 'Recipiente de color azul',
        ]);

        // «termo verde»: contiene «termo», pero NO «verde»
        $response = $this->get('/muro?buscar=termo+verde');
        $response->assertOk();
        $response->assertDontSee('Recipiente de color azul');
    }

    /**
     * Prueba 9: Eliminación de espacios externos al inicio y final.
     */
    public function test_eliminacion_de_espacios_externos(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0013',
            'nombre' => 'Audífonos Sony',
            'descripcion' => 'Auriculares inalámbricos negros',
        ]);

        $response = $this->get('/muro?buscar=' . urlencode('   Audífonos   '));
        $response->assertOk();
        $response->assertSee('Audífonos Sony');
    }

    /**
     * Prueba 10: Normalización de espacios consecutivos entre palabras.
     */
    public function test_normalizacion_de_espacios_consecutivos(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0014',
            'nombre' => 'Termo',
            'descripcion' => 'Recipiente de color azul',
        ]);

        // «  termo     azul  » debe interpretarse exactamente como dos términos: «termo» y «azul»
        $response = $this->get('/muro?buscar=' . urlencode('  termo     azul  '));
        $response->assertOk();
        $response->assertSee('Recipiente de color azul');
    }

    /**
     * Prueba 11: Texto compuesto únicamente por espacios se interpreta como ausencia de filtro.
     */
    public function test_texto_compuesto_unicamente_por_espacios(): void
    {
        $pub1 = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0015',
            'nombre' => 'Mochila Negra',
            'descripcion' => 'Mochila de lona',
        ]);

        $pub2 = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0016',
            'nombre' => 'Calculadora Científica',
            'descripcion' => 'Calculadora gris',
        ]);

        $response = $this->get('/muro?buscar=' . urlencode('     '));
        $response->assertOk();
        // Muestra todas las publicaciones sin filtrar erróneamente
        $response->assertSee('Mochila Negra');
        $response->assertSee('Calculadora Científica');
    }

    /**
     * Prueba 12: Ausencia de sinónimos automáticos.
     */
    public function test_ausencia_de_sinonimos_automaticos(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0017',
            'nombre' => 'Termo',
            'descripcion' => 'Botella de agua hermética',
        ]);

        // Buscar «vaso» no debe interpretarse automáticamente como sinónimo de termo o botella
        $response = $this->get('/muro?buscar=vaso');
        $response->assertOk();
        $response->assertDontSee('Botella de agua hermética');
    }

    /**
     * Prueba 13: Ausencia de equivalencias automáticas de género.
     */
    public function test_ausencia_de_equivalencias_automaticas_de_genero(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0018',
            'nombre' => 'Gorra',
            'descripcion' => 'Gorra roja con visera curvada y correa negra ajustable',
        ]);

        // Buscar «negro» no debe encontrar un texto que contiene «negra» si la palabra exacta «negro» no está
        $response = $this->get('/muro?buscar=negro');
        $response->assertOk();
        $response->assertDontSee('visera curvada y correa negra ajustable');
    }

    /**
     * Prueba 14: Un mismo reporte aparece una sola vez en los resultados (sin duplicación).
     */
    public function test_mismo_reporte_aparece_una_sola_vez(): void
    {
        // La palabra «llaves» aparece tanto en el nombre como en la descripción
        $pub = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0019',
            'nombre' => 'Juego de llaves',
            'descripcion' => 'Juego de llaves con llavero de metal del campus',
        ]);

        $response = $this->get('/muro?buscar=llaves');
        $response->assertOk();

        // En la vista, el texto del folio del objeto aparece exactamente una vez en el catálogo
        $contenido = $response->getContent();
        $cuenta = substr_count($contenido, 'OBJ-20261102-0019');
        // Si no aparece el folio en la tarjeta, verificar que el nombre solo aparezca una vez en las tarjetas
        $this->assertEquals(1, substr_count($contenido, 'Juego de llaves con llavero de metal'));
    }

    /**
     * Prueba 15: Exclusión de publicaciones no autorizadas para el muro.
     */
    public function test_exclusion_de_publicaciones_no_autorizadas(): void
    {
        $autor = User::factory()->create();

        // 1. Reporte en corrección solicitada
        Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261102-0020',
            'nombre' => 'Laptop Dell en corrección',
            'descripcion' => 'Laptop con cargador en revisión',
            'estado' => Publicacion::ESTADO_CORRECCION_SOLICITADA,
            'disponible' => false,
        ]);

        // 2. Reporte pendiente de aprobación
        Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261102-0021',
            'nombre' => 'Laptop Lenovo pendiente',
            'descripcion' => 'Laptop negra pendiente de revisión',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'disponible' => false,
        ]);

        // 3. Reporte cancelado
        Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261102-0022',
            'nombre' => 'Laptop HP cancelada',
            'descripcion' => 'Laptop retirada del muro',
            'estado' => Publicacion::ESTADO_CANCELADO,
            'disponible' => false,
        ]);

        // 4. Reporte aprobado y disponible
        $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0023',
            'nombre' => 'Laptop Asus autorizada',
            'descripcion' => 'Laptop plateada disponible en muro',
        ]);

        $response = $this->get('/muro?buscar=Laptop');
        $response->assertOk();

        $response->assertSee('Laptop Asus autorizada');
        $response->assertDontSee('Laptop Dell en corrección');
        $response->assertDontSee('Laptop Lenovo pendiente');
        $response->assertDontSee('Laptop HP cancelada');
    }

    /**
     * Prueba 16: Conservación del orden cronológico definido por HU-08.
     */
    public function test_conservacion_del_orden_cronologico(): void
    {
        $pubAntigua = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0024',
            'nombre' => 'Mochila Puma Antigua',
            'descripcion' => 'Mochila gris publicada hace 2 días',
            'fecha_primera_aprobacion' => Carbon::now(Publicacion::TIMEZONE)->subDays(2),
        ]);

        $pubReciente = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0025',
            'nombre' => 'Mochila Puma Reciente',
            'descripcion' => 'Mochila gris publicada hoy',
            'fecha_primera_aprobacion' => Carbon::now(Publicacion::TIMEZONE),
        ]);

        $response = $this->get('/muro?buscar=Mochila+Puma');
        $response->assertOk();

        $contenido = $response->getContent();
        $posReciente = strpos($contenido, 'Mochila Puma Reciente');
        $posAntigua = strpos($contenido, 'Mochila Puma Antigua');

        $this->assertTrue($posReciente !== false);
        $this->assertTrue($posAntigua !== false);
        $this->assertTrue($posReciente < $posAntigua, 'La publicación más reciente debe aparecer antes que la antigua.');
    }

    /**
     * Prueba 17: Protección contra inyección SQL y tratamiento seguro de caracteres especiales de LIKE.
     */
    public function test_proteccion_contra_inyeccion_sql_y_caracteres_especiales(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0026',
            'nombre' => 'Batería 100% nueva',
            'descripcion' => 'Pila recargable con carga 100% completa',
        ]);

        // Caracteres especiales de SQL y comodines
        $consultas = [
            "' OR '1'='1",
            "'; DROP TABLE publicaciones; --",
            "100%",
            "test_item",
            "\\",
        ];

        foreach ($consultas as $consulta) {
            $response = $this->get('/muro?buscar=' . urlencode($consulta));
            // No debe causar excepción de base de datos (500)
            $response->assertOk();
        }

        // Búsqueda literal de «100%» debe encontrar la publicación que contiene «100%»
        $resLiteral = $this->get('/muro?buscar=' . urlencode('100%'));
        $resLiteral->assertOk();
        $resLiteral->assertSee('Batería 100% nueva');
    }

    /**
     * Prueba 18: Búsqueda realizada por visitante sin sesión.
     */
    public function test_busqueda_sin_sesion(): void
    {
        $pub = $this->crearPublicacionAprobada([
            'folio' => 'OBJ-20261102-0027',
            'nombre' => 'Paraguas Negro',
            'descripcion' => 'Paraguas plegable en entrada principal',
        ]);

        $response = $this->get('/muro?buscar=Paraguas');

        $response->assertOk();
        $this->assertGuest();
        $response->assertSee('Paraguas Negro');
        // No expone información privada del autor
        $response->assertDontSee($pub->user->email);
        $response->assertDontSee('password');
    }
}

