<?php

namespace Tests\Feature;

use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicacionDetalleHallazgoHU09Task1Test extends TestCase
{
    use RefreshDatabase;

    /**
     * CA-01: El usuario autenticado visualiza los datos requeridos del hallazgo:
     * Nombre, descripción completa, fecha del hallazgo, área o edificio,
     * ubicación específica, nombre completo del autor, fecha de publicación y disponibilidad actual.
     */
    public function test_usuario_autenticado_ve_datos_completos_del_hallazgo(): void
    {
        $autor = User::factory()->create([
            'name' => 'Carlos Mendoza García',
        ]);

        $lector = User::factory()->create();

        $fechaHallazgo = Carbon::create(2026, 10, 20, 0, 0, 0, Publicacion::TIMEZONE);
        $fechaCreacion = Carbon::create(2026, 10, 21, 9, 0, 0, Publicacion::TIMEZONE);
        $fechaAprobacion = Carbon::create(2026, 10, 25, 14, 30, 0, Publicacion::TIMEZONE);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Mochila Negra Jansport',
            'titulo' => 'Mochila Negra Jansport',
            'descripcion' => 'Mochila de lona negra con cierre frontal y llavero del TecNM en el compartimento superior.',
            'fecha_hallazgo' => $fechaHallazgo->format('Y-m-d'),
            'hora_hallazgo' => '11:15',
            'area_zona' => 'Edificio F - Centro de Cómputo',
            'ubicacion_especifica' => 'Laboratorio 3, bajo el escritorio número 12',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'created_at' => $fechaCreacion,
            'fecha_primera_aprobacion' => $fechaAprobacion,
            'fecha_aprobacion' => $fechaAprobacion,
            'fecha_vencimiento' => $fechaAprobacion->copy()->addDays(14),
        ]);

        $response = $this->actingAs($lector)->get(route('publicaciones.show', $publicacion->id));

        $response->assertOk();

        // 1. Nombre del objeto y descripción completa
        $response->assertSee('Mochila Negra Jansport');
        $response->assertSee('Mochila de lona negra con cierre frontal y llavero del TecNM en el compartimento superior.');

        // 2. Etiquetas exactas
        $response->assertSee('Fecha del hallazgo');
        $response->assertSee('Fecha de publicación');

        // 3. Fecha del hallazgo y hora aproximada
        $response->assertSee('20/10/2026');
        $response->assertSee('11:15');
        $response->assertSee('Hora aproximada: 11:15 hrs');

        // 4. Ubicación
        $response->assertSee('Edificio F - Centro de Cómputo');
        $response->assertSee('Laboratorio 3, bajo el escritorio número 12');

        // 5. Nombre completo del autor
        $response->assertSee('Carlos Mendoza García');

        // 6. Fecha de publicación (inicio del periodo autorizado, 25/10/2026, no la de creación 21/10/2026)
        $response->assertSee('25/10/2026');

        // 7. Disponibilidad actual
        $response->assertSee('Disponibilidad actual');
        $response->assertSee('Disponible');
    }

    /**
     * CA-02: La fecha de publicación debe corresponder al inicio del periodo autorizado
     * y no necesariamente a la fecha de creación original del reporte.
     */
    public function test_fecha_publicacion_corresponde_a_inicio_del_periodo_autorizado(): void
    {
        $autor = User::factory()->create([
            'name' => 'María Fernández Ruiz',
        ]);

        $lector = User::factory()->create();

        $fechaHallazgo = Carbon::now(Publicacion::TIMEZONE)->subDays(15);
        $fechaCreacionOriginal = Carbon::now(Publicacion::TIMEZONE)->subDays(10);
        $fechaInicioAutorizado = Carbon::now(Publicacion::TIMEZONE)->subDays(2);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Calculadora Texas Instruments',
            'titulo' => 'Calculadora Texas Instruments',
            'descripcion' => 'Calculadora gráfica color plata olvidada en el salón de matemáticas avanzadas.',
            'fecha_hallazgo' => $fechaHallazgo->format('Y-m-d'),
            'hora_hallazgo' => null,
            'area_zona' => 'Edificio B - Ciencias Básicas',
            'ubicacion_especifica' => 'Aula B-204 fila central',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'created_at' => $fechaCreacionOriginal,
            'fecha_primera_aprobacion' => $fechaInicioAutorizado,
            'fecha_aprobacion' => $fechaInicioAutorizado,
            'fecha_vencimiento' => $fechaInicioAutorizado->copy()->addDays(14),
        ]);

        $response = $this->actingAs($lector)->get(route('publicaciones.show', $publicacion->id));

        $response->assertOk();

        // Debe mostrar la fecha de inicio del periodo autorizado
        $response->assertSee('Fecha de publicación');
        $response->assertSee($fechaInicioAutorizado->format('d/m/Y'));

        // No debe mostrar la fecha de creación original como fecha de publicación
        $response->assertDontSee($fechaCreacionOriginal->format('d/m/Y'));
    }

    /**
     * CA-03: La hora aproximada solo se muestra cuando exista un valor registrado.
     * Si no se proporcionó, no debe mostrar horas inventadas ni predeterminadas (como 00:00 o 12:00).
     */
    public function test_hora_no_se_muestra_cuando_no_esta_registrada(): void
    {
        $autor = User::factory()->create();
        $lector = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Llaves con Llavero Azul',
            'titulo' => 'Llaves con Llavero Azul',
            'descripcion' => 'Juego de tres llaves con aro plateado encontradas cerca de las canchas.',
            'fecha_hallazgo' => Carbon::now(Publicacion::TIMEZONE)->subDays(3)->format('Y-m-d'),
            'hora_hallazgo' => null, // Sin hora registrada
            'area_zona' => 'Canchas Deportivas',
            'ubicacion_especifica' => 'Banca de descanso junto al campo de fútbol',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => Carbon::now(Publicacion::TIMEZONE)->subDays(2),
            'fecha_vencimiento' => Carbon::now(Publicacion::TIMEZONE)->addDays(12),
        ]);

        $response = $this->actingAs($lector)->get(route('publicaciones.show', $publicacion->id));

        $response->assertOk();
        $response->assertSee(Carbon::now(Publicacion::TIMEZONE)->subDays(3)->format('d/m/Y'));

        // No debe mostrar hora aproximada ni valores predeterminados
        $response->assertDontSee('Hora aproximada');
        $response->assertDontSee('00:00');
        $response->assertDontSee('12:00');
    }

    /**
     * CA-04: La vista muestra la información del objeto correcto seleccionado desde el muro.
     */
    public function test_muestra_la_informacion_del_objeto_seleccionado_especifico(): void
    {
        $autor1 = User::factory()->create(['name' => 'Autor Objeto Uno']);
        $autor2 = User::factory()->create(['name' => 'Autor Objeto Dos']);
        $lector = User::factory()->create();

        $pub1 = Publicacion::factory()->create([
            'user_id' => $autor1->id,
            'nombre' => 'Paraguas Rojo Plegable',
            'descripcion' => 'Descripción única del paraguas rojo hallado en cafetería.',
            'area_zona' => 'Cafetería Central',
            'ubicacion_especifica' => 'Mesa 4 exterior',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => now(),
        ]);

        $pub2 = Publicacion::factory()->create([
            'user_id' => $autor2->id,
            'nombre' => 'Chamarra Universitaria Gris',
            'descripcion' => 'Descripción única de la chamarra gris olvidada en auditorio.',
            'area_zona' => 'Auditorio Principal',
            'ubicacion_especifica' => 'Fila H asiento 8',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => now(),
        ]);

        // Consulta del objeto 1
        $res1 = $this->actingAs($lector)->get(route('publicaciones.show', $pub1->id));
        $res1->assertOk();
        $res1->assertSee('Paraguas Rojo Plegable');
        $res1->assertSee('Autor Objeto Uno');
        $res1->assertSee('Cafetería Central');
        $res1->assertDontSee('Chamarra Universitaria Gris');
        $res1->assertDontSee('Autor Objeto Dos');
        $res1->assertDontSee('Auditorio Principal');

        // Consulta del objeto 2
        $res2 = $this->actingAs($lector)->get(route('publicaciones.show', $pub2->id));
        $res2->assertOk();
        $res2->assertSee('Chamarra Universitaria Gris');
        $res2->assertSee('Autor Objeto Dos');
        $res2->assertSee('Auditorio Principal');
        $res2->assertDontSee('Paraguas Rojo Plegable');
        $res2->assertDontSee('Autor Objeto Uno');
        $res2->assertDontSee('Cafetería Central');
    }

    /**
     * CA-05: Respetar acceso autenticado.
     * Al intentar ver el detalle desde el muro sin sesión, la lógica existente solicita
     * iniciar sesión y conserva el destino para redirigir tras autenticarse.
     */
    public function test_acceso_autenticado_conserva_destino_desde_el_muro(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('Password123!'),
        ]);

        $publicacion = Publicacion::factory()->create([
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => now(),
        ]);

        // 1. Visitante sin sesión intenta ver detalles privados desde el botón del muro
        $responseGuest = $this->get(route('publicaciones.detalles-privados', $publicacion->id));
        $responseGuest->assertRedirect(route('login'));
        $this->assertEquals($publicacion->id, session('intended_publication_id'));

        // 2. Al iniciar sesión, es redirigido a la publicación prevista
        $responseLogin = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $responseLogin->assertRedirect(route('publicaciones.show', $publicacion->id));

        // 3. Una vez autenticado, accede al detalle completo con éxito
        $responseDetalle = $this->actingAs($user)->get(route('publicaciones.show', $publicacion->id));
        $responseDetalle->assertOk();
        $responseDetalle->assertSee('Fecha del hallazgo');
        $responseDetalle->assertSee('Fecha de publicación');
    }

    /**
     * CA-06: Consultar el detalle es una acción de solo lectura.
     * No modifica el estado, ni la vigencia, ni la disponibilidad del objeto.
     */
    public function test_consultar_detalle_no_modifica_estado_ni_vigencia_del_objeto(): void
    {
        $autor = User::factory()->create();
        $lector = User::factory()->create();

        $fechaVencimiento = Carbon::now(Publicacion::TIMEZONE)->addDays(10);
        $fechaPrimeraAprobacion = Carbon::now(Publicacion::TIMEZONE)->subDays(4);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Audífonos Sony WH-1000XM4',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => $fechaPrimeraAprobacion,
            'fecha_aprobacion' => $fechaPrimeraAprobacion,
            'fecha_vencimiento' => $fechaVencimiento,
            'version' => 1,
        ]);

        $estadoOriginal = $publicacion->estado;
        $disponibleOriginal = $publicacion->disponible;
        $versionOriginal = $publicacion->version;
        $vencimientoOriginal = $publicacion->fecha_vencimiento->toDateTimeString();

        // Consulta repetida
        $this->actingAs($lector)->get(route('publicaciones.show', $publicacion->id))->assertOk();
        $this->actingAs($lector)->get(route('publicaciones.show', $publicacion->id))->assertOk();

        $publicacion->refresh();

        $this->assertEquals($estadoOriginal, $publicacion->estado);
        $this->assertEquals($disponibleOriginal, $publicacion->disponible);
        $this->assertEquals($versionOriginal, $publicacion->version);
        $this->assertEquals($vencimientoOriginal, $publicacion->fecha_vencimiento->toDateTimeString());
    }

    /**
     * CA-07: La vista se mantiene estrictamente como vista de consulta.
     * No agrega opciones para editar, cancelar, aprobar ni cambiar de estado.
     */
    public function test_vista_de_detalle_no_muestra_opciones_de_edicion_ni_cambio_de_estado(): void
    {
        $autor = User::factory()->create();
        $lector = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Calculadora Casio fx-991',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_primera_aprobacion' => now(),
        ]);

        $response = $this->actingAs($lector)->get(route('publicaciones.show', $publicacion->id));
        $response->assertOk();

        // No debe contener botones ni enlaces para modificar el reporte
        $response->assertDontSee('Editar reporte');
        $response->assertDontSee('Cancelar reporte');
        $response->assertDontSee('Aprobar publicación');
        $response->assertDontSee('Solicitar corrección');
        $response->assertDontSee(route('reportes.edit', $publicacion->id));
        $response->assertDontSee(route('reportes.cancelar', $publicacion->id));
    }
}
