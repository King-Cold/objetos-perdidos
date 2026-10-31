<?php

namespace Tests\Feature\Admin;

use App\Models\DecisionModeracion;
use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ModeracionSolicitudCorreccionTask073Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Prueba 1: Solicitud válida realizada por administrador.
     * Requerimiento 1 y Funcionalidad principal:
     * - El administrador puede enviar una solicitud de corrección válida.
     * - Se registra con éxito y redirige adecuadamente.
     */
    public function test_solicitud_valida_realizada_por_administrador(): void
    {
        $admin = User::factory()->admin()->create();
        $autor = User::factory()->create();

        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261031-1001',
            'nombre' => 'Calculadora Científica',
            'descripcion' => 'Calculadora científica gris olvidada en aula B-12',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.moderacion.solicitar-correccion', $reporte->id), [
            'motivo' => 'Por favor detalla si la calculadora incluye tapa protectora con número de serie.',
            'lock_version' => 1,
            'updated_at_token' => $reporte->updated_at?->timestamp,
        ]);

        $response->assertRedirect(route('admin.moderacion.index'));
        $response->assertSessionHas('status');

        $reporteActualizado = $reporte->fresh();
        $this->assertEquals(Publicacion::ESTADO_CORRECCION_SOLICITADA, $reporteActualizado->estado);
        $this->assertEquals('Por favor detalla si la calculadora incluye tapa protectora con número de serie.', $reporteActualizado->observaciones);
        $this->assertFalse((bool) $reporteActualizado->disponible);
    }

    /**
     * Prueba 2: Acceso denegado a usuarios generales y visitantes.
     * Requerimiento de Seguridad: Exclusivo para Administrador / Bibliotecario.
     */
    public function test_acceso_denegado_a_usuarios_generales(): void
    {
        $userGeneral = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $reporte = Publicacion::create([
            'user_id' => $userGeneral->id,
            'folio' => 'OBJ-20261031-1002',
            'nombre' => 'Audífonos Bluetooth',
            'descripcion' => 'Audífonos negros de diadema',
            'estado' => Publicacion::ESTADO_PENDIENTE,
        ]);

        // Usuario general recibe 403 Forbidden
        $responseGeneral = $this->actingAs($userGeneral)->post(route('admin.moderacion.solicitar-correccion', $reporte->id), [
            'motivo' => 'Motivo no autorizado',
            'lock_version' => 1,
        ]);
        $responseGeneral->assertStatus(403);
        $responseGeneral->assertSee('No tienes permiso para realizar esta acción');

        // Visitante sin autenticación redirigido al login
        auth()->logout();
        $responseGuest = $this->post(route('admin.moderacion.solicitar-correccion', $reporte->id), [
            'motivo' => 'Motivo no autorizado',
            'lock_version' => 1,
        ]);
        $responseGuest->assertRedirect(route('login'));
    }

    /**
     * Prueba 3: Motivo vacío rechazado.
     * Funcionalidad principal 3 y 4: El motivo debe ser obligatorio y no aceptar valores vacíos.
     */
    public function test_motivo_vacio_es_rechazado(): void
    {
        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261031-1003',
            'nombre' => 'Mochila Azul',
            'descripcion' => 'Mochila escolar azul rey',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
        ]);

        $response = $this->actingAs($admin)->from(route('admin.moderacion.show', $reporte->id))->post(route('admin.moderacion.solicitar-correccion', $reporte->id), [
            'motivo' => '',
            'lock_version' => 1,
        ]);

        $response->assertRedirect(route('admin.moderacion.show', $reporte->id));
        $response->assertSessionHasErrors('motivo');

        // El estado y observaciones permanecen sin cambio
        $reporteActualizado = $reporte->fresh();
        $this->assertEquals(Publicacion::ESTADO_PENDIENTE, $reporteActualizado->estado);
        $this->assertNull($reporteActualizado->observaciones);
        $this->assertEquals(0, DecisionModeracion::count());
    }

    /**
     * Prueba 4: Motivo compuesto únicamente por espacios en blanco rechazado.
     * Funcionalidad principal 4: Rechazar motivos compuestos solo por espacios.
     */
    public function test_motivo_compuesto_unicamente_por_espacios_es_rechazado(): void
    {
        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261031-1004',
            'nombre' => 'Termo Metálico',
            'descripcion' => 'Termo color acero inoxidable',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
        ]);

        $response = $this->actingAs($admin)->from(route('admin.moderacion.show', $reporte->id))->post(route('admin.moderacion.solicitar-correccion', $reporte->id), [
            'motivo' => "   \t \n   ",
            'lock_version' => 1,
        ]);

        $response->assertRedirect(route('admin.moderacion.show', $reporte->id));
        $response->assertSessionHasErrors('motivo');

        // Sin cambios en el reporte ni decisiones creadas
        $reporteActualizado = $reporte->fresh();
        $this->assertEquals(Publicacion::ESTADO_PENDIENTE, $reporteActualizado->estado);
        $this->assertEquals(0, DecisionModeracion::count());
    }

    /**
     * Prueba 5: Cancelar el modal no modifica datos y UI preparada.
     * Funcionalidad principal 7 y Diseño UI/UX:
     * - La vista incluye el modal con botón «Cancelar» y botón «Confirmar solicitud».
     * - Cancelar o no enviar datos mantiene intacto el reporte.
     */
    public function test_cancelar_modal_no_modifica_datos_y_elementos_en_vista(): void
    {
        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261031-1005',
            'nombre' => 'Suéter Escolar',
            'descripcion' => 'Suéter gris con logotipo institucional',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.moderacion.show', $reporte->id));

        $response->assertStatus(200);
        // Modal y formulario presentes con los elementos requeridos
        $response->assertSee('Solicitar corrección');
        $response->assertSee('OBJ-20261031-1005');
        $response->assertSee('name="motivo"', false);
        $response->assertSee('Cancelar');
        $response->assertSee('Confirmar solicitud');

        // Comprobación de integridad: el reporte continúa en su estado original
        $this->assertEquals(Publicacion::ESTADO_PENDIENTE, $reporte->fresh()->estado);
    }

    /**
     * Prueba 6: Cambio correcto a «Corrección solicitada».
     * Requerimiento: Al confirmar correctamente, cambiar el estado del reporte a «Corrección solicitada».
     */
    public function test_cambio_correcto_a_estado_correccion_solicitada(): void
    {
        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261031-1006',
            'nombre' => 'Libro de Álgebra Lineal',
            'descripcion' => 'Libro de texto con nombre en primera página',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.moderacion.solicitar-correccion', $reporte->id), [
            'motivo' => 'Cubre el nombre personal escrito en la primera página para proteger datos personales.',
            'lock_version' => 1,
        ]);

        $response->assertRedirect(route('admin.moderacion.index'));
        $this->assertEquals(Publicacion::ESTADO_CORRECCION_SOLICITADA, $reporte->fresh()->estado);
    }

    /**
     * Prueba 7: Reporte excluido del muro público y de la lista de pendientes.
     * Requerimiento: Mantener el reporte fuera del muro público y excluirlo de la lista de pendientes.
     */
    public function test_reporte_excluido_del_muro_y_de_lista_de_pendientes(): void
    {
        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261031-1007',
            'nombre' => 'Llaves con Llavero Metálico',
            'descripcion' => 'Juego de dos llaves con candado',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'disponible' => false,
            'version' => 1,
        ]);

        // Antes de la corrección, aparece en pendientes
        $this->assertTrue(Publicacion::pendientesRevision()->where('id', $reporte->id)->exists());

        $this->actingAs($admin)->post(route('admin.moderacion.solicitar-correccion', $reporte->id), [
            'motivo' => 'Especifique si las llaves corresponden a oficina o vehículo.',
            'lock_version' => 1,
        ]);

        $reporteActualizado = $reporte->fresh();

        // No está disponible para el muro
        $this->assertFalse((bool) $reporteActualizado->disponible);

        // Excluido de la lista de pendientes
        $this->assertFalse(Publicacion::pendientesRevision()->where('id', $reporte->id)->exists());

        // Al consultar index de moderación, no figura ningún reporte pendiente y muestra el estado vacío
        $this->flushSession();
        $indexResponse = $this->actingAs($admin)->get(route('admin.moderacion.index'));
        $indexResponse->assertSee('No hay reportes pendientes de revisión');
        $indexResponse->assertDontSee('OBJ-20261031-1007');
    }

    /**
     * Prueba 8: Conservación del folio original, contenido y fotografías.
     * Requerimiento: Conservar el folio original, contenido y fotografías.
     */
    public function test_conservacion_del_folio_original_contenido_y_fotografias(): void
    {
        $admin = User::factory()->admin()->create();
        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261031-8888',
            'nombre' => 'Reloj Digital Casio',
            'descripcion' => 'Reloj digital negro con correa deportiva',
            'categoria' => 'Accesorios',
            'area_zona' => 'Canchas deportivas',
            'ubicacion_especifica' => 'Gradas de la cancha 1',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
        ]);

        $foto = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/reloj.jpg',
            'nombre_original' => 'reloj.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 85000,
        ]);

        $this->actingAs($admin)->post(route('admin.moderacion.solicitar-correccion', $reporte->id), [
            'motivo' => 'Indica si el reloj tiene alarma programada para ayudar a su identificación.',
            'lock_version' => 1,
        ]);

        $reporteActualizado = $reporte->fresh();

        $this->assertEquals('OBJ-20261031-8888', $reporteActualizado->folio);
        $this->assertEquals('Reloj Digital Casio', $reporteActualizado->nombre);
        $this->assertEquals('Reloj digital negro con correa deportiva', $reporteActualizado->descripcion);
        $this->assertEquals('Accesorios', $reporteActualizado->categoria);
        $this->assertEquals('Canchas deportivas', $reporteActualizado->area_zona);
        $this->assertEquals('Gradas de la cancha 1', $reporteActualizado->ubicacion_especifica);
        $this->assertCount(1, $reporteActualizado->fotografias);
        $this->assertEquals($foto->id, $reporteActualizado->fotografias->first()->id);
    }

    /**
     * Prueba 9: Registro de responsable, fecha, hora y motivo.
     * Requerimiento: Registrar el administrador responsable, fecha, hora y motivo.
     */
    public function test_registro_de_responsable_fecha_y_motivo(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Lic. Roberto Martinez',
            'email' => 'roberto.martinez@merida.tecnm.mx',
        ]);

        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261031-1009',
            'nombre' => 'Memoria USB Kingston 64GB',
            'descripcion' => 'Memoria USB color rojo',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
        ]);

        $motivoEsperado = 'Por favor no publiques archivos personales en la descripción.';

        $this->actingAs($admin)->post(route('admin.moderacion.solicitar-correccion', $reporte->id), [
            'motivo' => $motivoEsperado,
            'lock_version' => 1,
        ]);

        $decision = DecisionModeracion::where('publicacion_id', $reporte->id)->first();

        $this->assertNotNull($decision);
        $this->assertEquals($admin->id, $decision->user_id);
        $this->assertEquals(DecisionModeracion::TIPO_CORRECCION_SOLICITADA, $decision->tipo_decision);
        $this->assertEquals($motivoEsperado, $decision->motivo);
        $this->assertNotNull($decision->fecha_decision);

        // En el detalle del reporte, las observaciones y la decisión son visibles
        $showResponse = $this->actingAs($admin)->get(route('admin.moderacion.show', $reporte->id));
        $showResponse->assertStatus(200);
        $showResponse->assertSee($motivoEsperado);
        $showResponse->assertSee('Lic. Roberto Martinez');
    }

    /**
     * Prueba 10: Conservación del historial de decisiones anteriores.
     * Requerimiento: Conservar el historial de decisiones anteriores a través de múltiples ciclos.
     */
    public function test_conservacion_del_historial_de_decisiones(): void
    {
        $admin1 = User::factory()->admin()->create(['name' => 'Admin Primero']);
        $admin2 = User::factory()->admin()->create(['name' => 'Admin Segundo']);

        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261031-1010',
            'nombre' => 'Carpeta Escolar',
            'descripcion' => 'Carpeta con apuntes de física',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
        ]);

        // Ciclo 1: Admin 1 solicita corrección
        $this->actingAs($admin1)->post(route('admin.moderacion.solicitar-correccion', $reporte->id), [
            'motivo' => 'Primera corrección: foto borrosa',
            'lock_version' => 1,
        ]);

        // Simular que el autor reenvía a revisión (HU-06 / reenviarARevision)
        $reporte->refresh();
        $reporte->reenviarARevision();

        // Ciclo 2: Admin 2 solicita otra corrección
        $this->actingAs($admin2)->post(route('admin.moderacion.solicitar-correccion', $reporte->id), [
            'motivo' => 'Segunda corrección: especificar materia',
            'lock_version' => $reporte->fresh()->version,
        ]);

        $decisiones = $reporte->fresh()->decisiones;

        $this->assertCount(2, $decisiones);
        $this->assertTrue($decisiones->contains('motivo', 'Primera corrección: foto borrosa'));
        $this->assertTrue($decisiones->contains('motivo', 'Segunda corrección: especificar materia'));
    }

    /**
     * Prueba 11: Rechazo de decisiones sobre versiones desactualizadas y mensaje exacto.
     * Requerimiento:
     * Si el contenido o estado cambió después de abrir el detalle, impedir confirmación y mostrar exactamente:
     * «El reporte cambió. Revisa su información actual antes de continuar».
     */
    public function test_rechazo_de_decisiones_sobre_versiones_desactualizadas(): void
    {
        $admin = User::factory()->admin()->create();

        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261031-1011',
            'nombre' => 'Lentes de Contacto',
            'descripcion' => 'Estuche con solución',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 2, // Versión actual en BD es 2
        ]);

        // 1. Simular envío con lock_version desactualizado (versión 1)
        $responseVersion = $this->actingAs($admin)->from(route('admin.moderacion.show', $reporte->id))->post(route('admin.moderacion.solicitar-correccion', $reporte->id), [
            'motivo' => 'Intento de corrección con versión vieja',
            'lock_version' => 1, // Desactualizado
        ]);

        $responseVersion->assertRedirect(route('admin.moderacion.show', $reporte->id));
        $responseVersion->assertSessionHas('error', 'El reporte cambió. Revisa su información actual antes de continuar.');

        // 2. Simular que el reporte ya no se encuentra en estado pendiente (ej. ya Aprobado o Cancelado)
        $reporte->update([
            'estado' => 'Aprobada',
            'version' => 3,
        ]);

        $responseEstado = $this->actingAs($admin)->from(route('admin.moderacion.show', $reporte->id))->post(route('admin.moderacion.solicitar-correccion', $reporte->id), [
            'motivo' => 'Intento sobre reporte ya no pendiente',
            'lock_version' => 3,
        ]);

        $responseEstado->assertRedirect(route('admin.moderacion.show', $reporte->id));
        $responseEstado->assertSessionHas('error', 'El reporte cambió. Revisa su información actual antes de continuar.');
    }

    /**
     * Prueba 12: Error de base de datos sin cambios parciales (Atomicidad y Transacción).
     * Requerimiento: Si ocurre un error, conservar el estado anterior sin cambios parciales.
     */
    public function test_error_de_base_de_datos_sin_cambios_parciales(): void
    {
        $admin = User::factory()->admin()->create();

        $reporte = Publicacion::create([
            'user_id' => User::factory()->create()->id,
            'folio' => 'OBJ-20261031-1012',
            'nombre' => 'Bicicleta de Montaña',
            'descripcion' => 'Bicicleta rodada 26 color verde',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
            'observaciones' => null,
        ]);

        // Simular excepción interceptando el modelo DecisionModeracion
        DecisionModeracion::saving(function () {
            throw new \RuntimeException('Fallo simulado de base de datos');
        });

        try {
            $this->actingAs($admin)->post(route('admin.moderacion.solicitar-correccion', $reporte->id), [
                'motivo' => 'Motivo que fallará por error forzado de DB',
                'lock_version' => 1,
            ]);
        } catch (\RuntimeException $e) {
            // Excepción esperada
        }

        // El reporte NO debe tener cambios parciales (rollback de transacción)
        $reporteActualizado = $reporte->fresh();
        $this->assertEquals(Publicacion::ESTADO_PENDIENTE, $reporteActualizado->estado);
        $this->assertNull($reporteActualizado->observaciones);
        $this->assertEquals(1, $reporteActualizado->version);
        $this->assertEquals(0, DecisionModeracion::count());
    }

    /**
     * Prueba 13: Compatibilidad y preparación con el flujo de HU-06.
     * Requerimiento:
     * - Conservar el folio original.
     * - Mantener accesible el motivo de corrección para edición.
     * - Soporte para el reenvío a revisión mediante reenviarARevision().
     */
    public function test_compatibilidad_con_flujo_de_hu06(): void
    {
        $admin = User::factory()->admin()->create();
        $autor = User::factory()->create();

        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261031-1013',
            'nombre' => 'Gafas Graduadas',
            'descripcion' => 'Gafas con armazón metálico negro',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 1,
        ]);

        // 1. Admin solicita corrección
        $this->actingAs($admin)->post(route('admin.moderacion.solicitar-correccion', $reporte->id), [
            'motivo' => 'Añade una foto donde se aprecie la graduación o estuche.',
            'lock_version' => 1,
        ]);

        $reporte->refresh();
        $this->assertEquals(Publicacion::ESTADO_CORRECCION_SOLICITADA, $reporte->estado);
        $this->assertEquals('Añade una foto donde se aprecie la graduación o estuche.', $reporte->observaciones);

        // 2. Simular flujo HU-06: el autor consulta el motivo y reenvía
        $this->assertEquals('Añade una foto donde se aprecie la graduación o estuche.', $reporte->observaciones);
        $this->assertEquals('OBJ-20261031-1013', $reporte->folio); // Mismo folio

        // Reenvío a revisión
        $reporte->reenviarARevision();

        $this->assertEquals(Publicacion::ESTADO_PENDIENTE, $reporte->fresh()->estado);
        $this->assertTrue(Publicacion::pendientesRevision()->where('id', $reporte->id)->exists());
    }
}
