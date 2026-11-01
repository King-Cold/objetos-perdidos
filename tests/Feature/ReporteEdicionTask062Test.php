<?php

namespace Tests\Feature;

use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteEdicionTask062Test extends TestCase
{
    use RefreshDatabase;

    /**
     * CA-09: El formulario de edición está disponible para Pendiente, Requiere corrección y Aprobada disponible.
     */
    public function test_author_can_access_edit_form_when_report_is_in_allowed_states(): void
    {
        $user = User::factory()->create();

        // 1. Pendiente de aprobación
        $repPendiente = Publicacion::factory()->pendiente()->create([
            'user_id' => $user->id,
            'nombre' => 'Laptop HP Gris',
        ]);

        $res1 = $this->actingAs($user)->get(route('reportes.edit', $repPendiente->id));
        $res1->assertStatus(200);
        $res1->assertSee('Editar Reporte de Hallazgo');
        $res1->assertSee('Laptop HP Gris');

        // 2. Requiere corrección
        $repCorreccion = Publicacion::factory()->requiereCorreccion()->create([
            'user_id' => $user->id,
            'nombre' => 'Cargador Tipo C',
            'motivo_correccion' => 'Falta foto de la marca',
        ]);

        $res2 = $this->actingAs($user)->get(route('reportes.edit', $repCorreccion->id));
        $res2->assertStatus(200);
        $res2->assertSee('Cargador Tipo C');
        $res2->assertSee('Falta foto de la marca');

        // 3. Aprobada disponible en el muro
        $repAprobado = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Mochila Negra Escolar',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => Carbon::now(Publicacion::TIMEZONE),
            'fecha_vencimiento' => Carbon::now(Publicacion::TIMEZONE)->addDays(14),
        ]);

        $res3 = $this->actingAs($user)->get(route('reportes.edit', $repAprobado->id));
        $res3->assertStatus(200);
        $res3->assertSee('Mochila Negra Escolar');
    }

    /**
     * CA-10: Impide la edición de reportes en estados no permitidos y redirige con advertencia.
     */
    public function test_edit_is_blocked_for_disallowed_states_and_redirects_with_warning(): void
    {
        $user = User::factory()->create();

        // 1. Cancelado
        $repCancelado = Publicacion::factory()->cancelado()->create([
            'user_id' => $user->id,
        ]);
        $res1 = $this->actingAs($user)->get(route('reportes.edit', $repCancelado->id));
        $res1->assertRedirect(route('reportes.mis-reportes'));
        $res1->assertSessionHas('warning', 'El estado del reporte cambió. Consulta su información actual antes de continuar');

        // 2. Devuelto
        $repDevuelto = Publicacion::factory()->create([
            'user_id' => $user->id,
            'estado' => Publicacion::ESTADO_DEVUELTO,
            'disponible' => false,
        ]);
        $res2 = $this->actingAs($user)->get(route('reportes.edit', $repDevuelto->id));
        $res2->assertRedirect(route('reportes.mis-reportes'));
        $res2->assertSessionHas('warning', 'El estado del reporte cambió. Consulta su información actual antes de continuar');

        // 3. Expirado / Vencido
        $repExpirado = Publicacion::factory()->expirado()->create([
            'user_id' => $user->id,
        ]);
        $res3 = $this->actingAs($user)->get(route('reportes.edit', $repExpirado->id));
        $res3->assertRedirect(route('reportes.mis-reportes'));
        $res3->assertSessionHas('warning', 'El estado del reporte cambió. Consulta su información actual antes de continuar');

        // 4. Confirmado en biblioteca (situación de resguardo oficial)
        $repResguardo = Publicacion::factory()->bajoResguardo()->create([
            'user_id' => $user->id,
            'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO,
        ]);
        $res4 = $this->actingAs($user)->get(route('reportes.edit', $repResguardo->id));
        $res4->assertRedirect(route('reportes.mis-reportes'));
        $res4->assertSessionHas('warning', 'El estado del reporte cambió. Consulta su información actual antes de continuar');
    }

    /**
     * CA-10, CT-01, CT-02: Un usuario general no puede acceder ni enviar edición de reportes ajenos.
     */
    public function test_another_user_cannot_access_or_submit_edit_form(): void
    {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();

        $reporte = Publicacion::factory()->pendiente()->create([
            'user_id' => $author->id,
            'nombre' => 'Calculadora Casio Autor',
        ]);

        // Intento de GET ajeno
        $resGet = $this->actingAs($otherUser)->get(route('reportes.edit', $reporte->id));
        $resGet->assertStatus(403);

        // Intento de PUT ajeno
        $resPut = $this->actingAs($otherUser)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Hackeado por otro usuario',
            'descripcion' => str_repeat('Intento de edición no autorizada sobre reporte ajeno. ', 2),
            'fecha_hallazgo' => $reporte->fecha_captura->format('Y-m-d'),
            'area_zona' => 'Edificio A',
            'ubicacion_especifica' => 'Aula 1',
        ]);
        $resPut->assertStatus(403);

        $this->assertDatabaseMissing('publicaciones', [
            'id' => $reporte->id,
            'nombre' => 'Hackeado por otro usuario',
        ]);
    }

    /**
     * CA-11: El formulario precarga la información capturada previamente.
     */
    public function test_edit_form_preloads_existing_report_data(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->pendiente()->create([
            'user_id' => $user->id,
            'nombre' => 'Audífonos Sony Inalámbricos',
            'categoria' => 'Electrónicos',
            'descripcion' => 'Audífonos de diadema color negro con almohadillas acolchadas en buen estado.',
            'area_zona' => 'Edificio G - Sistemas',
            'ubicacion_especifica' => 'Laboratorio L4 mesa central',
        ]);

        $foto = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/audifonos1.jpg',
            'nombre_original' => 'audifonos1.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
            'es_principal' => true,
        ]);

        $response = $this->actingAs($user)->get(route('reportes.edit', $reporte->id));

        $response->assertStatus(200);
        $response->assertSee('Audífonos Sony Inalámbricos');
        $response->assertSee('Audífonos de diadema color negro con almohadillas acolchadas en buen estado.');
        $response->assertSee('Edificio G - Sistemas');
        $response->assertSee('Laboratorio L4 mesa central');
        $response->assertSee($foto->url);
    }

    /**
     * CA-14: El autor puede modificar los campos permitidos y el reporte se actualiza correctamente.
     */
    public function test_author_can_update_allowed_fields_successfully(): void
    {
        $user = User::factory()->create();
        $captureDate = Carbon::now(Publicacion::TIMEZONE)->subDays(2);

        $reporte = Publicacion::factory()->requiereCorreccion()->create([
            'user_id' => $user->id,
            'nombre' => 'Nombre Original',
            'categoria' => 'Electrónicos',
            'descripcion' => 'Descripción original con longitud suficiente de más de cincuenta caracteres exactos.',
            'fecha_captura' => $captureDate,
            'fecha_hallazgo' => $captureDate->copy()->subDay(),
            'hora_hallazgo' => '10:00',
            'area_zona' => 'Edificio Antiguo',
            'ubicacion_especifica' => 'Lugar previo',
        ]);

        Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/test1.jpg',
            'nombre_original' => 'test1.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
            'es_principal' => true,
        ]);

        $newDate = $captureDate->copy()->subDays(2)->format('Y-m-d');

        $response = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Nombre Corregido y Actualizado',
            'descripcion' => 'Descripción corregida atendiendo observaciones de biblioteca con longitud adecuada.',
            'fecha_hallazgo' => $newDate,
            'hora_hallazgo' => '11:30',
            'area_zona' => 'Edificio B - Biblioteca',
            'ubicacion_especifica' => 'Mesa de lectura 5 planta baja',
        ]);

        $response->assertRedirect(route('reportes.mis-reportes'));

        $reporte->refresh();

        $this->assertEquals('Nombre Corregido y Actualizado', $reporte->nombre);
        $this->assertEquals('Descripción corregida atendiendo observaciones de biblioteca con longitud adecuada.', $reporte->descripcion);
        $this->assertEquals($newDate, $reporte->fecha_hallazgo->format('Y-m-d'));
        $this->assertEquals('11:30', substr((string) $reporte->hora_hallazgo, 0, 5));
        $this->assertEquals('Edificio B - Biblioteca', $reporte->area_zona);
        $this->assertEquals('Mesa de lectura 5 planta baja', $reporte->ubicacion_especifica);

        // Si estaba en 'Requiere corrección', regresa a 'Pendiente de aprobación'
        $this->assertEquals(Publicacion::ESTADO_PENDIENTE, $reporte->estado);
    }

    /**
     * CA-13: Folio, autoría, fecha de captura, primera aprobación y vigencia no pueden alterarse mediante edición.
     */
    public function test_protected_institutional_fields_cannot_be_overwritten_by_edit(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $originalFolio = 'OBJ-20261020-0001';
        $originalCapture = Carbon::now(Publicacion::TIMEZONE)->subDays(3);
        $originalFirstApproval = Carbon::now(Publicacion::TIMEZONE)->subDays(2);
        $originalExpiration = Carbon::now(Publicacion::TIMEZONE)->addDays(12);

        $reporte = Publicacion::factory()->create([
            'user_id' => $user->id,
            'folio' => $originalFolio,
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_captura' => $originalCapture,
            'fecha_primera_aprobacion' => $originalFirstApproval,
            'fecha_vencimiento' => $originalExpiration,
        ]);

        Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/test2.jpg',
            'nombre_original' => 'test2.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
            'es_principal' => true,
        ]);

        // Intento malicioso de alterar campos protegidos enviando inputs forjados
        $response = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'folio' => 'HACK-99999999-9999',
            'user_id' => $otherUser->id,
            'fecha_captura' => '2020-01-01 00:00:00',
            'fecha_primera_aprobacion' => '2020-01-01 00:00:00',
            'fecha_vencimiento' => '2030-01-01 00:00:00',
            'nombre' => 'Edición Válida del Objeto',
            'descripcion' => 'Esta es una descripción legítima con más de cincuenta caracteres para la prueba.',
            'fecha_hallazgo' => $originalCapture->format('Y-m-d'),
            'area_zona' => 'Edificio C',
            'ubicacion_especifica' => 'Aula C1',
        ]);

        $response->assertRedirect(route('reportes.mis-reportes'));

        $reporte->refresh();

        // Verificación estricta de inmutabilidad (CA-13)
        $this->assertEquals($originalFolio, $reporte->folio);
        $this->assertEquals($user->id, $reporte->user_id);
        $this->assertEquals($originalCapture->toDateTimeString(), $reporte->fecha_captura->toDateTimeString());
        $this->assertEquals($originalFirstApproval->toDateTimeString(), $reporte->fecha_primera_aprobacion->toDateTimeString());
        $this->assertEquals($originalExpiration->toDateTimeString(), $reporte->fecha_vencimiento->toDateTimeString());
    }

    /**
     * CA-12: Validación rechaza campos obligatorios vacíos o con solo espacios.
     */
    public function test_validation_rejects_empty_or_whitespace_only_fields(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->pendiente()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => '   ',
            'descripcion' => '   ',
            'fecha_hallazgo' => '',
            'area_zona' => '   ',
            'ubicacion_especifica' => '   ',
        ]);

        $response->assertSessionHasErrors(['nombre', 'descripcion', 'fecha_hallazgo', 'area_zona', 'ubicacion_especifica']);
    }

    /**
     * CA-12: Validación de descripción entre 50 y 200 caracteres exactos.
     */
    public function test_validation_enforces_description_length_between_50_and_200_chars(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->pendiente()->create([
            'user_id' => $user->id,
        ]);

        $captureDate = $reporte->fecha_captura->format('Y-m-d');

        // Menos de 50 caracteres (49 caracteres)
        $resShort = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Mouse USB',
            'descripcion' => str_repeat('a', 49),
            'fecha_hallazgo' => $captureDate,
            'area_zona' => 'Edificio G',
            'ubicacion_especifica' => 'Mesa 1',
        ]);
        $resShort->assertSessionHasErrors('descripcion');

        // Más de 200 caracteres (201 caracteres)
        $resLong = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Mouse USB',
            'descripcion' => str_repeat('a', 201),
            'fecha_hallazgo' => $captureDate,
            'area_zona' => 'Edificio G',
            'ubicacion_especifica' => 'Mesa 1',
        ]);
        $resLong->assertSessionHasErrors('descripcion');

        // Exactamente 50 caracteres
        $resValidMin = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Mouse USB',
            'descripcion' => str_repeat('a', 50),
            'fecha_hallazgo' => $captureDate,
            'area_zona' => 'Edificio G',
            'ubicacion_especifica' => 'Mesa 1',
        ]);
        $resValidMin->assertSessionDoesntHaveErrors('descripcion');

        // Exactamente 200 caracteres
        $resValidMax = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Mouse USB',
            'descripcion' => str_repeat('a', 200),
            'fecha_hallazgo' => $captureDate,
            'area_zona' => 'Edificio G',
            'ubicacion_especifica' => 'Mesa 1',
        ]);
        $resValidMax->assertSessionDoesntHaveErrors('descripcion');
    }

    /**
     * CA-15: La validación de la fecha de hallazgo toma como referencia la fecha de captura original.
     */
    public function test_hallazgo_date_validation_is_referenced_to_original_capture_date(): void
    {
        $user = User::factory()->create();

        // Supongamos que el reporte fue capturado hace 4 días (ej. 2026-10-25)
        $fechaCaptura = Carbon::now(Publicacion::TIMEZONE)->subDays(4);

        $reporte = Publicacion::factory()->pendiente()->create([
            'user_id' => $user->id,
            'fecha_captura' => $fechaCaptura,
        ]);

        $maxAllowed = $fechaCaptura->format('Y-m-d');
        $minAllowed = $fechaCaptura->copy()->subDays(7)->format('Y-m-d');

        // 1. Fecha posterior a la fecha de captura original (ej. ayer o hoy, aunque sea válida respecto a hoy)
        // Debe ser RECHAZADA porque es posterior a cuando se capturó originalmente el hallazgo (CA-15).
        $fechaPosteriorCaptura = $fechaCaptura->copy()->addDay()->format('Y-m-d');
        $resFuture = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Mochila Azul',
            'descripcion' => str_repeat('Mochila escolar de color azul marino con cierres dorados. ', 2),
            'fecha_hallazgo' => $fechaPosteriorCaptura,
            'area_zona' => 'Edificio G',
            'ubicacion_especifica' => 'Aula 2',
        ]);
        $resFuture->assertSessionHasErrors('fecha_hallazgo');

        // 2. Fecha anterior al límite de 7 días previos a la captura
        $fechaMuyVieja = $fechaCaptura->copy()->subDays(8)->format('Y-m-d');
        $resOld = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Mochila Azul',
            'descripcion' => str_repeat('Mochila escolar de color azul marino con cierres dorados. ', 2),
            'fecha_hallazgo' => $fechaMuyVieja,
            'area_zona' => 'Edificio G',
            'ubicacion_especifica' => 'Aula 2',
        ]);
        $resOld->assertSessionHasErrors('fecha_hallazgo');

        // 3. Fecha exactamente igual a la captura original
        $resExactCapture = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Mochila Azul',
            'descripcion' => str_repeat('Mochila escolar de color azul marino con cierres dorados. ', 2),
            'fecha_hallazgo' => $maxAllowed,
            'area_zona' => 'Edificio G',
            'ubicacion_especifica' => 'Aula 2',
        ]);
        $resExactCapture->assertSessionDoesntHaveErrors('fecha_hallazgo');

        // 4. Fecha límite de 7 días antes de la captura
        $resExactMin = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Mochila Azul',
            'descripcion' => str_repeat('Mochila escolar de color azul marino con cierres dorados. ', 2),
            'fecha_hallazgo' => $minAllowed,
            'area_zona' => 'Edificio G',
            'ubicacion_especifica' => 'Aula 2',
        ]);
        $resExactMin->assertSessionDoesntHaveErrors('fecha_hallazgo');
    }

    /**
     * CA-16: Si la fecha de hallazgo corresponde a la fecha de captura, la hora no puede superar la hora de captura.
     */
    public function test_hallazgo_time_on_capture_date_cannot_be_later_than_capture_time(): void
    {
        $user = User::factory()->create();

        // Capturado a las 14:30 de un día
        $fechaCaptura = Carbon::parse('2026-10-25 14:30:00', Publicacion::TIMEZONE);

        $reporte = Publicacion::factory()->pendiente()->create([
            'user_id' => $user->id,
            'fecha_captura' => $fechaCaptura,
        ]);

        $sameDay = $fechaCaptura->format('Y-m-d');

        // Hora posterior a 14:30 (ej. 15:00) -> Debe fallar (CA-16)
        $resLater = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Gafas de Sol',
            'descripcion' => str_repeat('Gafas de sol negras marca Ray-Ban encontradas en el campus. ', 2),
            'fecha_hallazgo' => $sameDay,
            'hora_hallazgo' => '15:00',
            'area_zona' => 'Cafetería',
            'ubicacion_especifica' => 'Mesa exterior',
        ]);
        $resLater->assertSessionHasErrors('hora_hallazgo');

        // Hora anterior o igual a 14:30 (ej. 12:15) -> Pasa
        $resEarlier = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Gafas de Sol',
            'descripcion' => str_repeat('Gafas de sol negras marca Ray-Ban encontradas en el campus. ', 2),
            'fecha_hallazgo' => $sameDay,
            'hora_hallazgo' => '12:15',
            'area_zona' => 'Cafetería',
            'ubicacion_especifica' => 'Mesa exterior',
        ]);
        $resEarlier->assertSessionDoesntHaveErrors('hora_hallazgo');
    }

    /**
     * CA-17: La hora de hallazgo es opcional y valida formato correcto si se captura.
     */
    public function test_hallazgo_time_is_optional_and_rejects_invalid_format(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->pendiente()->create([
            'user_id' => $user->id,
        ]);

        $captureDate = $reporte->fecha_captura->format('Y-m-d');

        // Vacía (null) -> Pasa
        $resEmpty = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Libro de Cálculo',
            'descripcion' => str_repeat('Libro de Cálculo Diferencial e Integral de Stewart pasta dura. ', 2),
            'fecha_hallazgo' => $captureDate,
            'hora_hallazgo' => '',
            'area_zona' => 'Biblioteca',
            'ubicacion_especifica' => 'Pasillo 3',
        ]);
        $resEmpty->assertSessionDoesntHaveErrors('hora_hallazgo');

        // Formato inválido -> Falla
        $resInvalid = $this->actingAs($user)->put(route('reportes.update', $reporte->id), [
            'nombre' => 'Libro de Cálculo',
            'descripcion' => str_repeat('Libro de Cálculo Diferencial e Integral de Stewart pasta dura. ', 2),
            'fecha_hallazgo' => $captureDate,
            'hora_hallazgo' => '25:99',
            'area_zona' => 'Biblioteca',
            'ubicacion_especifica' => 'Pasillo 3',
        ]);
        $resInvalid->assertSessionHasErrors('hora_hallazgo');
    }

    /**
     * CA-22 y CA-23: El formulario incluye botones de guardar y cancelar, y cancelar descarta cambios regresando a Mis Reportes.
     */
    public function test_edit_form_contains_action_buttons_and_cancel_link(): void
    {
        $user = User::factory()->create();
        $reporte = Publicacion::factory()->pendiente()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('reportes.edit', $reporte->id));

        $response->assertStatus(200);
        $response->assertSee('Guardar y enviar a revisión');
        $response->assertSee('Cancelar edición');
        $response->assertSee(route('reportes.mis-reportes'));
    }
}
