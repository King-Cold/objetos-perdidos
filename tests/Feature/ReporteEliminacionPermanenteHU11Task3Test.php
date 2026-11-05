<?php

namespace Tests\Feature;

use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pruebas para la HU-11 / Subtarea 3:
 * Implementar eliminación definitiva segura y actualización del historial.
 */
class ReporteEliminacionPermanenteHU11Task3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    /**
     * Helper para crear y asociar una fotografía física en disco y en BD.
     */
    protected function attachPhoto(Publicacion $publicacion, string $nombre = 'foto.jpg', string $disk = 'local', ?string $customPath = null): Fotografia
    {
        $path = $customPath ?? ('hallazgos/' . uniqid() . '_' . $nombre);
        Storage::disk($disk)->put($path, 'contenido-falso-de-imagen');

        return Fotografia::create([
            'publicacion_id' => $publicacion->id,
            'ruta' => $path,
            'nombre_original' => $nombre,
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 1024,
            'es_principal' => true,
        ]);
    }

    /**
     * Escenario 1: Eliminación exitosa de una publicación cancelada y elegible.
     */
    public function test_successful_permanent_deletion_of_cancelled_and_eligible_publication(): void
    {
        $author = User::factory()->create();

        $reporte = Publicacion::factory()->cancelado()->create([
            'user_id' => $author->id,
            'nombre' => 'Laptop HP Cancelada',
            'folio' => 'OBJ-20261104-HP01',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'devolucion_en_proceso' => false,
            'incidencia_abierta' => false,
            'tiene_incidencia' => false,
            'estado_devolucion' => null,
        ]);

        $foto = $this->attachPhoto($reporte, 'laptop.jpg');

        $this->assertDatabaseHas('publicaciones', ['id' => $reporte->id]);
        $this->assertDatabaseHas('fotografias', ['id' => $foto->id]);
        Storage::disk('local')->assertExists($foto->ruta);

        $response = $this->actingAs($author)->delete(route('reportes.destroy', $reporte->id));

        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('status', 'La publicación se eliminó permanentemente');
        $response->assertSessionMissing('error');
        $response->assertSessionMissing('warning');

        // Registro eliminado permanentemente de la base de datos
        $this->assertDatabaseMissing('publicaciones', ['id' => $reporte->id]);
        $this->assertDatabaseMissing('fotografias', ['id' => $foto->id]);

        // Archivo físico eliminado del almacenamiento
        Storage::disk('local')->assertMissing($foto->ruta);
    }

    /**
     * Escenario 2: Eliminación de sus fotografías exclusivamente asociadas.
     */
    public function test_deletion_of_exclusively_associated_photographs(): void
    {
        $author = User::factory()->create();

        $reporte = Publicacion::factory()->cancelado()->create([
            'user_id' => $author->id,
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'devolucion_en_proceso' => false,
            'incidencia_abierta' => false,
            'tiene_incidencia' => false,
            'estado_devolucion' => null,
        ]);

        $foto1 = $this->attachPhoto($reporte, 'camara_frontal.jpg');
        $foto2 = $this->attachPhoto($reporte, 'camara_trasera.jpg');

        Storage::disk('local')->assertExists($foto1->ruta);
        Storage::disk('local')->assertExists($foto2->ruta);

        $response = $this->actingAs($author)->delete(route('reportes.destroy', $reporte->id));

        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('status', 'La publicación se eliminó permanentemente');

        // Ambas fotografías exclusivas deben haber sido eliminadas de disco y BD
        Storage::disk('local')->assertMissing($foto1->ruta);
        Storage::disk('local')->assertMissing($foto2->ruta);
        $this->assertDatabaseMissing('fotografias', ['id' => $foto1->id]);
        $this->assertDatabaseMissing('fotografias', ['id' => $foto2->id]);
    }

    /**
     * Escenario 3: Conservación de publicaciones y archivos de otros reportes.
     * Si un archivo estuviera referenciado por otro reporte, no debe borrarse del almacenamiento físico.
     */
    public function test_preservation_of_publications_and_files_of_other_reports(): void
    {
        $authorA = User::factory()->create();
        $authorB = User::factory()->create();

        // Reporte A (será eliminado)
        $reporteA = Publicacion::factory()->cancelado()->create([
            'user_id' => $authorA->id,
            'folio' => 'OBJ-20261104-REPA',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'devolucion_en_proceso' => false,
            'incidencia_abierta' => false,
            'tiene_incidencia' => false,
            'estado_devolucion' => null,
        ]);

        // Reporte B (otro reporte ajeno que debe conservarse intacto)
        $reporteB = Publicacion::factory()->create([
            'user_id' => $authorB->id,
            'folio' => 'OBJ-20261104-REPB',
            'estado' => Publicacion::ESTADO_APROBADA,
        ]);

        // Foto exclusiva del reporte A
        $fotoExclusivaA = $this->attachPhoto($reporteA, 'foto_exclusiva_a.jpg');

        // Foto exclusiva del reporte B
        $fotoExclusivaB = $this->attachPhoto($reporteB, 'foto_exclusiva_b.jpg');

        // Foto con ruta compartida (simulada) entre A y B
        $rutaCompartida = 'hallazgos/foto_compartida_institucional.jpg';
        Storage::disk('local')->put($rutaCompartida, 'contenido-compartido');

        $fotoCompartidaA = Fotografia::create([
            'publicacion_id' => $reporteA->id,
            'ruta' => $rutaCompartida,
            'nombre_original' => 'compartida_a.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 2048,
            'es_principal' => false,
        ]);

        $fotoCompartidaB = Fotografia::create([
            'publicacion_id' => $reporteB->id,
            'ruta' => $rutaCompartida,
            'nombre_original' => 'compartida_b.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 2048,
            'es_principal' => false,
        ]);

        // El autor A elimina su reporte A
        $response = $this->actingAs($authorA)->delete(route('reportes.destroy', $reporteA->id));

        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('status', 'La publicación se eliminó permanentemente');

        // Reporte A y su foto exclusiva fueron eliminados
        $this->assertDatabaseMissing('publicaciones', ['id' => $reporteA->id]);
        $this->assertDatabaseMissing('fotografias', ['id' => $fotoExclusivaA->id]);
        Storage::disk('local')->assertMissing($fotoExclusivaA->ruta);

        // La foto de A en BD se eliminó, pero el archivo compartido en disco NO se eliminó porque Reporte B lo usa
        $this->assertDatabaseMissing('fotografias', ['id' => $fotoCompartidaA->id]);
        Storage::disk('local')->assertExists($rutaCompartida);

        // El reporte B y sus fotografías permanecen intactos
        $this->assertDatabaseHas('publicaciones', [
            'id' => $reporteB->id,
            'folio' => 'OBJ-20261104-REPB',
            'estado' => Publicacion::ESTADO_APROBADA,
        ]);
        $this->assertDatabaseHas('fotografias', ['id' => $fotoExclusivaB->id]);
        $this->assertDatabaseHas('fotografias', ['id' => $fotoCompartidaB->id]);
        Storage::disk('local')->assertExists($fotoExclusivaB->ruta);
    }

    /**
     * Escenario 4: Bloqueo de acceso mediante el enlace anterior.
     */
    public function test_blocking_access_via_previous_links_after_deletion(): void
    {
        $author = User::factory()->create();

        $reporte = Publicacion::factory()->cancelado()->create([
            'user_id' => $author->id,
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'devolucion_en_proceso' => false,
            'incidencia_abierta' => false,
            'tiene_incidencia' => false,
            'estado_devolucion' => null,
        ]);
        $foto = $this->attachPhoto($reporte, 'foto_privada.jpg');

        $reporteId = $reporte->id;
        $fotoId = $foto->id;

        // Comprobación previa: las rutas existen antes de la eliminación
        $this->actingAs($author)->get(route('reportes.show', $reporteId))->assertOk();
        $this->actingAs($author)->get(route('fotografias.show', $fotoId))->assertOk();

        // Ejecutar eliminación permanente
        $this->actingAs($author)->delete(route('reportes.destroy', $reporteId))
            ->assertRedirect(route('reportes.mis-reportes'))
            ->assertSessionHas('status', 'La publicación se eliminó permanentemente');

        // Intentar consultar mediante el enlace anterior en show privado: 404 Not Found
        $this->actingAs($author)->get(route('reportes.show', $reporteId))->assertNotFound();

        // Intentar editar mediante el enlace anterior: 404 Not Found
        $this->actingAs($author)->get(route('reportes.edit', $reporteId))->assertNotFound();

        // Intentar acceder a la fotografía mediante su enlace anterior: 404 Not Found
        $this->actingAs($author)->get(route('fotografias.show', $fotoId))->assertNotFound();

        // Intentar consultar en el muro público: no disponible / redirigido
        $this->get(route('publicaciones.show', $reporteId))->assertRedirect(route('muro'));
    }

    /**
     * Escenario 5: Manejo de errores durante la eliminación.
     * Evita la eliminación parcial y preserva la integridad si ocurre un error en la base de datos.
     */
    public function test_error_handling_during_database_failure_prevents_partial_deletion(): void
    {
        $author = User::factory()->create();

        $reporte = Publicacion::factory()->cancelado()->create([
            'user_id' => $author->id,
            'nombre' => 'Objeto con Error Simulado',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'devolucion_en_proceso' => false,
            'incidencia_abierta' => false,
            'tiene_incidencia' => false,
            'estado_devolucion' => null,
        ]);
        $foto = $this->attachPhoto($reporte, 'foto_resistente.jpg');

        // Simular una falla durante el borrado del modelo
        Publicacion::deleting(function ($model) use ($reporte) {
            if ($model->id === $reporte->id) {
                throw new \RuntimeException('Falla simulada de base de datos durante delete');
            }
        });

        $response = $this->actingAs($author)->delete(route('reportes.destroy', $reporte->id));

        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('error');
        $response->assertSessionMissing('status');

        // El reporte NO debe haberse eliminado de la base de datos (rollback)
        $this->assertDatabaseHas('publicaciones', [
            'id' => $reporte->id,
            'nombre' => 'Objeto con Error Simulado',
        ]);

        // Las fotografías deben permanecer en base de datos
        $this->assertDatabaseHas('fotografias', [
            'id' => $foto->id,
            'publicacion_id' => $reporte->id,
        ]);

        // El archivo físico debe permanecer intacto en el almacenamiento (restaurado)
        Storage::disk('local')->assertExists($foto->ruta);
    }

    /**
     * Escenario 6: Actualización correcta del historial («Mis reportes») después de la operación.
     */
    public function test_mis_reportes_list_is_updated_and_excludes_deleted_publication(): void
    {
        $author = User::factory()->create();

        // Reporte cancelado y elegible (será eliminado)
        $reporteAEliminar = Publicacion::factory()->cancelado()->create([
            'user_id' => $author->id,
            'nombre' => 'Bolígrafo Montblanc Cancelado',
            'folio' => 'OBJ-20261104-BOL1',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'devolucion_en_proceso' => false,
            'incidencia_abierta' => false,
            'tiene_incidencia' => false,
            'estado_devolucion' => null,
        ]);
        $this->attachPhoto($reporteAEliminar, 'boligrafo.jpg');

        // Otro reporte del mismo usuario que debe permanecer en el historial
        $reporteConservado = Publicacion::factory()->create([
            'user_id' => $author->id,
            'nombre' => 'Cartera de Cuero Activa',
            'folio' => 'OBJ-20261104-CART2',
            'estado' => Publicacion::ESTADO_APROBADA,
        ]);

        // Antes de eliminar: ambos reportes aparecen en «Mis reportes»
        $vistaInicial = $this->actingAs($author)->get(route('reportes.mis-reportes'));
        $vistaInicial->assertSee('OBJ-20261104-BOL1');
        $vistaInicial->assertSee('OBJ-20261104-CART2');

        // Ejecutar eliminación
        $response = $this->actingAs($author)->delete(route('reportes.destroy', $reporteAEliminar->id));
        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('status', 'La publicación se eliminó permanentemente');

        // Tras la eliminación: solo permanece el segundo reporte en «Mis reportes»
        $vistaFinal = $this->actingAs($author)->get(route('reportes.mis-reportes'));
        $vistaFinal->assertDontSee('OBJ-20261104-BOL1');
        $vistaFinal->assertDontSee('Bolígrafo Montblanc Cancelado');
        $vistaFinal->assertSee('OBJ-20261104-CART2');
        $vistaFinal->assertSee('Cartera de Cuero Activa');
    }

    /**
     * Escenario 7: Si el usuario elimina su único reporte, se muestra el estado vacío de «Mis reportes».
     */
    public function test_mis_reportes_shows_empty_state_when_only_report_is_deleted(): void
    {
        $author = User::factory()->create();

        $reporteUnico = Publicacion::factory()->cancelado()->create([
            'user_id' => $author->id,
            'nombre' => 'Único Reporte Registrado',
            'folio' => 'OBJ-20261104-UNIC1',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'devolucion_en_proceso' => false,
            'incidencia_abierta' => false,
            'tiene_incidencia' => false,
            'estado_devolucion' => null,
        ]);
        $this->attachPhoto($reporteUnico, 'unico.jpg');

        $this->actingAs($author)->delete(route('reportes.destroy', $reporteUnico->id))
            ->assertRedirect(route('reportes.mis-reportes'));

        $vista = $this->actingAs($author)->get(route('reportes.mis-reportes'));
        $vista->assertSee('Aún no has registrado objetos encontrados');
        $vista->assertDontSee('OBJ-20261104-UNIC1');
    }

    /**
     * Escenario 8: La vista «Mis reportes» renderiza el banner de éxito con el mensaje exacto.
     */
    public function test_mis_reportes_displays_exact_success_message_banner(): void
    {
        $author = User::factory()->create();

        $reporte = Publicacion::factory()->cancelado()->create([
            'user_id' => $author->id,
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'devolucion_en_proceso' => false,
            'incidencia_abierta' => false,
            'tiene_incidencia' => false,
            'estado_devolucion' => null,
        ]);
        $this->attachPhoto($reporte);

        $response = $this->actingAs($author)->delete(route('reportes.destroy', $reporte->id));

        $responseFollowed = $this->actingAs($author)->get(route('reportes.mis-reportes'));
        $responseFollowed->assertSee('La publicación se eliminó permanentemente');
    }
}

