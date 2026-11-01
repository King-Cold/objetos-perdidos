<?php

namespace Tests\Feature;

use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FotografiaMuroPublicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * CA Muro: Una fotografía de una publicación aprobada y disponible debe poder ser visualizada
     * tanto por visitantes sin sesión como por usuarios autenticados.
     */
    public function test_fotografia_de_publicacion_aprobada_es_accesible_en_muro_publico(): void
    {
        $autor = User::factory()->create();
        Storage::disk('local')->put('hallazgos/test_foto.jpg', 'contenido_imagen_falsa');

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Laptop HP Pavilion',
            'titulo' => 'Laptop HP Pavilion',
            'descripcion' => 'Laptop azul en buen estado.',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => Carbon::now(Publicacion::TIMEZONE),
            'fecha_vencimiento' => Carbon::now(Publicacion::TIMEZONE)->addDays(14),
        ]);

        $foto = Fotografia::create([
            'publicacion_id' => $publicacion->id,
            'ruta' => 'hallazgos/test_foto.jpg',
            'nombre_original' => 'test_foto.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 1024,
            'es_principal' => true,
        ]);

        // 1. Visitante sin sesión puede consultar la fotografía de la publicación aprobada
        $responseGuest = $this->get(route('fotografias.show', $foto->id));
        $this->assertEquals(200, $responseGuest->getStatusCode());

        // 2. Usuario normal autenticado puede consultar la fotografía
        $lector = User::factory()->create();
        $responseUser = $this->actingAs($lector)->get(route('fotografias.show', $foto->id));
        $this->assertEquals(200, $responseUser->getStatusCode());
    }

    /**
     * Tarea 05.5: Fotografías de publicaciones no aprobadas (pendientes) no son públicas y redirigen al login para visitantes.
     */
    public function test_fotografia_de_publicacion_pendiente_no_es_publica_y_redirige_a_login(): void
    {
        $autor = User::factory()->create();
        Storage::disk('local')->put('hallazgos/foto_privada.jpg', 'contenido_privado');

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'disponible' => false,
        ]);

        $foto = Fotografia::create([
            'publicacion_id' => $publicacion->id,
            'ruta' => 'hallazgos/foto_privada.jpg',
            'nombre_original' => 'foto_privada.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 1024,
            'es_principal' => true,
        ]);

        // Visitante sin sesión es redirigido a login
        $responseGuest = $this->get(route('fotografias.show', $foto->id));
        $responseGuest->assertRedirect(route('login'));

        // Usuario ajeno autenticado recibe 403 Forbidden
        $lector = User::factory()->create();
        $responseUser = $this->actingAs($lector)->get(route('fotografias.show', $foto->id));
        $responseUser->assertForbidden();

        // Autor puede verla
        $responseAuthor = $this->actingAs($autor)->get(route('fotografias.show', $foto->id));
        $this->assertEquals(200, $responseAuthor->getStatusCode());
    }
}
