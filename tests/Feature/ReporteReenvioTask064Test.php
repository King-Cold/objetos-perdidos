<?php

namespace Tests\Feature;

use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ReporteReenvioTask064Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper para crear una fotografía base asociada a la publicación.
     */
    protected function attachPhoto(Publicacion $publicacion, bool $isPrincipal = true): Fotografia
    {
        return Fotografia::create([
            'publicacion_id' => $publicacion->id,
            'ruta' => 'hallazgos/test_' . uniqid() . '.jpg',
            'nombre_original' => 'foto.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
            'es_principal' => $isPrincipal,
        ]);
    }

    /**
     * CA-24: Guardar sobre el mismo reporte conserva folio, autor y creación, registra actualización y queda en «Pendiente de aprobación».
     */
    public function test_save_updates_existing_record_without_duplication_and_sets_pending_state(): void
    {
        $user = User::factory()->create();
        $originalCapture = Carbon::now(Publicacion::TIMEZONE)->subDays(2);

        $reporte = Publicacion::factory()->requiereCorreccion()->create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261029-0001',
            'fecha_captura' => $originalCapture,
            'nombre' => 'Mochila Negra',
            'descripcion' => 'Descripción inicial de prueba con más de cincuenta caracteres exactos.',
            'fecha_hallazgo' => $originalCapture->format('Y-m-d'),
            'area_zona' => 'Edificio A',
            'ubicacion_especifica' => 'Aula 101',
        ]);
        $this->attachPhoto($reporte);

        $initialCount = Publicacion::count();

        $response = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Mochila Negra Marca Chenson',
            'descripcion' => 'Descripción corregida con detalle suficiente para cumplir los criterios de validación.',
            'fecha_hallazgo' => $originalCapture->format('Y-m-d'),
            'area_zona' => 'Edificio B',
            'ubicacion_especifica' => 'Laboratorio de Cómputo 2',
        ]);

        $response->assertRedirect(route('reportes.mis-reportes'));

        // No se duplica el registro (mismo conteo e ID)
        $this->assertEquals($initialCount, Publicacion::count());

        $reporte->refresh();

        // Inmutabilidad de metadatos institucionales (CA-24)
        $this->assertEquals('OBJ-20261029-0001', $reporte->folio);
        $this->assertEquals($user->id, $reporte->user_id);
        $this->assertEquals($originalCapture->toDateTimeString(), $reporte->fecha_captura->toDateTimeString());

        // Campos actualizados y estado a 'Pendiente de aprobación'
        $this->assertEquals('Mochila Negra Marca Chenson', $reporte->nombre);
        $this->assertEquals(Publicacion::ESTADO_PENDIENTE, $reporte->estado);
    }

    /**
     * CA-25: Si tuvo aprobación previa, conservar primera aprobación y vencimiento. Guardar no reinicia ni amplía los 14 días.
     */
    public function test_preserves_first_approval_and_expiration_dates_without_extending_14_days(): void
    {
        $user = User::factory()->create();
        $captureDate = Carbon::now(Publicacion::TIMEZONE)->subDays(5);
        $firstApprovalDate = Carbon::now(Publicacion::TIMEZONE)->subDays(4);
        $originalExpirationDate = $firstApprovalDate->copy()->addDays(14);

        $reporte = Publicacion::factory()->create([
            'user_id' => $user->id,
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_captura' => $captureDate,
            'fecha_primera_aprobacion' => $firstApprovalDate,
            'fecha_vencimiento' => $originalExpirationDate,
            'nombre' => 'Calculadora Científica Casio',
            'descripcion' => 'Descripción completa de la calculadora encontrada en biblioteca con longitud suficiente.',
            'fecha_hallazgo' => $captureDate->format('Y-m-d'),
            'area_zona' => 'Biblioteca',
            'ubicacion_especifica' => 'Mesa de estudio 3',
        ]);
        $this->attachPhoto($reporte);

        $response = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Calculadora Científica Casio FX-991',
            'descripcion' => 'Descripción editada manteniendo todos los requerimientos y sin reiniciar vigencia.',
            'fecha_hallazgo' => $captureDate->format('Y-m-d'),
            'area_zona' => 'Biblioteca',
            'ubicacion_especifica' => 'Mesa 4 planta alta',
        ]);

        $response->assertRedirect(route('reportes.mis-reportes'));

        $reporte->refresh();

        // CA-25: La fecha de primera aprobación y vencimiento se mantienen idénticas
        $this->assertEquals($firstApprovalDate->toDateTimeString(), $reporte->fecha_primera_aprobacion->toDateTimeString());
        $this->assertEquals($originalExpirationDate->toDateTimeString(), $reporte->fecha_vencimiento->toDateTimeString());
    }

    /**
     * CA-26: Si se edita una publicación disponible, se retira del muro y su detalle general ya no es accesible públicamente.
     */
    public function test_editing_available_publication_removes_it_from_muro_and_public_detail_until_reapproval(): void
    {
        $user = User::factory()->create();
        $captureDate = Carbon::now(Publicacion::TIMEZONE)->subDays(2);
        $approvalDate = Carbon::now(Publicacion::TIMEZONE)->subDay();
        $expirationDate = $approvalDate->copy()->addDays(14);

        $reporte = Publicacion::factory()->create([
            'user_id' => $user->id,
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_captura' => $captureDate,
            'fecha_primera_aprobacion' => $approvalDate,
            'fecha_vencimiento' => $expirationDate,
            'nombre' => 'Termo Metálico Azul',
            'descripcion' => 'Termo encontrado en cafetería con longitud suficiente para superar las validaciones.',
            'fecha_hallazgo' => $captureDate->format('Y-m-d'),
            'area_zona' => 'Cafetería',
            'ubicacion_especifica' => 'Mesa exterior 2',
        ]);
        $this->attachPhoto($reporte);

        // Inicialmente está disponible en el Muro y en publicaciones.show
        $resMuroPre = $this->get(route('muro'));
        $resMuroPre->assertSee('Termo Metálico Azul');

        $resShowPre = $this->get(route('publicaciones.show', $reporte->id));
        $resShowPre->assertStatus(200);

        // El autor edita y reenvía su reporte
        $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Termo Metálico Azul Marino Yeti',
            'descripcion' => 'Descripción corregida con detalle de marca y stickers visibles en cafetería.',
            'fecha_hallazgo' => $captureDate->format('Y-m-d'),
            'area_zona' => 'Cafetería',
            'ubicacion_especifica' => 'Mesa exterior 2 cerca del jardín',
        ]);

        $reporte->refresh();

        // CA-26: Pasa a no disponible y pendiente de aprobación
        $this->assertFalse((bool) $reporte->disponible);
        $this->assertEquals(Publicacion::ESTADO_PENDIENTE, $reporte->estado);

        // Ya NO aparece en el Muro público
        $resMuroPost = $this->get(route('muro'));
        $resMuroPost->assertDontSee('Termo Metálico Azul Marino Yeti');
        $resMuroPost->assertDontSee('Termo Metálico Azul');

        // Ya NO es accesible en el detalle general público publicaciones.show
        $resShowPost = $this->get(route('publicaciones.show', $reporte->id));
        $resShowPost->assertRedirect(route('muro'));
        $resShowPost->assertSessionHas('warning', 'Esta publicación no está disponible para consulta');
    }

    /**
     * CA-27: Muestra «Los cambios se guardaron y el reporte se envió a revisión». En «Mis reportes» figura como «Pendiente de aprobación».
     */
    public function test_displays_exact_success_message_and_reflects_pending_approval_in_mis_reportes(): void
    {
        $user = User::factory()->create();
        $captureDate = Carbon::now(Publicacion::TIMEZONE)->subDays(1);

        $reporte = Publicacion::factory()->requiereCorreccion()->create([
            'user_id' => $user->id,
            'fecha_captura' => $captureDate,
            'nombre' => 'Lentes de Sol Negros',
            'descripcion' => 'Lentes de pasta color negro encontrados en pasillo principal con longitud adecuada.',
            'fecha_hallazgo' => $captureDate->format('Y-m-d'),
            'area_zona' => 'Edificio C',
            'ubicacion_especifica' => 'Banca de espera',
        ]);
        $this->attachPhoto($reporte);

        $response = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Lentes de Sol Ray-Ban Negros',
            'descripcion' => 'Lentes de pasta con estuche negro encontrados en banca de espera del edificio C.',
            'fecha_hallazgo' => $captureDate->format('Y-m-d'),
            'area_zona' => 'Edificio C',
            'ubicacion_especifica' => 'Banca de espera junto a prefectura',
        ]);

        // Redirección con el mensaje exacto exigido por CA-27
        $response->assertRedirect(route('reportes.mis-reportes'));
        $response->assertSessionHas('status', 'Los cambios se guardaron y el reporte se envió a revisión');

        // Al consultar Mis Reportes, se muestra el mensaje y el estado escrito
        $resList = $this->actingAs($user)->get(route('reportes.mis-reportes'));
        $resList->assertSee('Los cambios se guardaron y el reporte se envió a revisión');
        $resList->assertSee('Pendiente de aprobación');
    }

    /**
     * CA-28: Si se aprueba nuevamente antes de vencer, solo podrá publicarse por el tiempo restante de la vigencia original.
     */
    public function test_reapproval_before_expiration_only_publishes_for_remaining_original_time(): void
    {
        $user = User::factory()->create();
        $now = Carbon::now(Publicacion::TIMEZONE);
        $captureDate = $now->copy()->subDays(6);
        $firstApproval = $captureDate->copy()->addHours(2);
        $originalExpiration = $firstApproval->copy()->addDays(14); // Le restan 8 días de vigencia

        $reporte = Publicacion::factory()->create([
            'user_id' => $user->id,
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_captura' => $captureDate,
            'fecha_primera_aprobacion' => $firstApproval,
            'fecha_vencimiento' => $originalExpiration,
            'descripcion' => 'Descripción adecuada con longitud suficiente para satisfacer validaciones.',
            'fecha_hallazgo' => $captureDate->format('Y-m-d'),
        ]);
        $this->attachPhoto($reporte);

        // Edición por parte del autor
        $response = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Nombre Actualizado',
            'descripcion' => 'Descripción actualizada con longitud reglamentaria mayor a cincuenta caracteres.',
            'fecha_hallazgo' => $captureDate->format('Y-m-d'),
            'area_zona' => 'Canchas',
            'ubicacion_especifica' => 'Gradas oriente',
        ]);

        $response->assertRedirect(route('reportes.mis-reportes'));

        $reporte->refresh();
        $this->assertEquals(Publicacion::ESTADO_PENDIENTE, $reporte->estado);
        $this->assertFalse((bool) $reporte->disponible);

        // Simulación controlada de reaprobación administrativa antes de vencer
        $reporte->estado = Publicacion::ESTADO_APROBADA;
        $reporte->disponible = true;
        $reporte->save();

        // La fecha de vencimiento NO se extendió otros 14 días: expira en la fecha original
        $this->assertEquals($originalExpiration->toDateTimeString(), $reporte->fecha_vencimiento->toDateTimeString());
        $this->assertTrue($reporte->isDisponible());
        $this->assertEquals('Vence el ' . $originalExpiration->format('d/m/Y'), $reporte->vigencia_texto);
    }

    /**
     * CA-29: Si vence mientras está en corrección o pendiente de aprobación, no regresa al muro al aprobarse.
     */
    public function test_expired_report_during_pending_does_not_return_to_muro_even_if_approved(): void
    {
        $user = User::factory()->create();
        $firstApproval = Carbon::now(Publicacion::TIMEZONE)->subDays(20);
        $expirationDate = $firstApproval->copy()->addDays(14); // Expiró hace 6 días

        $reporte = Publicacion::factory()->create([
            'user_id' => $user->id,
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'disponible' => false,
            'fecha_captura' => $firstApproval->copy()->subDay(),
            'fecha_primera_aprobacion' => $firstApproval,
            'fecha_vencimiento' => $expirationDate,
            'descripcion' => 'Descripción de objeto con vigencia vencida durante el periodo de corrección.',
            'fecha_hallazgo' => $firstApproval->format('Y-m-d'),
        ]);
        $this->attachPhoto($reporte);

        // Se intenta marcar como 'Aprobada'
        $reporte->estado = Publicacion::ESTADO_APROBADA;
        $reporte->disponible = true;
        $reporte->save();

        // Por estar expirado (now >= fecha_vencimiento), isDisponible() evalúa false (CA-29, CT-10)
        $this->assertTrue($reporte->isExpirado());
        $this->assertFalse($reporte->isDisponible());

        // La ruta del muro no lo muestra
        $resMuro = $this->get(route('muro'));
        $resMuro->assertDontSee($reporte->nombre);
    }

    /**
     * CA-30: En caso de error de validación, conserva los datos y fotografías guardados en la BD sin alteraciones.
     */
    public function test_validation_errors_preserve_existing_saved_data_and_photos(): void
    {
        $user = User::factory()->create();
        $originalDesc = 'Descripción previa que debe preservarse intacta si falla la validación en el reenvío.';

        $reporte = Publicacion::factory()->requiereCorreccion()->create([
            'user_id' => $user->id,
            'nombre' => 'Llaves con Llavero Metálico',
            'descripcion' => $originalDesc,
            'fecha_hallazgo' => Carbon::now(Publicacion::TIMEZONE)->format('Y-m-d'),
        ]);
        $foto = $this->attachPhoto($reporte);

        // Envío inválido: descripción vacía y archivo inválido
        $response = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Nombre Nuevo Inválido',
            'descripcion' => '', // Inválido (< 50 caracteres)
            'fecha_hallazgo' => Carbon::now(Publicacion::TIMEZONE)->format('Y-m-d'),
            'area_zona' => 'Edificio C',
            'ubicacion_especifica' => 'Entrada',
            'eliminar_fotos' => [$foto->id], // Intenta eliminar la única foto (quedaría 0)
        ]);

        $response->assertSessionHasErrors(['descripcion', 'fotografias']);

        $reporte->refresh();

        // Los datos y fotos previos se conservan exactamente iguales en la BD
        $this->assertEquals('Llaves con Llavero Metálico', $reporte->nombre);
        $this->assertEquals($originalDesc, $reporte->descripcion);
        $this->assertEquals(Publicacion::ESTADO_REQUIERE_CORRECCION, $reporte->estado);
        $this->assertDatabaseHas('fotografias', ['id' => $foto->id]);
    }
}
