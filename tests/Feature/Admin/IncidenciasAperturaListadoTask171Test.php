<?php

namespace Tests\Feature\Admin;

use App\Models\Devolucion;
use App\Models\Incidencia;
use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncidenciasAperturaListadoTask171Test extends TestCase
{
    use RefreshDatabase;

    private function crearAdmin(): User
    {
        return User::factory()->create([
            'name' => 'Bibliotecaria Admin TecNM',
            'email' => 'admin.incidencias@merida.tecnm.mx',
            'role' => User::ROLE_ADMIN,
        ]);
    }

    private function crearAutor(string $nombre = 'Estudiante Autor', string $email = 'autor.inc@merida.tecnm.mx'): User
    {
        return User::factory()->create([
            'name' => $nombre,
            'email' => $email,
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);
    }

    private function crearPublicacionDevuelta(User $autor, array $overrides = []): Publicacion
    {
        return Publicacion::create(array_merge([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261107-0401',
            'titulo' => 'Calculadora Científica Casio FX-991EX',
            'nombre' => 'Calculadora Científica Casio FX-991EX',
            'categoria' => 'Electrónicos',
            'descripcion' => 'Calculadora negra con funda protectora gris encontrada en el aula F5.',
            'area_zona' => 'Edificio F',
            'ubicacion_especifica' => 'Aula F5',
            'fecha_hallazgo' => '2026-11-05',
            'hora_hallazgo' => '10:30:00',
            'estado' => Publicacion::ESTADO_DEVUELTO,
            'disponible' => false,
            'estado_devolucion' => 'Devuelto',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_captura' => '2026-11-05 11:00:00',
            'fecha_envio_revision' => '2026-11-05 11:05:00',
            'fecha_primera_aprobacion' => '2026-11-05 12:00:00',
            'fecha_aprobacion' => '2026-11-05 12:00:00',
            'fecha_vencimiento' => now()->addDays(12),
        ], $overrides));
    }

    /**
     * El selector únicamente muestra objetos con estado «Devuelto»,
     * oculta otros estados, impide en el backend registrar sobre objetos no elegibles
     * y muestra aviso si no existen objetos devueltos.
     */
    public function test_selector_solo_muestra_objetos_devueltos_y_backend_rechaza_otros_estados(): void
    {
        $admin = $this->crearAdmin();
        $autor = $this->crearAutor();

        // Sin objetos devueltos: muestra el mensaje informativo
        $respVacio = $this->actingAs($admin)->get(route('admin.incidencias.create'));
        $respVacio->assertOk();
        $respVacio->assertSee('No hay objetos disponibles para registrar incidencias');

        // Crear objetos en estados no elegibles y uno elegible («Devuelto»)
        $pubAprobada = $this->crearPublicacionDevuelta($autor, [
            'folio' => 'OBJ-20261107-0101',
            'nombre' => 'Llaves con Llavero Azul Disponible',
            'titulo' => 'Llaves con Llavero Azul Disponible',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
        ]);

        $this->crearPublicacionDevuelta($autor, [
            'folio' => 'OBJ-20261107-0102',
            'nombre' => 'Gorra Negra Pendiente',
            'titulo' => 'Gorra Negra Pendiente',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'disponible' => false,
        ]);

        $this->crearPublicacionDevuelta($autor, [
            'folio' => 'OBJ-20261107-0103',
            'nombre' => 'Cuaderno Espiral Cancelado',
            'titulo' => 'Cuaderno Espiral Cancelado',
            'estado' => Publicacion::ESTADO_CANCELADO,
            'disponible' => false,
        ]);

        $this->crearPublicacionDevuelta($autor, [
            'folio' => 'OBJ-20261107-0104',
            'nombre' => 'Reloj Digital Casio Devuelto',
            'titulo' => 'Reloj Digital Casio Devuelto',
            'estado' => Publicacion::ESTADO_DEVUELTO,
            'disponible' => false,
        ]);

        $respSelector = $this->actingAs($admin)->get(route('admin.incidencias.create'));
        $respSelector->assertOk();
        $respSelector->assertSee('OBJ-20261107-0104');
        $respSelector->assertSee('Reloj Digital Casio Devuelto');
        $respSelector->assertDontSee('Llaves con Llavero Azul Disponible');
        $respSelector->assertDontSee('Gorra Negra Pendiente');
        $respSelector->assertDontSee('Cuaderno Espiral Cancelado');

        // Intentar forzar en el backend un objeto no elegible (estado != Devuelto)
        $respRechazo = $this->actingAs($admin)
            ->from(route('admin.incidencias.create'))
            ->post(route('admin.incidencias.store'), [
                'publicacion_id' => $pubAprobada->id,
                'motivo' => 'Intento manual sobre un objeto que no está devuelto.',
            ]);

        $respRechazo->assertRedirect(route('admin.incidencias.create'));
        $respRechazo->assertSessionHasErrors('publicacion_id');
        $this->assertSame(0, Incidencia::count());
    }

    /**
     * No permite abrir dos incidencias del mismo objeto (se retira del selector tras abrir la primera
     * y el backend bloquea cualquier intento de registrar una segunda incidencia para el mismo objeto).
     */
    public function test_no_permite_abrir_dos_incidencias_del_mismo_objeto(): void
    {
        $admin = $this->crearAdmin();
        $autor = $this->crearAutor();

        $publicacion = $this->crearPublicacionDevuelta($autor, [
            'folio' => 'OBJ-20261107-0201',
            'nombre' => 'Mochila Negra Samsonite Devuelta',
            'titulo' => 'Mochila Negra Samsonite Devuelta',
        ]);

        // Abrir primera incidencia exitosamente
        $resp1 = $this->actingAs($admin)->post(route('admin.incidencias.store'), [
            'publicacion_id' => $publicacion->id,
            'motivo' => 'Primera duda sobre la entrega de la mochila negra.',
        ]);

        $incidencia = Incidencia::first();
        $this->assertNotNull($incidencia);
        $resp1->assertRedirect(route('admin.incidencias.show', $incidencia->id));
        $this->assertSame(1, Incidencia::count());

        // El objeto ya no aparece en el selector de apertura de incidencias
        $respCreate = $this->actingAs($admin)->get(route('admin.incidencias.create'));
        $respCreate->assertOk();
        $respCreate->assertDontSee('Mochila Negra Samsonite Devuelta');

        // Intentar abrir una segunda incidencia para el mismo objeto mediante POST directo
        $resp2 = $this->actingAs($admin)
            ->from(route('admin.incidencias.create'))
            ->post(route('admin.incidencias.store'), [
                'publicacion_id' => $publicacion->id,
                'motivo' => 'Intento de abrir una segunda incidencia sobre el mismo objeto.',
            ]);

        $resp2->assertRedirect(route('admin.incidencias.create'));
        $resp2->assertSessionHasErrors('publicacion_id');
        $this->assertSame(1, Incidencia::count());
    }

    /**
     * Un administrador abre una incidencia sobre un objeto «Devuelto»,
     * vinculándola con el evento de devolución correcto (incluso cuando existen varios),
     * registrando estado «Abierta», fecha, responsable y conservando intactas las evidencias.
     */
    public function test_admin_abre_incidencia_vinculada_a_reporte_devuelto_y_evento_de_devolucion_correcto_conservando_evidencias(): void
    {
        Storage::fake('local');

        $admin = $this->crearAdmin();
        $autor = $this->crearAutor();
        $publicacion = $this->crearPublicacionDevuelta($autor);

        $evidencia1 = UploadedFile::fake()->create('evidencia_entrega_1.jpg', 110, 'image/jpeg')
            ->store("devoluciones/{$publicacion->id}", 'local');
        $evidencia2 = UploadedFile::fake()->create('evidencia_entrega_2.jpg', 120, 'image/jpeg')
            ->store("devoluciones/{$publicacion->id}", 'local');

        $devolucion1 = Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Primer Receptor Anterior',
            'receptor_identificador' => 'le22080111@merida.tecnm.mx',
            'fecha_entrega' => '2026-11-05',
            'hora_entrega' => '15:00:00',
            'fecha_captura' => '2026-11-05 15:10:00',
            'explicacion_propiedad' => 'Primera entrega previa.',
            'fotografia_ruta' => $evidencia1,
            'fotografia_nombre_original' => 'evidencia_entrega_1.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 112640,
            'estado' => Devolucion::ESTADO_VERIFICADA,
            'admin_id' => $admin->id,
            'fecha_resolucion' => '2026-11-05 16:00:00',
        ]);

        $devolucion2 = Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Carlos Eduardo Méndez',
            'receptor_identificador' => 'le22080999@merida.tecnm.mx',
            'fecha_entrega' => '2026-11-06',
            'hora_entrega' => '13:15:00',
            'fecha_captura' => '2026-11-06 13:20:00',
            'explicacion_propiedad' => 'Mostró su número de control grabado en el reverso.',
            'fotografia_ruta' => $evidencia2,
            'fotografia_nombre_original' => 'evidencia_entrega_2.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 122880,
            'estado' => Devolucion::ESTADO_VERIFICADA,
            'admin_id' => $admin->id,
            'fecha_resolucion' => '2026-11-06 14:00:00',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.incidencias.store', $publicacion->id), [
            'devolucion_id' => $devolucion2->id,
            'motivo' => 'Se recibió una reclamación presencial de otro estudiante que asegura ser el dueño original de la calculadora.',
        ]);

        $incidencia = Incidencia::first();
        $this->assertNotNull($incidencia);

        $response->assertRedirect(route('admin.incidencias.show', $incidencia->id));
        $response->assertSessionHas('status');

        $this->assertSame($publicacion->id, $incidencia->publicacion_id);
        $this->assertSame($devolucion2->id, $incidencia->devolucion_id);
        $this->assertSame($admin->id, $incidencia->user_id);
        $this->assertSame(Incidencia::ESTADO_ABIERTA, $incidencia->estado);
        $this->assertNotNull($incidencia->fecha_apertura);
        $this->assertStringContainsString('reclamación presencial', $incidencia->motivo);

        // Las evidencias y antecedentes de ambas devoluciones permanecen intactos
        $devolucion1->refresh();
        $devolucion2->refresh();
        $this->assertSame($evidencia1, $devolucion1->fotografia_ruta);
        $this->assertSame($evidencia2, $devolucion2->fotografia_ruta);
        Storage::disk('local')->assertExists($evidencia1);
        Storage::disk('local')->assertExists($evidencia2);

        // El historial del objeto se conserva y registra el evento de incidencia
        $publicacion->refresh();
        $this->assertSame(Publicacion::ESTADO_DEVUELTO, $publicacion->estado);
        $eventos = $publicacion->getEventosHistorial();
        $eventoIncidencia = $eventos->firstWhere('tipo_slug', 'incidencia');
        $this->assertNotNull($eventoIncidencia);
        $this->assertSame('Incidencia', $eventoIncidencia['tipo']);
    }

    /**
     * No se aceptan motivos vacíos ni compuestos únicamente por espacios.
     */
    public function test_rechaza_apertura_de_incidencia_con_motivo_vacio_o_solo_espacios(): void
    {
        $admin = $this->crearAdmin();
        $autor = $this->crearAutor();
        $publicacion = $this->crearPublicacionDevuelta($autor);

        $respVacio = $this->actingAs($admin)->from(route('admin.incidencias.create', $publicacion->id))
            ->post(route('admin.incidencias.store', $publicacion->id), [
                'motivo' => '',
            ]);

        $respVacio->assertRedirect(route('admin.incidencias.create', $publicacion->id));
        $respVacio->assertSessionHasErrors('motivo');
        $this->assertSame(0, Incidencia::count());

        $respEspacios = $this->actingAs($admin)->from(route('admin.incidencias.create', $publicacion->id))
            ->post(route('admin.incidencias.store', $publicacion->id), [
                'motivo' => '       ',
            ]);

        $respEspacios->assertRedirect(route('admin.incidencias.create', $publicacion->id));
        $respEspacios->assertSessionHasErrors('motivo');
        $this->assertSame(0, Incidencia::count());
    }

    /**
     * Listado administrativo, detalle, seguimiento autorizado
     * del autor y bloqueo de acceso a usuarios no autorizados.
     */
    public function test_listado_y_detalle_administrativo_y_restricciones_de_acceso_por_rol(): void
    {
        $admin = $this->crearAdmin();
        $autor = $this->crearAutor();
        $otroUsuario = $this->crearAutor('Otro Estudiante', 'otro.est@merida.tecnm.mx');

        $publicacion = $this->crearPublicacionDevuelta($autor, [
            'folio' => 'OBJ-20261107-0402',
            'nombre' => 'Termo Metálico Azul Yeti',
            'titulo' => 'Termo Metálico Azul Yeti',
        ]);

        $devolucion = Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Receptor Privado Confidencial',
            'receptor_identificador' => 'le22089999@merida.tecnm.mx',
            'fecha_entrega' => '2026-11-06',
            'hora_entrega' => '11:00:00',
            'fecha_captura' => '2026-11-06 11:05:00',
            'explicacion_propiedad' => 'Explicación privada.',
            'fotografia_ruta' => 'devoluciones/ficticia.jpg',
            'fotografia_nombre_original' => 'ficticia.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 50000,
            'estado' => Devolucion::ESTADO_VERIFICADA,
            'admin_id' => $admin->id,
            'fecha_resolucion' => '2026-11-06 12:00:00',
        ]);

        $this->actingAs($admin)->post(route('admin.incidencias.store', $publicacion->id), [
            'devolucion_id' => $devolucion->id,
            'motivo' => 'Duda sobre coincidencia del grabado en la base del termo entregado.',
        ]);

        $incidencia = Incidencia::firstOrFail();

        // 1. El administrador consulta el listado administrativo
        $respIndex = $this->actingAs($admin)->get(route('admin.incidencias.index'));
        $respIndex->assertOk();
        $respIndex->assertSee('OBJ-20261107-0402');
        $respIndex->assertSee('Termo Metálico Azul Yeti');
        $respIndex->assertSee('Duda sobre coincidencia del grabado en la base del termo entregado.');
        $respIndex->assertSee($incidencia->fecha_apertura_texto);
        $respIndex->assertSee('Abierta');
        $respIndex->assertSee(route('admin.incidencias.show', $incidencia->id), false);

        // 2. El administrador consulta el detalle de la incidencia
        $respShow = $this->actingAs($admin)->get(route('admin.incidencias.show', $incidencia->id));
        $respShow->assertOk();
        $respShow->assertSee("Incidencia #{$incidencia->id}");
        $respShow->assertSee('OBJ-20261107-0402');
        $respShow->assertSee('Termo Metálico Azul Yeti');
        $respShow->assertSee('Duda sobre coincidencia del grabado en la base del termo entregado.');
        $respShow->assertSee($admin->name);
        $respShow->assertSee($incidencia->fecha_apertura_texto);
        $respShow->assertSee('Abierta');
        $respShow->assertSee("Devolución #{$devolucion->id}");

        // 3. Un usuario general (autor u otro) no puede abrir incidencias ni acceder al listado o detalle administrativo
        $this->actingAs($autor)->get(route('admin.incidencias.index'))->assertForbidden();
        $this->actingAs($autor)->get(route('admin.incidencias.create'))->assertForbidden();
        $this->actingAs($autor)->get(route('admin.incidencias.show', $incidencia->id))->assertForbidden();
        $this->actingAs($autor)->post(route('admin.incidencias.store'), [
            'publicacion_id' => $publicacion->id,
            'motivo' => 'Intento de usuario general',
        ])->assertForbidden();

        // 4. El autor únicamente visualiza el seguimiento autorizado en Mis reportes y detalle de su reporte
        $respMisReportes = $this->actingAs($autor)->get(route('reportes.mis-reportes'));
        $respMisReportes->assertOk();
        $respMisReportes->assertSee('OBJ-20261107-0402');
        $respMisReportes->assertSee('Termo Metálico Azul Yeti');
        $respMisReportes->assertSee('Incidencia abierta en revisión administrativa');
        $respMisReportes->assertDontSee('Duda sobre coincidencia del grabado en la base del termo entregado.');

        $respDetalleAutor = $this->actingAs($autor)->get(route('reportes.show', $publicacion->id));
        $respDetalleAutor->assertOk();
        $respDetalleAutor->assertSee('Seguimiento autorizado: Incidencia abierta en revisión administrativa');
        $respDetalleAutor->assertSee('Estado de seguimiento: Abierta');
        $respDetalleAutor->assertDontSee('Receptor Privado Confidencial');
        $respDetalleAutor->assertDontSee('Duda sobre coincidencia del grabado en la base del termo entregado.');

        // 5. Otro usuario no puede consultar incidencias ni reportes ajenos
        $this->actingAs($otroUsuario)->get(route('reportes.show', $publicacion->id))->assertForbidden();
        $this->actingAs($otroUsuario)->get(route('admin.incidencias.show', $incidencia->id))->assertForbidden();
    }

    /**
     * El objeto permanece fuera del muro público y del detalle general,
     * sin cambiar a «Disponible» ni presumir culpabilidad del receptor.
     */
    public function test_objeto_con_incidencia_permanece_fuera_del_muro_y_detalle_publico_sin_presumir_culpabilidad(): void
    {
        $admin = $this->crearAdmin();
        $autor = $this->crearAutor();

        $publicacion = $this->crearPublicacionDevuelta($autor, [
            'folio' => 'OBJ-20261107-0403',
            'nombre' => 'Audífonos Sony WH-1000XM4',
            'titulo' => 'Audífonos Sony WH-1000XM4',
        ]);

        $devolucion = Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Mariana López Vega',
            'receptor_identificador' => 'le22081111@merida.tecnm.mx',
            'fecha_entrega' => '2026-11-06',
            'hora_entrega' => '15:00:00',
            'fecha_captura' => '2026-11-06 15:10:00',
            'explicacion_propiedad' => 'Mostró el empaque con el número de serie.',
            'fotografia_ruta' => 'devoluciones/audifonos.jpg',
            'fotografia_nombre_original' => 'audifonos.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 102400,
            'estado' => Devolucion::ESTADO_VERIFICADA,
            'admin_id' => $admin->id,
            'fecha_resolucion' => '2026-11-06 16:00:00',
        ]);

        $this->actingAs($admin)->post(route('admin.devoluciones.incidencias.store', $devolucion->id), [
            'motivo' => 'Se requiere verificar el número de serie mostrado en la fotografía antes de cerrar la aclaración.',
        ]);

        $publicacion->refresh();
        $devolucion->refresh();
        $incidencia = Incidencia::firstOrFail();

        $this->assertFalse((bool) $publicacion->disponible);
        $this->assertFalse($publicacion->isDisponible());
        $this->assertSame(Publicacion::ESTADO_DEVUELTO, $publicacion->estado);
        $this->assertTrue((bool) $publicacion->incidencia_abierta);
        $this->assertTrue((bool) $publicacion->tiene_incidencia);
        $this->assertSame('Mariana López Vega', $devolucion->receptor_nombre);

        $respShowAdmin = $this->actingAs($admin)->get(route('admin.incidencias.show', $incidencia->id));
        $respShowAdmin->assertOk();
        $respShowAdmin->assertSee('Su apertura no señala automáticamente al receptor como responsable de una entrega indebida ni confirma una irregularidad.');

        auth()->logout();
        $respMuro = $this->get(route('muro'));
        $respMuro->assertOk();
        $respMuro->assertDontSee('Audífonos Sony WH-1000XM4');
        $respMuro->assertDontSee('Mariana López Vega');

        $this->get(route('publicaciones.show', $publicacion->id))
            ->assertRedirect(route('muro'));
    }
}

