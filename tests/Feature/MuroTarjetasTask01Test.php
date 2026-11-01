<?php

namespace Tests\Feature;

use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MuroTarjetasTask01Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: El muro público es accesible para visitantes sin sesión y usuarios autenticados.
     */
    public function test_muro_es_accesible_para_visitantes_y_usuarios_autenticados(): void
    {
        $responseGuest = $this->get('/');
        $responseGuest->assertOk();

        $responseGuestMuro = $this->get(route('muro'));
        $responseGuestMuro->assertOk();

        $user = User::factory()->create();
        $responseAuth = $this->actingAs($user)->get('/');
        $responseAuth->assertOk();

        $responseAuthMuro = $this->actingAs($user)->get(route('muro'));
        $responseAuthMuro->assertOk();
    }

    /**
     * Test 2: La tarjeta muestra exclusivamente nombre, fotografía principal y descripción completa.
     */
    public function test_tarjeta_muestra_exclusivamente_nombre_foto_principal_y_descripcion_completa(): void
    {
        $autor = User::factory()->create([
            'name' => 'Profesor Roberto Gomez',
            'phone' => '9991234567',
        ]);

        $descripcionCompleta = 'Mochila color negro marca Samsonite con cierres metálicos plateados, un llavero de metal del TecNM en el cierre frontal superior, conteniendo cuadernos de apuntes de Cálculo Vectorial sin identificación personal visible en las tapas.';

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261101-0042',
            'nombre' => 'Mochila negra Samsonite',
            'titulo' => 'Mochila negra Samsonite',
            'descripcion' => $descripcionCompleta,
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Edificio H - Aulas de Posgrado',
            'lugar_hallazgo' => 'Edificio H - Salón H-204',
            'ubicacion_especifica' => 'Debajo del pupitre de la segunda fila',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_hallazgo' => Carbon::now(Publicacion::TIMEZONE)->subDays(2)->format('Y-m-d'),
        ]);

        $fotoPrincipal = Fotografia::create([
            'publicacion_id' => $publicacion->id,
            'ruta' => 'hallazgos/samsonite_frontal.jpg',
            'nombre_original' => 'samsonite_frontal.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 204800,
            'es_principal' => true,
        ]);

        $fotoSecundaria = Fotografia::create([
            'publicacion_id' => $publicacion->id,
            'ruta' => 'hallazgos/samsonite_trasera.jpg',
            'nombre_original' => 'samsonite_trasera.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 195000,
            'es_principal' => false,
        ]);

        $response = $this->get('/');
        $response->assertOk();

        // 1. Datos públicos permitidos
        $response->assertSee('Mochila negra Samsonite');
        $response->assertSee($descripcionCompleta);
        $response->assertSee($fotoPrincipal->url);
        $response->assertSee('Ver más detalles');
        $response->assertSee(route('publicaciones.show', $publicacion->id));

        // 2. Datos privados rigurosamente excluidos de la tarjeta pública
        $response->assertDontSee('Profesor Roberto Gomez');
        $response->assertDontSee('9991234567');
        $response->assertDontSee('OBJ-20261101-0042');
        $response->assertDontSee('Edificio H - Aulas de Posgrado');
        $response->assertDontSee('Edificio H - Salón H-204');
        $response->assertDontSee('Debajo del pupitre de la segunda fila');
        $response->assertDontSee(Publicacion::RESGUARDO_AUTOR);
        $response->assertDontSee($fotoSecundaria->url);
    }

    /**
     * Test 3: Si no hay fotografía, la tarjeta muestra el fallback accesible con «Imagen no disponible».
     */
    public function test_tarjeta_muestra_alternativa_imagen_no_disponible_si_no_tiene_foto(): void
    {
        $autor = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Calculadora Casio fx-991',
            'titulo' => 'Calculadora Casio fx-991',
            'descripcion' => 'Calculadora científica gris con tapa protectora.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
        ]);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('Calculadora Casio fx-991');
        $response->assertSee('Calculadora científica gris con tapa protectora.');
        $response->assertSee('Imagen no disponible');
        $response->assertSee('Ver más detalles');
    }

    /**
     * Test 4: La tarjeta no trunca descripciones largas con puntos suspensivos artificiales.
     */
    public function test_tarjeta_no_trunca_ni_resume_la_descripcion(): void
    {
        $autor = User::factory()->create();

        $textoLargo = str_repeat('Detalle de características completas del objeto encontrado en campus. ', 8);

        Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Carpeta con apuntes',
            'titulo' => 'Carpeta con apuntes',
            'descripcion' => $textoLargo,
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
        ]);

        $response = $this->get(route('muro'));
        $response->assertOk();
        $response->assertSee($textoLargo);
    }

    /**
     * Test 5: La presentación conserva los colores institucionales y el diseño responsivo.
     */
    public function test_presentacion_aplica_colores_institucionales_y_rejilla_responsiva(): void
    {
        $autor = User::factory()->create();

        Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Audífonos Bluetooth',
            'titulo' => 'Audífonos Bluetooth',
            'descripcion' => 'Estuche de carga blanco con auriculares inalámbricos.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
        ]);

        $response = $this->get('/');
        $response->assertOk();

        // Color institucional rojo vino en botón
        $response->assertSee('bg-tecnm-wine');
        $response->assertSee('hover:bg-tecnm-wine-hover');

        // Color institucional dorado en acentos de tarjeta
        $response->assertSee('border-tecnm-gold');

        // Rejilla responsiva para 360px (cols-1), 768px (md:cols-2) y 1280px (lg:cols-3)
        $response->assertSee('grid-cols-1');
        $response->assertSee('md:grid-cols-2');
        $response->assertSee('lg:grid-cols-3');

        // Preservación de proporciones fotográficas
        $response->assertSee('aspect-[4/3]');
        $response->assertSee('object-cover');
    }

    /**
     * Test 6: Muro conserva estado vacío cuando no existen publicaciones disponibles.
     */
    public function test_muro_muestra_estado_vacio_cuando_no_hay_publicaciones_aprobadas(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('No hay publicaciones disponibles');
    }

    /**
     * Test 7: Fotografía principal de publicación aprobada es transmitida con éxito a visitantes y usuarios autenticados.
     */
    public function test_fotografia_de_publicacion_aprobada_es_accesible_para_visitantes_y_usuarios_en_el_muro(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put('hallazgos/foto_aprobada.jpg', 'fake-image-binary');

        $autor = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Calculadora Casio fx-991',
            'titulo' => 'Calculadora Casio fx-991',
            'descripcion' => 'Calculadora científica en buen estado.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => Carbon::now(Publicacion::TIMEZONE),
            'fecha_vencimiento' => Carbon::now(Publicacion::TIMEZONE)->addDays(14),
        ]);

        $foto = Fotografia::create([
            'publicacion_id' => $publicacion->id,
            'ruta' => 'hallazgos/foto_aprobada.jpg',
            'nombre_original' => 'foto_aprobada.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 1024,
            'es_principal' => true,
        ]);

        // 1. Visitante sin sesión puede cargar la fotografía pública de la tarjeta
        $responseGuest = $this->get(route('fotografias.show', $foto->id));
        $this->assertEquals(200, $responseGuest->getStatusCode());

        // 2. Usuario autenticado puede cargar la fotografía pública de la tarjeta
        $lector = User::factory()->create();
        $responseUser = $this->actingAs($lector)->get(route('fotografias.show', $foto->id));
        $this->assertEquals(200, $responseUser->getStatusCode());
    }

    /**
     * Test 8: Orden cronológico institucional desde el inicio de publicación más reciente al más antiguo,
     * con desempate por folio descendente cuando coinciden fecha y hora.
     */
    public function test_orden_cronologico_por_inicio_de_publicacion_y_desempate_por_folio(): void
    {
        $user = User::factory()->create();
        $baseTime = Carbon::create(2026, 11, 1, 10, 0, 0, Publicacion::TIMEZONE);

        // Publicación A: Publicada hace 2 horas (folio menor)
        $pubA = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Objeto Publicado Empate Folio 10',
            'folio' => 'OBJ-20261101-0010',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => $baseTime->copy()->subHours(2),
            'fecha_vencimiento' => $baseTime->copy()->addDays(14),
            'fecha_hallazgo' => $baseTime->copy()->subDays(10)->format('Y-m-d'), // Hallazgo muy antiguo
        ]);

        // Publicación B: Misma fecha y hora que A, pero folio mayor
        $pubB = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Objeto Publicado Empate Folio 20',
            'folio' => 'OBJ-20261101-0020',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => $baseTime->copy()->subHours(2),
            'fecha_vencimiento' => $baseTime->copy()->addDays(14),
            'fecha_hallazgo' => $baseTime->copy()->subDays(1)->format('Y-m-d'), // Hallazgo reciente
        ]);

        // Publicación C: Publicada hace 1 hora (la más reciente en publicación)
        $pubC = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Objeto Mas Reciente Publicado',
            'folio' => 'OBJ-20261101-0005',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => $baseTime->copy()->subHours(1),
            'fecha_vencimiento' => $baseTime->copy()->addDays(14),
            'fecha_hallazgo' => $baseTime->copy()->subDays(5)->format('Y-m-d'),
        ]);

        $response = $this->get('/');
        $response->assertOk();

        $publicaciones = $response->viewData('publicaciones');
        $this->assertCount(3, $publicaciones);

        // El orden debe ser:
        // 1. PubC (publicada hace 1 hora, más reciente en inicio de publicación)
        // 2. PubB (publicada hace 2 horas, empatada con A pero con folio 0020 > 0010)
        // 3. PubA (publicada hace 2 horas, folio 0010)
        $this->assertEquals($pubC->id, $publicaciones->values()->get(0)->id);
        $this->assertEquals($pubB->id, $publicaciones->values()->get(1)->id);
        $this->assertEquals($pubA->id, $publicaciones->values()->get(2)->id);
    }

    /**
     * Test 9: Exclusión estricta de publicaciones no aprobadas, canceladas, vencidas,
     * en devolución, en incidencia y objetos en biblioteca sin autorización.
     */
    public function test_exclusiones_estrictas_de_disponibilidad_en_muro(): void
    {
        $user = User::factory()->create();
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        // 1. Pendiente de aprobación -> Excluido
        $pubPendiente = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Reporte Pendiente Excluido',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'disponible' => false,
        ]);

        // 2. Corrección solicitada -> Excluido
        $pubCorreccion = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Reporte Correccion Excluido',
            'estado' => Publicacion::ESTADO_CORRECCION_SOLICITADA,
            'disponible' => false,
        ]);

        // 3. Cancelado -> Excluido
        $pubCancelado = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Reporte Cancelado Excluido',
            'estado' => Publicacion::ESTADO_CANCELADO,
            'disponible' => false,
        ]);

        // 4. Vencido (fecha_vencimiento en el pasado) -> Excluido
        $pubVencido = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Reporte Vencido Excluido',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_vencimiento' => $ahora->copy()->subMinutes(10),
        ]);

        // 5. Devolución en proceso -> Excluido
        $pubDevolucion = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Reporte Devolucion Excluido',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'devolucion_en_proceso' => true,
            'fecha_vencimiento' => $ahora->copy()->addDays(5),
        ]);

        // 6. Con incidencia abierta -> Excluido
        $pubIncidencia = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Reporte Incidencia Excluido',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'incidencia_abierta' => true,
            'fecha_vencimiento' => $ahora->copy()->addDays(5),
        ]);

        // 7. Objeto en biblioteca sin publicación autorizada (disponible = false) -> Excluido
        $pubBibSinAutorizar = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Objeto Biblioteca No Autorizado',
            'lugar_hallazgo' => 'Biblioteca Campus Poniente',
            'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO,
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'disponible' => false,
        ]);

        // 8. Objeto en biblioteca con publicación expresamente aprobada -> INCLUIDO
        $pubBibAprobado = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Objeto Biblioteca Aprobado Permitido',
            'lugar_hallazgo' => 'Biblioteca Campus Poniente',
            'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO,
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => $ahora->copy()->subHour(),
            'fecha_vencimiento' => $ahora->copy()->addDays(14),
        ]);

        // 9. Objeto bajo resguardo del autor con publicación aprobada -> INCLUIDO
        $pubAutorAprobado = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Objeto Autor Aprobado Permitido',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => $ahora->copy()->subMinutes(30),
            'fecha_vencimiento' => $ahora->copy()->addDays(14),
        ]);

        $response = $this->get('/');
        $response->assertOk();

        // Verificar inclusiones
        $response->assertSee('Objeto Biblioteca Aprobado Permitido');
        $response->assertSee('Objeto Autor Aprobado Permitido');

        // Verificar exclusiones
        $response->assertDontSee('Reporte Pendiente Excluido');
        $response->assertDontSee('Reporte Correccion Excluido');
        $response->assertDontSee('Reporte Cancelado Excluido');
        $response->assertDontSee('Reporte Vencido Excluido');
        $response->assertDontSee('Reporte Devolucion Excluido');
        $response->assertDontSee('Reporte Incidencia Excluido');
        $response->assertDontSee('Objeto Biblioteca No Autorizado');
    }

    /**
     * Test 10: Cada página muestra un máximo de 12 tarjetas.
     */
    public function test_paginacion_maximo_doce_tarjetas_por_pagina(): void
    {
        $user = User::factory()->create();
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        // Crear 15 publicaciones aprobadas y disponibles
        for ($i = 1; $i <= 15; $i++) {
            Publicacion::factory()->create([
                'user_id' => $user->id,
                'nombre' => "Objeto Paginado {$i}",
                'folio' => sprintf('OBJ-20261101-%04d', $i),
                'estado' => Publicacion::ESTADO_APROBADA,
                'disponible' => true,
                'fecha_primera_aprobacion' => $ahora->copy()->subMinutes(100 - $i),
                'fecha_vencimiento' => $ahora->copy()->addDays(14),
            ]);
        }

        // Página 1: debe contener un máximo de 12 publicaciones
        $responsePage1 = $this->get('/');
        $responsePage1->assertOk();
        $pubsPage1 = $responsePage1->viewData('publicaciones');
        $this->assertCount(12, $pubsPage1);

        // Página 2: debe contener las 3 publicaciones restantes
        $responsePage2 = $this->get('/?page=2');
        $responsePage2->assertOk();
        $pubsPage2 = $responsePage2->viewData('publicaciones');
        $this->assertCount(3, $pubsPage2);
    }

    /**
     * Test 11: Controles «Anterior» y «Siguiente» se ofrecen solo cuando existen páginas en esas direcciones.
     */
    public function test_controles_anterior_y_siguiente_condicionales_segun_direccion(): void
    {
        $user = User::factory()->create();
        $ahora = Carbon::now(Publicacion::TIMEZONE);

        // Caso A: Menos de 12 publicaciones -> NO se ofrecen controles
        for ($i = 1; $i <= 5; $i++) {
            Publicacion::factory()->create([
                'user_id' => $user->id,
                'nombre' => "Objeto Caso A {$i}",
                'estado' => Publicacion::ESTADO_APROBADA,
                'disponible' => true,
                'fecha_primera_aprobacion' => $ahora->copy()->subMinutes($i),
                'fecha_vencimiento' => $ahora->copy()->addDays(14),
            ]);
        }

        $resUnicaPagina = $this->get('/');
        $resUnicaPagina->assertOk();
        $resUnicaPagina->assertDontSee('Anterior');
        $resUnicaPagina->assertDontSee('Siguiente');

        // Caso B: Agregar 10 más para totalizar 15 publicaciones (2 páginas)
        for ($i = 6; $i <= 15; $i++) {
            Publicacion::factory()->create([
                'user_id' => $user->id,
                'nombre' => "Objeto Caso B {$i}",
                'estado' => Publicacion::ESTADO_APROBADA,
                'disponible' => true,
                'fecha_primera_aprobacion' => $ahora->copy()->subMinutes($i),
                'fecha_vencimiento' => $ahora->copy()->addDays(14),
            ]);
        }

        // En página 1: Solo existe página hacia adelante -> Solo control «Siguiente»
        $resPage1 = $this->get('/');
        $resPage1->assertOk();
        $resPage1->assertSee('Siguiente');
        $resPage1->assertDontSee('Anterior');

        // En página 2 (última): Solo existe página previa -> Solo control «Anterior»
        $resPage2 = $this->get('/?page=2');
        $resPage2->assertOk();
        $resPage2->assertSee('Anterior');
        $resPage2->assertDontSee('Siguiente');
    }

    /**
     * Test 12: En un listado sin cambios, avanzar y retroceder mantiene un orden estable
     * sin duplicar publicaciones ni omitir registros entre páginas.
     */
    public function test_paginacion_orden_estable_sin_duplicidad_ni_omision(): void
    {
        $user = User::factory()->create();
        $ahora = Carbon::now(Publicacion::TIMEZONE);
        $totalCreadas = 20;

        for ($i = 1; $i <= $totalCreadas; $i++) {
            Publicacion::factory()->create([
                'user_id' => $user->id,
                'nombre' => "Item Estable {$i}",
                'folio' => sprintf('OBJ-20261101-%04d', $i),
                'estado' => Publicacion::ESTADO_APROBADA,
                'disponible' => true,
                'fecha_primera_aprobacion' => $ahora->copy()->subMinutes($totalCreadas - $i),
                'fecha_vencimiento' => $ahora->copy()->addDays(14),
            ]);
        }

        $resPage1 = $this->get('/');
        $pubsPage1 = $resPage1->viewData('publicaciones');
        $idsPage1 = $pubsPage1->pluck('id')->toArray();

        $resPage2 = $this->get('/?page=2');
        $pubsPage2 = $resPage2->viewData('publicaciones');
        $idsPage2 = $pubsPage2->pluck('id')->toArray();

        // 1. Las dos páginas no comparten ningún ID (sin duplicados)
        $interseccion = array_intersect($idsPage1, $idsPage2);
        $this->assertEmpty($interseccion, 'No debe haber publicaciones duplicadas entre páginas.');

        // 2. La unión de ambas páginas contiene exactamente todas las 20 publicaciones (sin omisiones)
        $todosLosIds = array_merge($idsPage1, $idsPage2);
        $this->assertCount($totalCreadas, $todosLosIds);
        $this->assertEquals($totalCreadas, count(array_unique($todosLosIds)));

        // 3. Al volver a consultar página 1 (retroceder), los registros coinciden exactamente
        $resPage1Reload = $this->get('/?page=1');
        $idsPage1Reload = $resPage1Reload->viewData('publicaciones')->pluck('id')->toArray();
        $this->assertSame($idsPage1, $idsPage1Reload);
    }
}



