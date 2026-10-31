<?php

namespace Tests\Feature\Admin;

use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeracionDetalleTask072Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Prueba 1: «Revisar» abre el reporte correcto.
     * Requerimiento 1 y 3: Abrir el detalle del reporte seleccionado garantizando correspondencia unívoca.
     */
    public function test_revisar_opens_correct_report(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $reporte1 = Publicacion::create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261031-0101',
            'nombre' => 'Laptop Dell Inspiron 15',
            'descripcion' => 'Laptop color plata olvidada en sala de cómputo',
            'estado' => Publicacion::ESTADO_PENDIENTE,
        ]);

        $reporte2 = Publicacion::create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261031-0202',
            'nombre' => 'Audífonos Sony Bluetooth',
            'descripcion' => 'Audífonos negros de diadema',
            'estado' => Publicacion::ESTADO_PENDIENTE,
        ]);

        // Simular que el administrador pulsa "Revisar" sobre el Reporte 1
        $response = $this->actingAs($admin)->get(route('admin.moderacion.show', $reporte1->id));

        $response->assertStatus(200);
        $response->assertSee('OBJ-20261031-0101');
        $response->assertSee('Laptop Dell Inspiron 15');

        // No debe mostrar la información del Reporte 2
        $response->assertDontSee('OBJ-20261031-0202');
        $response->assertDontSee('Audífonos Sony Bluetooth');
    }

    /**
     * Prueba 2: Los datos corresponden detalladamente al folio seleccionado.
     * Requerimiento 2: Folio, nombre, descripción, autor, fechas, hallazgo y ubicación.
     */
    public function test_displayed_data_corresponds_to_selected_folio(): void
    {
        $admin = User::factory()->admin()->create();

        $autor = User::factory()->create([
            'name' => 'Luis Fernando Gomez',
            'email' => 'luis.gomez@merida.tecnm.mx',
        ]);

        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261031-3333',
            'nombre' => 'Mochila Nike Negra con útiles escolares',
            'titulo' => 'Mochila Nike Negra con útiles escolares',
            'descripcion' => 'Mochila escolar de lona negra con libreta institucional y credencial TecNM en el bolsillo frontal.',
            'categoria' => 'Mochilas',
            'fecha_hallazgo' => Carbon::parse('2026-10-30'),
            'hora_hallazgo' => '11:45',
            'area_zona' => 'Edificio D - Aulas de Sistemas',
            'ubicacion_especifica' => 'Salón D-4, segunda fila banca 3',
            'fecha_captura' => Carbon::parse('2026-10-30 12:00:00'),
            'fecha_envio_revision' => Carbon::parse('2026-10-31 09:30:00'),
            'estado' => Publicacion::ESTADO_PENDIENTE,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.moderacion.show', $reporte->id));

        $response->assertStatus(200);

        // Folio y nombre del objeto destacados
        $response->assertSee('OBJ-20261031-3333');
        $response->assertSee('Mochila Nike Negra con útiles escolares');

        // Descripción completa
        $response->assertSee('Mochila escolar de lona negra con libreta institucional y credencial TecNM en el bolsillo frontal.');

        // Autor
        $response->assertSee('Luis Fernando Gomez');
        $response->assertSee('luis.gomez@merida.tecnm.mx');

        // Ubicación
        $response->assertSee('Edificio D - Aulas de Sistemas');
        $response->assertSee('Salón D-4, segunda fila banca 3');

        // Fechas
        $response->assertSee('30/10/2026 12:00'); // Fecha captura
        $response->assertSee('31/10/2026 09:30'); // Fecha último envío
        $response->assertSee('30/10/2026');       // Fecha hallazgo
        $response->assertSee('11:45');            // Hora hallazgo

        // Opción para regresar a la lista de pendientes (Requerimiento 6)
        $response->assertSee(route('admin.moderacion.index'));
        $response->assertSee('Moderación de reportes');
    }

    /**
     * Prueba 3: La imagen principal se muestra correctamente y maneja casos sin fotografía.
     * Requerimiento 2: Fotografía principal mostrada con suficiente tamaño.
     */
    public function test_main_photograph_is_displayed_correctly_and_handles_missing_photos(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        // 1. Caso con fotografía
        $reporteConFoto = Publicacion::create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261031-4444',
            'nombre' => 'Termo Metálico Azul',
            'descripcion' => 'Termo con calcomanías',
            'estado' => Publicacion::ESTADO_PENDIENTE,
        ]);

        $fotoPrincipal = Fotografia::create([
            'publicacion_id' => $reporteConFoto->id,
            'ruta' => 'hallazgos/termo_principal.jpg',
            'nombre_original' => 'termo_principal.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 102400,
        ]);

        $responseConFoto = $this->actingAs($admin)->get(route('admin.moderacion.show', $reporteConFoto->id));
        $responseConFoto->assertStatus(200);
        $responseConFoto->assertSee('Fotografía principal');
        $responseConFoto->assertSee(route('fotografias.show', $fotoPrincipal->id));

        // 2. Caso sin fotografías
        $reporteSinFoto = Publicacion::create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261031-5555',
            'nombre' => 'Llaves con llavero',
            'descripcion' => 'Juego de tres llaves',
            'estado' => Publicacion::ESTADO_PENDIENTE,
        ]);

        $responseSinFoto = $this->actingAs($admin)->get(route('admin.moderacion.show', $reporteSinFoto->id));
        $responseSinFoto->assertStatus(200);
        $responseSinFoto->assertSee('Sin fotografía adjunta');
    }

    /**
     * Prueba 4: Las fotografías adicionales corresponden al reporte y se organizan en galería.
     * Requerimiento 2: Fotografías adicionales en galería sencilla cuando existan.
     */
    public function test_additional_photographs_belong_to_report_and_are_displayed_in_gallery(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $reporte = Publicacion::create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261031-6666',
            'nombre' => 'Chamarra Universitaria',
            'descripcion' => 'Chamarra vino con mangas blancas',
            'estado' => Publicacion::ESTADO_PENDIENTE,
        ]);

        // Foto 1: Principal
        $foto1 = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/chamarra_frente.jpg',
            'nombre_original' => 'chamarra_frente.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 100000,
        ]);

        // Foto 2 y 3: Adicionales
        $foto2 = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/chamarra_espalda.jpg',
            'nombre_original' => 'chamarra_espalda.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 100000,
        ]);

        $foto3 = Fotografia::create([
            'publicacion_id' => $reporte->id,
            'ruta' => 'hallazgos/chamarra_etiqueta.jpg',
            'nombre_original' => 'chamarra_etiqueta.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 100000,
        ]);

        // Foto ajena perteneciente a otro reporte
        $otroReporte = Publicacion::create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261031-9999',
            'nombre' => 'Otro Objeto Distinto',
            'descripcion' => 'Descripción ajena',
            'estado' => Publicacion::ESTADO_PENDIENTE,
        ]);

        $fotoAjena = Fotografia::create([
            'publicacion_id' => $otroReporte->id,
            'ruta' => 'hallazgos/foto_ajena.jpg',
            'nombre_original' => 'foto_ajena.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 100000,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.moderacion.show', $reporte->id));

        $response->assertStatus(200);

        // Se muestran las fotos del reporte
        $response->assertSee(route('fotografias.show', $foto1->id));
        $response->assertSee(route('fotografias.show', $foto2->id));
        $response->assertSee(route('fotografias.show', $foto3->id));
        $response->assertSee('Fotografías adicionales (2)');

        // No se debe incluir la foto del otro reporte
        $response->assertDontSee(route('fotografias.show', $fotoAjena->id));
    }

    /**
     * Prueba 5: Las observaciones de corrección aparecen únicamente cuando existen.
     * Requerimiento 2 y 5: Observaciones vigentes mostradas claramente sin ocupar espacio si no existen.
     */
    public function test_observations_appear_only_when_they_exist(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        // 1. Reporte CON observaciones de corrección
        $reporteConObs = Publicacion::create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261031-8881',
            'nombre' => 'Billetera Café',
            'descripcion' => 'Billetera de piel',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'observaciones' => 'Por favor detalla si la billetera contiene credencial escolar sin publicar el número de control.',
        ]);

        $responseConObs = $this->actingAs($admin)->get(route('admin.moderacion.show', $reporteConObs->id));
        $responseConObs->assertStatus(200);
        $responseConObs->assertSee('Observaciones vigentes de corrección');
        $responseConObs->assertSee('Por favor detalla si la billetera contiene credencial escolar sin publicar el número de control.');

        // 2. Reporte SIN observaciones de corrección
        $reporteSinObs = Publicacion::create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261031-8882',
            'nombre' => 'Calculadora Básica',
            'descripcion' => 'Calculadora sin detalles adicionales',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'observaciones' => null,
        ]);

        $responseSinObs = $this->actingAs($admin)->get(route('admin.moderacion.show', $reporteSinObs->id));
        $responseSinObs->assertStatus(200);
        $responseSinObs->assertDontSee('Observaciones vigentes de corrección');
    }

    /**
     * Prueba 6: Se muestran las acciones administrativas correspondientes y mecanismo de versión.
     * Requerimiento 4, 5 y Seguridad: Opciones «Aprobar publicación» y «Solicitar corrección» preparadas.
     */
    public function test_displays_corresponding_administrative_actions_and_version_mechanism(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $reportePendiente = Publicacion::create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261031-7701',
            'nombre' => 'Gafas de Sol Ray-Ban',
            'descripcion' => 'Gafas negras con estuche de piel',
            'estado' => Publicacion::ESTADO_PENDIENTE,
            'version' => 3,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.moderacion.show', $reportePendiente->id));

        $response->assertStatus(200);

        // Acciones administrativas requeridas (Requerimiento 4)
        $response->assertSee('Aprobar publicación');
        $response->assertSee('Solicitar corrección');

        // Mecanismo de control de versión / concurrencia preparado (Requerimiento 5 y Seguridad)
        $response->assertSee('name="lock_version"', false);
        $response->assertSee('value="3"', false);
        $response->assertSee('name="reporte_id"', false);

        // Caso de reporte ya NO disponible para revisión (ej. ya Aprobada)
        $reporteAprobado = Publicacion::create([
            'user_id' => $user->id,
            'folio' => 'OBJ-20261031-7702',
            'nombre' => 'Libro de Cálculo James Stewart',
            'descripcion' => 'Libro pasta dura',
            'estado' => 'Aprobada',
        ]);

        $responseAprobado = $this->actingAs($admin)->get(route('admin.moderacion.show', $reporteAprobado->id));
        $responseAprobado->assertStatus(200);
        $responseAprobado->assertSee('Este reporte ya no se encuentra en revisión pendiente');
        $responseAprobado->assertSee('disabled', false);
    }

    /**
     * Prueba 7: Usuarios no autorizados no pueden consultar el detalle.
     * Requerimiento de Seguridad: Restringir acceso exclusivamente a Administrador/Bibliotecario.
     */
    public function test_unauthorized_users_cannot_access_detail(): void
    {
        $userGeneral = User::factory()->create([
            'role' => User::ROLE_USUARIO_GENERAL,
        ]);

        $reporte = Publicacion::create([
            'user_id' => $userGeneral->id,
            'folio' => 'OBJ-20261031-1234',
            'nombre' => 'Objeto Privado',
            'descripcion' => 'Descripción de prueba',
            'estado' => Publicacion::ESTADO_PENDIENTE,
        ]);

        // Usuario general autenticado recibe 403 Forbidden
        $generalResponse = $this->actingAs($userGeneral)->get(route('admin.moderacion.show', $reporte->id));
        $generalResponse->assertStatus(403);
        $generalResponse->assertSee('No tienes permiso para realizar esta acción');

        // Visitante no autenticado es redirigido a login
        auth()->logout();
        $guestResponse = $this->get(route('admin.moderacion.show', $reporte->id));
        $guestResponse->assertRedirect(route('login'));
    }

    /**
     * Prueba 8: Reportes inexistentes se manejan correctamente con error 404.
     * Requerimiento de Seguridad: Comprobar la existencia del reporte.
     */
    public function test_nonexistent_reports_are_handled_correctly_with_404(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.moderacion.show', 999999));

        $response->assertStatus(404);
    }

    /**
     * Prueba 9: La interfaz no expone información privada innecesaria.
     * Requerimiento de Seguridad: No publicar datos personales que deban ocultarse.
     */
    public function test_view_does_not_expose_unnecessary_private_information(): void
    {
        $admin = User::factory()->admin()->create();

        $autor = User::factory()->create([
            'name' => 'Ana Sofia Cantón',
            'email' => 'ana.canton@merida.tecnm.mx',
            'password' => bcrypt('ClaveSecreta12!'),
            'remember_token' => 'token_super_secreto_123456789',
        ]);

        $reporte = Publicacion::create([
            'user_id' => $autor->id,
            'folio' => 'OBJ-20261031-9876',
            'nombre' => 'Paraguas Automático',
            'descripcion' => 'Paraguas negro plegable',
            'estado' => Publicacion::ESTADO_PENDIENTE,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.moderacion.show', $reporte->id));

        $response->assertStatus(200);

        // No debe exponer hashes de contraseñas ni tokens de sesión
        $response->assertDontSee($autor->password);
        $response->assertDontSee('token_super_secreto_123456789');
    }
}

