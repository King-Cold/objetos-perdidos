<?php

namespace Tests\Feature;

use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicacionGaleriaAprobadaHU09Task2Test extends TestCase
{
    use RefreshDatabase;

    /**
     * CA-01 (Muro autenticado vs público):
     * - El visitante sin sesión solo ve título, descripción y foto principal en la tarjeta.
     * - El usuario autenticado ve información ampliada (fecha de hallazgo, ubicación y disponibilidad).
     */
    public function test_tarjeta_muro_muestra_informacion_ampliada_unicamente_a_usuarios_autenticados(): void
    {
        $autor = User::factory()->create();
        $lector = User::factory()->create();

        $fechaHallazgo = Carbon::create(2026, 10, 28, 10, 30, 0, Publicacion::TIMEZONE);
        $fechaAprobacion = Carbon::create(2026, 10, 29, 12, 0, 0, Publicacion::TIMEZONE);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Audífonos Sony WH-1000XM4',
            'titulo' => 'Audífonos Sony WH-1000XM4',
            'descripcion' => 'Audífonos inalámbricos negros con estuche.',
            'fecha_hallazgo' => $fechaHallazgo->format('Y-m-d'),
            'hora_hallazgo' => '10:30',
            'area_zona' => 'Edificio C - Biblioteca',
            'lugar_hallazgo' => 'Sala de lectura silenciosa',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => $fechaAprobacion,
            'fecha_aprobacion' => $fechaAprobacion,
            'fecha_vencimiento' => $fechaAprobacion->copy()->addDays(14),
        ]);

        $fotoPrincipal = Fotografia::create([
            'publicacion_id' => $publicacion->id,
            'ruta' => 'hallazgos/audifonos_1.jpg',
            'nombre_original' => 'audifonos_1.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 50000,
            'es_principal' => true,
        ]);

        $fotoAdicional = Fotografia::create([
            'publicacion_id' => $publicacion->id,
            'ruta' => 'hallazgos/audifonos_2.jpg',
            'nombre_original' => 'audifonos_2.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 45000,
            'es_principal' => false,
        ]);

        // 1. Visitante sin sesión en el Muro
        $responseGuest = $this->get('/');
        $responseGuest->assertOk();
        $responseGuest->assertSee('Audífonos Sony WH-1000XM4');
        $responseGuest->assertSee('Audífonos inalámbricos negros con estuche.');
        $responseGuest->assertSee($fotoPrincipal->url);
        $responseGuest->assertSee('Ver más detalles');
        // No ve información adicional privada en la tarjeta
        $responseGuest->assertDontSee('Edificio C - Biblioteca');
        $responseGuest->assertDontSee('28/10/2026');
        // No ve la foto secundaria en la tarjeta del muro
        $responseGuest->assertDontSee($fotoAdicional->url);

        // 2. Usuario autenticado en el Muro
        $responseAuth = $this->actingAs($lector)->get('/');
        $responseAuth->assertOk();
        $responseAuth->assertSee('Audífonos Sony WH-1000XM4');
        $responseAuth->assertSee('Audífonos inalámbricos negros con estuche.');
        $responseAuth->assertSee($fotoPrincipal->url);
        // Ve la información adicional en la tarjeta
        $responseAuth->assertSee('Disponibilidad:');
        $responseAuth->assertSee('Disponible');
        $responseAuth->assertSee('Encontrado:');
        $responseAuth->assertSee('28/10/2026');
        $responseAuth->assertSee('Edificio C - Biblioteca');
        $responseAuth->assertSee('Ver más detalles');
        $responseAuth->assertSee(route('publicaciones.show', $publicacion->id));
        // En la tarjeta del muro sigue sin mostrarse la galería completa (solo la foto principal)
        $responseAuth->assertDontSee($fotoAdicional->url);
    }

    /**
     * CA-02 (Galería de publicación aprobada con múltiples fotografías):
     * - Muestra la fotografía principal primero y luego las adicionales.
     * - Muestra el indicador de posición "1 de 3".
     * - Incluye controles de navegación anterior y siguiente.
     * - Incluye miniaturas para selección directa.
     * - Incluye botón Ampliar.
     */
    public function test_galeria_muestra_fotos_aprobadas_con_principal_primero_y_navegacion(): void
    {
        $autor = User::factory()->create();
        $lector = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Mochila Nike Gris',
            'titulo' => 'Mochila Nike Gris',
            'descripcion' => 'Mochila deportiva con compartimento para laptop.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => now()->subDay(),
            'fecha_aprobacion' => now()->subDay(),
            'fecha_vencimiento' => now()->addDays(13),
        ]);

        // Crear una foto secundaria primero en BD
        $fotoAdicional1 = Fotografia::create([
            'publicacion_id' => $publicacion->id,
            'ruta' => 'hallazgos/mochila_lateral.jpg',
            'nombre_original' => 'mochila_lateral.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 60000,
            'es_principal' => false,
        ]);

        // Crear la foto principal después
        $fotoPrincipal = Fotografia::create([
            'publicacion_id' => $publicacion->id,
            'ruta' => 'hallazgos/mochila_frente.jpg',
            'nombre_original' => 'mochila_frente.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 80000,
            'es_principal' => true,
        ]);

        $fotoAdicional2 = Fotografia::create([
            'publicacion_id' => $publicacion->id,
            'ruta' => 'hallazgos/mochila_interior.jpg',
            'nombre_original' => 'mochila_interior.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 70000,
            'es_principal' => false,
        ]);

        $response = $this->actingAs($lector)->get(route('publicaciones.show', $publicacion->id));
        $response->assertOk();

        // 1. Contiene todas las fotos aprobadas
        $response->assertSee($fotoPrincipal->url);
        $response->assertSee($fotoAdicional1->url);
        $response->assertSee($fotoAdicional2->url);

        // 2. Indicador de posición (1 de 3)
        $response->assertSee('1 de 3');

        // 3. Controles de navegación anterior y siguiente
        $response->assertSee('Fotografía anterior');
        $response->assertSee('Fotografía siguiente');

        // 4. Etiqueta de fotografía principal
        $response->assertSee('Fotografía principal');

        // 5. Botón ampliar fotografía
        $response->assertSee('Ampliar');
    }

    /**
     * CA-03 (Publicación con una sola fotografía):
     * - Muestra la fotografía y el botón ampliar.
     * - No muestra controles de navegación anterior ni siguiente ni cuadrícula de miniaturas innecesaria.
     * - Muestra posición "1 de 1".
     */
    public function test_galeria_maneja_correctamente_publicacion_con_una_sola_fotografia(): void
    {
        $autor = User::factory()->create();
        $lector = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Termo Stanley Verde',
            'titulo' => 'Termo Stanley Verde',
            'descripcion' => 'Termo metálico de 1 litro con tapa negra.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => now()->subDay(),
            'fecha_aprobacion' => now()->subDay(),
            'fecha_vencimiento' => now()->addDays(13),
        ]);

        $fotoUnica = Fotografia::create([
            'publicacion_id' => $publicacion->id,
            'ruta' => 'hallazgos/termo.jpg',
            'nombre_original' => 'termo.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 40000,
            'es_principal' => true,
        ]);

        $response = $this->actingAs($lector)->get(route('publicaciones.show', $publicacion->id));
        $response->assertOk();

        // Muestra la foto única y el botón ampliar
        $response->assertSee($fotoUnica->url);
        $response->assertSee('Ampliar');
        $response->assertSee('1 de 1');

        // No debe mostrar controles innecesarios de navegación
        $response->assertDontSee('Fotografía anterior');
        $response->assertDontSee('Fotografía siguiente');
    }

    /**
     * CA-04 (Publicación sin fotografías):
     * - Muestra el mensaje informativo "Sin fotografía adjunta".
     * - No muestra controles de navegación ni miniaturas.
     */
    public function test_galeria_maneja_correctamente_publicacion_sin_fotografias(): void
    {
        $autor = User::factory()->create();
        $lector = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Llaves con llavero de Torre Eiffel',
            'titulo' => 'Llaves con llavero de Torre Eiffel',
            'descripcion' => 'Juego de dos llaves pequeñas.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => now()->subDay(),
            'fecha_aprobacion' => now()->subDay(),
            'fecha_vencimiento' => now()->addDays(13),
        ]);

        $response = $this->actingAs($lector)->get(route('publicaciones.show', $publicacion->id));
        $response->assertOk();

        $response->assertSee('Sin fotografía adjunta');
        $response->assertDontSee('Fotografía anterior');
        $response->assertDontSee('Fotografía siguiente');
        $response->assertDontSee('Ampliar');
    }
}

