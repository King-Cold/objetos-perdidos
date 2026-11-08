<?php

namespace Tests\Feature\Admin;

use App\Models\Devolucion;
use App\Models\Incidencia;
use App\Models\IncidenciaObservacion;
use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncidenciasObservacionesRevisionTask172Test extends TestCase
{
    use RefreshDatabase;

    private function crearAdmin(): User
    {
        return User::factory()->create([
            'name' => 'Bibliotecario Revisor TecNM',
            'email' => 'revisor.inc@merida.tecnm.mx',
            'role' => User::ROLE_ADMIN,
        ]);
    }

    private function crearAutor(string $nombre = 'Estudiante Autor', string $email = 'autor.t2@merida.tecnm.mx'): User
    {
        return User::factory()->create([
            'name' => $nombre,
            'email' => $email,
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);
    }

    private function crearEscenarioConIncidencia(User $admin, User $autor, bool $conArchivoEvidencia = true): array
    {
        $publicacion = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261107-0501',
            'titulo' => 'Laptop Lenovo ThinkPad T14',
            'nombre' => 'Laptop Lenovo ThinkPad T14',
            'categoria' => 'Electrónicos',
            'descripcion' => 'Laptop negra con calcomanía del TecNM en la tapa.',
            'area_zona' => 'Centro de Cómputo',
            'ubicacion_especifica' => 'Laboratorio 2',
            'fecha_hallazgo' => '2026-11-05',
            'hora_hallazgo' => '09:00:00',
            'estado' => Publicacion::ESTADO_DEVUELTO,
            'disponible' => false,
            'estado_devolucion' => 'Devuelto',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
            'fecha_captura' => '2026-11-05 09:30:00',
            'fecha_envio_revision' => '2026-11-05 09:35:00',
            'fecha_primera_aprobacion' => '2026-11-05 10:00:00',
            'fecha_aprobacion' => '2026-11-05 10:00:00',
            'fecha_vencimiento' => now()->addDays(12),
            'incidencia_abierta' => true,
            'tiene_incidencia' => true,
        ]);

        $evidenciaPath = 'devoluciones/' . $publicacion->id . '/evidencia_laptop.jpg';
        if ($conArchivoEvidencia) {
            $evidenciaPath = UploadedFile::fake()->create('evidencia_laptop.jpg', 140, 'image/jpeg')
                ->store("devoluciones/{$publicacion->id}", 'local');
        }

        $devolucion = Devolucion::create([
            'publicacion_id' => $publicacion->id,
            'user_id' => $autor->id,
            'receptor_nombre' => 'Alejandro Pech Cámara',
            'receptor_identificador' => 'le22080555@merida.tecnm.mx',
            'fecha_entrega' => '2026-11-06',
            'hora_entrega' => '12:00:00',
            'fecha_captura' => '2026-11-06 12:10:00',
            'explicacion_propiedad' => 'Desbloqueó el equipo con su huella y mostró sesión institucional.',
            'fotografia_ruta' => $evidenciaPath,
            'fotografia_nombre_original' => 'evidencia_laptop.jpg',
            'fotografia_mime_type' => 'image/jpeg',
            'fotografia_tamano_bytes' => 143360,
            'estado' => Devolucion::ESTADO_VERIFICADA,
            'admin_id' => $admin->id,
            'fecha_resolucion' => '2026-11-06 13:00:00',
        ]);

        $incidencia = Incidencia::create([
            'publicacion_id' => $publicacion->id,
            'devolucion_id' => $devolucion->id,
            'user_id' => $admin->id,
            'motivo' => 'Se recibió consulta sobre el modelo exacto del equipo entregado.',
            'estado' => Incidencia::ESTADO_ABIERTA,
            'fecha_apertura' => now(),
        ]);

        return [$publicacion, $devolucion, $incidencia];
    }

    /**
     * CA-03: Observaciones del caso.
     * Permite registrar aclaraciones, contactos por medios externos y resultados obtenidos
     * identificando fecha y responsable de cada observación, y rechaza textos vacíos.
     */
    public function test_admin_puede_registrar_observaciones_contactos_externos_y_resultados_con_fecha_y_responsable(): void
    {
        Storage::fake('local');

        $admin = $this->crearAdmin();
        $autor = $this->crearAutor();
        [$publicacion, $devolucion, $incidencia] = $this->crearEscenarioConIncidencia($admin, $autor);

        // Rechaza observación vacía o solo espacios
        $respInvalida = $this->actingAs($admin)
            ->from(route('admin.incidencias.show', $incidencia->id))
            ->post(route('admin.incidencias.observaciones.store', $incidencia->id), [
                'tipo' => 'Aclaración',
                'descripcion' => '     ',
            ]);

        $respInvalida->assertRedirect(route('admin.incidencias.show', $incidencia->id));
        $respInvalida->assertSessionHasErrors('descripcion');
        $this->assertSame(0, IncidenciaObservacion::count());

        // Registrar contacto por medio externo y resultado obtenido
        $respValida = $this->actingAs($admin)->post(route('admin.incidencias.observaciones.store', $incidencia->id), [
            'tipo' => 'Contacto por medio externo',
            'medio_contacto' => 'Correo institucional y llamada telefónica',
            'descripcion' => 'Se contactó al autor y al receptor para cotejar el número de serie de la laptop.',
            'resultado_obtenido' => 'Ambos enviaron comprobante del número de serie que coincide con la factura.',
        ]);

        $respValida->assertRedirect(route('admin.incidencias.show', $incidencia->id));
        $this->assertSame(1, IncidenciaObservacion::count());

        $obs = IncidenciaObservacion::first();
        $this->assertSame($incidencia->id, $obs->incidencia_id);
        $this->assertSame($admin->id, $obs->user_id);
        $this->assertNotNull($obs->fecha_registro);

        // El detalle administrativo muestra la observación con fecha, responsable, medio y resultado
        $respShow = $this->actingAs($admin)->get(route('admin.incidencias.show', $incidencia->id));
        $respShow->assertOk();
        $respShow->assertSee('Contacto por medio externo');
        $respShow->assertSee('Correo institucional y llamada telefónica');
        $respShow->assertSee('Se contactó al autor y al receptor para cotejar el número de serie de la laptop.');
        $respShow->assertSee('Ambos enviaron comprobante del número de serie que coincide con la factura.');
        $respShow->assertSee($admin->name);
        $respShow->assertSee($obs->fecha_registro_texto);

        // Un usuario general no puede registrar observaciones
        $this->actingAs($autor)->post(route('admin.incidencias.observaciones.store', $incidencia->id), [
            'tipo' => 'Aclaración',
            'descripcion' => 'Intento no autorizado',
        ])->assertForbidden();
    }

    /**
     * CA-05: Entrega aclarada como correcta.
     * Si la revisión confirma que fue entregado a la persona correcta y la evidencia cumple HU-12,
     * aprueba la devolución y cierra la incidencia registrando motivo, fecha y responsable,
     * sin exigir recuperar el objeto ni recibirlo en biblioteca.
     */
    public function test_entrega_aclarada_como_correcta_aprueba_devolucion_y_cierra_incidencia_sin_exigir_recuperacion(): void
    {
        Storage::fake('local');

        $admin = $this->crearAdmin();
        $autor = $this->crearAutor();
        [$publicacion, $devolucion, $incidencia] = $this->crearEscenarioConIncidencia($admin, $autor, true);

        $response = $this->actingAs($admin)->post(route('admin.incidencias.confirmar-entrega-correcta', $incidencia->id), [
            'motivo' => 'Se verificó que la otra persona confundió su equipo; el receptor acreditó propiedad legítima y la evidencia cumple HU-12.',
        ]);

        $response->assertRedirect(route('admin.incidencias.show', $incidencia->id));
        $response->assertSessionHas('status');

        $incidencia->refresh();
        $devolucion->refresh();
        $publicacion->refresh();

        $this->assertSame(Incidencia::ESTADO_CERRADA, $incidencia->estado);
        $this->assertSame('Entrega aclarada como correcta', $incidencia->resultado);
        $this->assertSame($admin->id, $incidencia->cerrada_por_id);
        $this->assertSame($admin->id, $incidencia->responsable_revision_id);
        $this->assertNotNull($incidencia->fecha_cierre);

        $this->assertSame(Devolucion::ESTADO_VERIFICADA, $devolucion->estado);
        $this->assertSame(Publicacion::ESTADO_DEVUELTO, $publicacion->estado);
        $this->assertFalse((bool) $publicacion->disponible);
        $this->assertFalse((bool) $publicacion->incidencia_abierta);
        $this->assertNull($publicacion->recepcion);
    }

    /**
     * CA-05: Impide confirmar entrega correcta si la evidencia fotográfica no cumple HU-12.
     */
    public function test_impide_confirmar_entrega_correcta_si_falta_evidencia_de_hu12(): void
    {
        Storage::fake('local');

        $admin = $this->crearAdmin();
        $autor = $this->crearAutor();
        [$publicacion, $devolucion, $incidencia] = $this->crearEscenarioConIncidencia($admin, $autor, false);

        $response = $this->actingAs($admin)->post(route('admin.incidencias.confirmar-entrega-correcta', $incidencia->id), [
            'motivo' => 'Intento de cerrar sin archivo de evidencia válido.',
        ]);

        $response->assertRedirect(route('admin.incidencias.show', $incidencia->id));
        $response->assertSessionHas('error');

        $incidencia->refresh();
        $this->assertSame(Incidencia::ESTADO_ABIERTA, $incidencia->estado);
        $this->assertNull($incidencia->fecha_cierre);
    }

    /**
     * CA-06: Prueba incompleta (evidencia insuficiente sin sospecha).
     * Solicita la corrección de la devolución existente mediante HU-12, el seguimiento indica
     * «En espera de corrección», sin crear otra entrega ni devolver el objeto al estado «Disponible».
     */
    public function test_prueba_incompleta_solicita_correccion_por_hu12_sobre_misma_devolucion_sin_reactivar_publicacion(): void
    {
        Storage::fake('local');

        $admin = $this->crearAdmin();
        $autor = $this->crearAutor();
        [$publicacion, $devolucion, $incidencia] = $this->crearEscenarioConIncidencia($admin, $autor, true);

        $totalDevolucionesAntes = Devolucion::count();

        $response = $this->actingAs($admin)->post(route('admin.incidencias.solicitar-correccion', $incidencia->id), [
            'motivo' => 'La fotografía de la credencial está borrosa; se solicita subir una foto nítida y precisar el número de control.',
        ]);

        $response->assertRedirect(route('admin.incidencias.show', $incidencia->id));
        $response->assertSessionHas('status');

        $incidencia->refresh();
        $devolucion->refresh();
        $publicacion->refresh();

        // No se creó otra entrega
        $this->assertSame($totalDevolucionesAntes, Devolucion::count());

        // La misma devolución existente pasó a «Requiere corrección» (HU-12)
        $this->assertSame(Devolucion::ESTADO_REQUIERE_CORRECCION, $devolucion->estado);
        $this->assertStringContainsString('fotografía de la credencial está borrosa', (string) $devolucion->motivo_correccion);

        // La incidencia y el seguimiento para el autor indican «En espera de corrección»
        $this->assertSame(Incidencia::ESTADO_ESPERA_CORRECCION, $incidencia->estado);
        $this->assertSame('En espera de corrección', $incidencia->seguimiento_autorizado);
        $this->assertSame($admin->id, $incidencia->responsable_revision_id);
        $this->assertNotNull($incidencia->fecha_revision);

        // El objeto permanece fuera del muro y no cambia a «Disponible»
        $this->assertFalse((bool) $publicacion->disponible);
        $this->assertFalse($publicacion->isDisponible());
        $this->assertNotSame('Disponible', $publicacion->estado);

        // El autor visualiza «En espera de corrección» en su seguimiento autorizado y puede abrir la edición de la misma devolución (HU-12)
        $this->actingAs($autor)->get(route('reportes.show', $publicacion->id))
            ->assertOk()
            ->assertSee('En espera de corrección');

        $this->actingAs($autor)->get(route('devoluciones.edit', $devolucion->id))
            ->assertOk();
    }

    /**
     * CA-07: Entrega incorrecta confirmada.
     * Si se confirma que el receptor no era el dueño, registra esa conclusión y la solicitud de llevar
     * el objeto a biblioteca; pasa a «En espera de recuperación», sigue fuera del muro y la promesa
     * de devolverlo no se considera recuperación física.
     */
    public function test_entrega_incorrecta_confirmada_pasa_a_espera_de_recuperacion_y_promesa_no_es_recuperacion_fisica(): void
    {
        Storage::fake('local');

        $admin = $this->crearAdmin();
        $autor = $this->crearAutor();
        [$publicacion, $devolucion, $incidencia] = $this->crearEscenarioConIncidencia($admin, $autor, true);

        $response = $this->actingAs($admin)->post(route('admin.incidencias.confirmar-entrega-incorrecta', $incidencia->id), [
            'motivo' => 'El propietario real presentó la factura original con número de serie coincidente; el receptor admitió haber confundido su equipo.',
            'solicitud_recuperacion' => 'Se solicitó al receptor acudir a biblioteca institucional para entregar físicamente la laptop.',
        ]);

        $response->assertRedirect(route('admin.incidencias.show', $incidencia->id));
        $response->assertSessionHas('status');

        $incidencia->refresh();
        $publicacion->refresh();

        $this->assertSame(Incidencia::ESTADO_ESPERA_RECUPERACION, $incidencia->estado);
        $this->assertSame('En espera de recuperación', $incidencia->seguimiento_autorizado);
        $this->assertStringContainsString('propietario real presentó la factura', (string) $incidencia->motivo_resolucion);
        $this->assertStringContainsString('acudir a biblioteca institucional', (string) $incidencia->solicitud_recuperacion);
        $this->assertSame($admin->id, $incidencia->responsable_revision_id);
        $this->assertNotNull($incidencia->fecha_revision);
        $this->assertNull($incidencia->fecha_cierre);

        // Registrar una observación indicando que el receptor prometió devolver el objeto mañana
        $this->actingAs($admin)->post(route('admin.incidencias.observaciones.store', $incidencia->id), [
            'tipo' => 'Contacto por medio externo',
            'medio_contacto' => 'Llamada telefónica',
            'descripcion' => 'El receptor atendió la llamada y prometió llevar el objeto a biblioteca mañana a las 10:00 a. m.',
            'resultado_obtenido' => 'Promesa de entrega en biblioteca pendiente de confirmación física.',
        ]);

        $incidencia->refresh();
        $publicacion->refresh();
        $devolucion->refresh();

        // La promesa de devolverlo NO se considera recuperación física ni cierra la incidencia ni reactiva la publicación
        $this->assertSame(Incidencia::ESTADO_ESPERA_RECUPERACION, $incidencia->estado);
        $this->assertNull($incidencia->fecha_cierre);
        $this->assertFalse($publicacion->hasRecepcionFisicaConfirmada());
        $this->assertFalse((bool) $publicacion->disponible);
        $this->assertFalse($publicacion->isDisponible());

        // Se conserva el antecedente y la evidencia de la entrega anterior
        $this->assertSame('Alejandro Pech Cámara', $devolucion->receptor_nombre);
        Storage::disk('local')->assertExists($devolucion->fotografia_ruta);
    }
}

