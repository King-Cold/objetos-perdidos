<?php

namespace Tests\Feature;

use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReporteFotografiasTask063Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * CA-18: Permite conservar las fotografías existentes sin necesidad de volverlas a subir.
     */
    public function test_existing_photos_are_preserved_when_not_marked_for_deletion(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->requiereCorreccion()->create([
            'user_id' => $user->id,
            'nombre' => 'Laptop Dell Inspiron',
            'descripcion' => 'Descripción válida de cincuenta caracteres para el reporte de prueba.',
            'fecha_captura' => Carbon::now(Publicacion::TIMEZONE),
            'fecha_hallazgo' => Carbon::now(Publicacion::TIMEZONE)->format('Y-m-d'),
            'area_zona' => 'Edificio C',
            'ubicacion_especifica' => 'Aula C1',
        ]);

        $foto1 = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/foto1.jpg',
            'nombre_original' => 'foto1.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
            'es_principal' => true,
        ]);

        $foto2 = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/foto2.jpg',
            'nombre_original' => 'foto2.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
            'es_principal' => false,
        ]);

        $response = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Laptop Dell Inspiron Actualizada',
            'descripcion' => 'Descripción válida de cincuenta caracteres para el reporte de prueba.',
            'fecha_hallazgo' => Carbon::now(Publicacion::TIMEZONE)->format('Y-m-d'),
            'area_zona' => 'Edificio C',
            'ubicacion_especifica' => 'Aula C1',
            'eliminar_fotos' => [],
            'foto_principal_id' => $foto1->id,
        ]);

        $response->assertRedirect(route('reportes.mis-reportes'));
        $this->assertDatabaseCount('fotografias', 2);
        $this->assertDatabaseHas('fotografias', ['id' => $foto1->id, 'es_principal' => true]);
        $this->assertDatabaseHas('fotografias', ['id' => $foto2->id, 'es_principal' => false]);
    }

    /**
     * CA-18: Permite retirar fotografías existentes individualmente.
     */
    public function test_can_remove_specific_existing_photo(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->create([
            'user_id' => $user->id,
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'fecha_captura' => Carbon::now(Publicacion::TIMEZONE),
            'fecha_hallazgo' => Carbon::now(Publicacion::TIMEZONE)->format('Y-m-d'),
        ]);

        $foto1 = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/foto1.jpg',
            'nombre_original' => 'foto1.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
            'es_principal' => true,
        ]);

        $foto2 = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/foto2.jpg',
            'nombre_original' => 'foto2.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
            'es_principal' => false,
        ]);

        $response = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => $reporte->nombre,
            'descripcion' => $reporte->descripcion,
            'fecha_hallazgo' => $reporte->fecha_hallazgo->format('Y-m-d'),
            'area_zona' => $reporte->area_zona,
            'ubicacion_especifica' => $reporte->ubicacion_especifica,
            'eliminar_fotos' => [$foto2->id],
            'foto_principal_id' => $foto1->id,
        ]);

        $response->assertRedirect(route('reportes.mis-reportes'));
        $this->assertDatabaseMissing('fotografias', ['id' => $foto2->id]);
        $this->assertDatabaseHas('fotografias', ['id' => $foto1->id]);
    }

    /**
     * CA-18, CA-19: Permite agregar nuevas fotografías en formatos JPG y PNG respetando el límite de 5.
     */
    public function test_can_upload_new_photos_in_jpg_and_png(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->create([
            'user_id' => $user->id,
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'fecha_captura' => Carbon::now(Publicacion::TIMEZONE),
            'fecha_hallazgo' => Carbon::now(Publicacion::TIMEZONE)->format('Y-m-d'),
        ]);

        $foto1 = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/foto1.jpg',
            'nombre_original' => 'foto1.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
            'es_principal' => true,
        ]);

        $newJpg = UploadedFile::fake()->image('nueva1.jpg', 600, 600)->size(1500); // 1.5 MB
        $newPng = UploadedFile::fake()->image('nueva2.png', 600, 600)->size(800);  // 800 KB

        $response = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => $reporte->nombre,
            'descripcion' => $reporte->descripcion,
            'fecha_hallazgo' => $reporte->fecha_hallazgo->format('Y-m-d'),
            'area_zona' => $reporte->area_zona,
            'ubicacion_especifica' => $reporte->ubicacion_especifica,
            'nuevas_fotografias' => [$newJpg, $newPng],
            'foto_principal_id' => $foto1->id,
        ]);

        $response->assertRedirect(route('reportes.mis-reportes'));
        $this->assertDatabaseCount('fotografias', 3);
        $this->assertDatabaseHas('fotografias', ['nombre_original' => 'nueva1.jpg']);
        $this->assertDatabaseHas('fotografias', ['nombre_original' => 'nueva2.png']);
    }

    /**
     * CA-19: Rechaza archivos que exceden los 2 MB (2,097,152 bytes) y archivos que no sean imágenes válidas.
     */
    public function test_rejects_files_exceeding_2mb_and_non_image_files(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->create([
            'user_id' => $user->id,
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'fecha_captura' => Carbon::now(Publicacion::TIMEZONE),
            'fecha_hallazgo' => Carbon::now(Publicacion::TIMEZONE)->format('Y-m-d'),
        ]);

        $foto1 = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/foto1.jpg',
            'nombre_original' => 'foto1.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
            'es_principal' => true,
        ]);

        // 1. Archivo mayor a 2 MB (2049 KB)
        $archivoPesado = UploadedFile::fake()->image('pesada.jpg')->size(2049);
        $res1 = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => $reporte->nombre,
            'descripcion' => $reporte->descripcion,
            'fecha_hallazgo' => $reporte->fecha_hallazgo->format('Y-m-d'),
            'area_zona' => $reporte->area_zona,
            'ubicacion_especifica' => $reporte->ubicacion_especifica,
            'nuevas_fotografias' => [$archivoPesado],
        ]);
        $res1->assertSessionHasErrors('nuevas_fotografias.0');

        // 2. Archivo con formato no permitido (PDF)
        $archivoPdf = UploadedFile::fake()->create('documento.pdf', 500, 'application/pdf');
        $res2 = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => $reporte->nombre,
            'descripcion' => $reporte->descripcion,
            'fecha_hallazgo' => $reporte->fecha_hallazgo->format('Y-m-d'),
            'area_zona' => $reporte->area_zona,
            'ubicacion_especifica' => $reporte->ubicacion_especifica,
            'nuevas_fotografias' => [$archivoPdf],
        ]);
        $res2->assertSessionHasErrors('nuevas_fotografias.0');
    }

    /**
     * CA-20: Identifica fotografía principal; al retirar la principal, la foto restante pasa a ser la principal automáticamente.
     */
    public function test_when_current_principal_is_removed_remaining_photo_becomes_principal(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->create([
            'user_id' => $user->id,
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'fecha_captura' => Carbon::now(Publicacion::TIMEZONE),
            'fecha_hallazgo' => Carbon::now(Publicacion::TIMEZONE)->format('Y-m-d'),
        ]);

        $fotoPrincipal = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/foto_princ.jpg',
            'nombre_original' => 'foto_princ.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
            'es_principal' => true,
        ]);

        $fotoSecundaria = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/foto_sec.jpg',
            'nombre_original' => 'foto_sec.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
            'es_principal' => false,
        ]);

        // Retira la principal; el sistema automáticamente promueve la foto restante
        $response = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => $reporte->nombre,
            'descripcion' => $reporte->descripcion,
            'fecha_hallazgo' => $reporte->fecha_hallazgo->format('Y-m-d'),
            'area_zona' => $reporte->area_zona,
            'ubicacion_especifica' => $reporte->ubicacion_especifica,
            'eliminar_fotos' => [$fotoPrincipal->id],
        ]);

        $response->assertRedirect(route('reportes.mis-reportes'));
        $this->assertDatabaseMissing('fotografias', ['id' => $fotoPrincipal->id]);
        $this->assertDatabaseHas('fotografias', ['id' => $fotoSecundaria->id, 'es_principal' => true]);
    }

    /**
     * CA-21: Impide guardar si el reporte queda con 0 fotografías o con más de 5 fotografías.
     */
    public function test_rejects_saving_with_zero_or_more_than_five_photos(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->create([
            'user_id' => $user->id,
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'fecha_captura' => Carbon::now(Publicacion::TIMEZONE),
            'fecha_hallazgo' => Carbon::now(Publicacion::TIMEZONE)->format('Y-m-d'),
        ]);

        $foto1 = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/foto1.jpg',
            'nombre_original' => 'foto1.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
            'es_principal' => true,
        ]);

        // 1. Caso: Intentar eliminar la única foto sin subir ninguna nueva (0 fotos)
        $resZero = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => $reporte->nombre,
            'descripcion' => $reporte->descripcion,
            'fecha_hallazgo' => $reporte->fecha_hallazgo->format('Y-m-d'),
            'area_zona' => $reporte->area_zona,
            'ubicacion_especifica' => $reporte->ubicacion_especifica,
            'eliminar_fotos' => [$foto1->id],
        ]);
        $resZero->assertSessionHasErrors('fotografias');

        // 2. Caso: Conservar 1 y subir 5 fotos más (resultarían 6 fotos)
        $nuevas6 = [
            UploadedFile::fake()->image('f1.jpg'),
            UploadedFile::fake()->image('f2.jpg'),
            UploadedFile::fake()->image('f3.jpg'),
            UploadedFile::fake()->image('f4.jpg'),
            UploadedFile::fake()->image('f5.jpg'),
        ];

        $resExceso = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => $reporte->nombre,
            'descripcion' => $reporte->descripcion,
            'fecha_hallazgo' => $reporte->fecha_hallazgo->format('Y-m-d'),
            'area_zona' => $reporte->area_zona,
            'ubicacion_especifica' => $reporte->ubicacion_especifica,
            'eliminar_fotos' => [],
            'nuevas_fotografias' => $nuevas6,
        ]);
        $resExceso->assertSessionHasErrors('fotografias');
    }

    /**
     * CA-30: Ante errores de validación, conserva los datos y fotografías existentes en la base de datos sin alteraciones.
     */
    public function test_existing_data_and_photos_remain_intact_when_validation_fails(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Nombre Intacto Original',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'fecha_captura' => Carbon::now(Publicacion::TIMEZONE),
            'fecha_hallazgo' => Carbon::now(Publicacion::TIMEZONE)->format('Y-m-d'),
        ]);

        $foto = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/intacta.jpg',
            'nombre_original' => 'intacta.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
            'es_principal' => true,
        ]);

        // Intento con descripción demasiado corta (falla CA-14) y eliminación de foto
        $response = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Intento Fallido',
            'descripcion' => 'Corta', // inválida
            'fecha_hallazgo' => Carbon::now(Publicacion::TIMEZONE)->format('Y-m-d'),
            'area_zona' => 'Edificio G',
            'ubicacion_especifica' => 'Aula 1',
            'eliminar_fotos' => [$foto->id],
        ]);

        $response->assertSessionHasErrors('descripcion');

        $reporte->refresh();
        $this->assertEquals('Nombre Intacto Original', $reporte->nombre);
        $this->assertDatabaseHas('fotografias', ['id' => $foto->id]);
    }
}

