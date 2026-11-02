<?php

namespace Tests\Feature;

use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicacionContactoResguardoHU09Task3Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Escenario 1: Cuando el autor conserva el objeto, se muestra su teléfono vigente
     * de exactamente 10 dígitos y el botón «Llamar».
     */
    public function test_autor_conserva_objeto_muestra_telefono_vigente_10_digitos_y_boton_llamar(): void
    {
        $autor = User::factory()->create([
            'phone' => '9991234567',
        ]);

        $lector = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Calculadora Científica Casio',
            'titulo' => 'Calculadora Científica Casio',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Edificio G - Sistemas',
            'lugar_hallazgo' => 'Laboratorio G-1',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
        ]);

        $response = $this->actingAs($lector)->get(route('publicaciones.show', $publicacion->id));

        $response->assertOk();

        // 1. Muestra contacto y teléfono de 10 dígitos
        $response->assertSee('Contacto directo con el autor');
        $response->assertSee('9991234567');
        $response->assertSee('Teléfono: 9991234567');

        // 2. Muestra botón «Llamar» con enlace tel:
        $response->assertSee('Llamar');
        $response->assertSee('href="tel:9991234567"', false);

        // 3. Oculta aviso de biblioteca
        $response->assertDontSee('Disponible en biblioteca');
    }

    /**
     * Escenario 2: Cuando existe recepción física confirmada en biblioteca y la publicación
     * está autorizada, se muestra «Disponible en biblioteca» con ubicación, horario e instrucciones.
     * El teléfono del autor y el botón «Llamar» permanecen ocultos.
     */
    public function test_resguardo_confirmado_en_biblioteca_muestra_disponible_en_biblioteca_y_oculta_telefono_y_llamar(): void
    {
        $autor = User::factory()->create([
            'phone' => '9998887766',
        ]);

        $lector = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Laptop Dell Latitude',
            'titulo' => 'Laptop Dell Latitude',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Edificio de Biblioteca',
            'lugar_hallazgo' => 'Biblioteca Campus Poniente - Sala de Lectura',
            'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO,
        ]);

        $response = $this->actingAs($lector)->get(route('publicaciones.show', $publicacion->id));

        $response->assertOk();

        // 1. Aviso visible «Disponible en biblioteca»
        $response->assertSee('Disponible en biblioteca');
        $response->assertSee('Objeto bajo resguardo confirmado de Biblioteca');

        // 2. Ubicación donde solicitar la atención
        $response->assertSee('Ubicación de atención:');

        // 3. Horario de atención correspondiente
        $response->assertSee('Horario de atención:');
        $response->assertSee('Lunes a Viernes de 7:00 a 19:00 hrs.');

        // 4. Instrucciones para la persona interesada
        $response->assertSee('Instrucciones de recuperación:');
        $response->assertSee('credencial institucional vigente');

        // 5. El teléfono del autor y el botón «Llamar» permanecen COMPLETAMENTE ocultos
        $response->assertDontSee('9998887766');
        $response->assertDontSee('Llamar');
        $response->assertDontSee('tel:9998887766');
    }

    /**
     * Escenario 3: Cuando la recepción está pendiente (no confirmada), NO se muestra
     * el aviso de disponibilidad en biblioteca.
     */
    public function test_recepcion_pendiente_no_muestra_aviso_disponible_en_biblioteca(): void
    {
        $autor = User::factory()->create([
            'phone' => '9995554433',
        ]);

        $lector = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Libro de Álgebra Lineal',
            'titulo' => 'Libro de Álgebra Lineal',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Biblioteca Campus Poniente',
            'lugar_hallazgo' => 'Mesa de estudio 2',
            'situacion_resguardo' => 'Recepción pendiente',
        ]);

        $response = $this->actingAs($lector)->get(route('publicaciones.show', $publicacion->id));

        $response->assertOk();

        // 1. NO se muestra disponibilidad en biblioteca porque aún no hay recepción confirmada
        $response->assertDontSee('Disponible en biblioteca');

        // 2. Al seguir el objeto en posesión del autor, se muestra su teléfono y botón Llamar
        $response->assertSee('9995554433');
        $response->assertSee('Llamar');
    }

    /**
     * Escenario 4: Cuando existe una solicitud pendiente de resguardo, tampoco se muestra
     * como disponible en biblioteca.
     */
    public function test_solicitud_pendiente_no_se_considera_resguardo_confirmado(): void
    {
        $autor = User::factory()->create([
            'phone' => '9994443322',
        ]);

        $lector = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Mochila Azul',
            'titulo' => 'Mochila Azul',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Biblioteca Campus Poniente',
            'lugar_hallazgo' => 'Pasillo central',
            'situacion_resguardo' => 'Solicitud pendiente de resguardo',
        ]);

        $response = $this->actingAs($lector)->get(route('publicaciones.show', $publicacion->id));

        $response->assertOk();
        $response->assertDontSee('Disponible en biblioteca');
        $response->assertSee('9994443322');
        $response->assertSee('Llamar');
    }

    /**
     * Escenario 5: Cuando el teléfono es inválido (menor a 10 dígitos, mayor o con letras),
     * o no está registrado, no se muestra un contacto incorrecto ni el botón Llamar.
     */
    public function test_telefono_invalido_o_no_registrado_no_muestra_contacto_incorrecto_ni_boton_llamar(): void
    {
        // Autor con teléfono incompleto (menos de 10 dígitos)
        $autorInvalido = User::factory()->create([
            'phone' => '999123',
        ]);

        $lector = User::factory()->create();

        $pubInvalido = Publicacion::factory()->create([
            'user_id' => $autorInvalido->id,
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Edificio F',
            'lugar_hallazgo' => 'Laboratorio F-1',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
        ]);

        $resInvalido = $this->actingAs($lector)->get(route('publicaciones.show', $pubInvalido->id));
        $resInvalido->assertOk();
        $resInvalido->assertDontSee('999123');
        $resInvalido->assertDontSee('Llamar');

        // Autor con teléfono vacío
        $autorSinTel = User::factory()->create([
            'phone' => '',
        ]);

        $pubSinTel = Publicacion::factory()->create([
            'user_id' => $autorSinTel->id,
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Edificio F',
            'lugar_hallazgo' => 'Laboratorio F-2',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
        ]);

        $resSinTel = $this->actingAs($lector)->get(route('publicaciones.show', $pubSinTel->id));
        $resSinTel->assertOk();
        $resSinTel->assertDontSee('Contacto directo con el autor');
        $resSinTel->assertDontSee('Llamar');
    }

    /**
     * Escenario 6: Los visitantes sin autenticación no tienen acceso a información privada
     * (ni teléfono, ni botón Llamar, ni instrucciones de biblioteca).
     */
    public function test_visitantes_sin_autenticacion_no_ven_informacion_de_contacto_privada(): void
    {
        $autor = User::factory()->create([
            'phone' => '9997778899',
        ]);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'area_zona' => 'Edificio H',
            'lugar_hallazgo' => 'Salón H-101',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
        ]);

        $response = $this->get(route('publicaciones.show', $publicacion->id));

        $response->assertOk();
        $response->assertSee('Información de contacto protegida');
        $response->assertDontSee('9997778899');
        $response->assertDontSee('Llamar');
    }

    /**
     * Escenario 7 (Alcance del contacto):
     * - Consultar el teléfono o botón «Llamar» no registra llamadas, ni reservas, ni reclamaciones.
     * - No altera la disponibilidad ni detiene o amplía el vencimiento.
     * - La vista no incluye chat ni formularios de reclamación.
     * - El teléfono permanece en texto visible y seleccionable.
     */
    public function test_alcance_del_contacto_inmutable_sin_chat_ni_reclamaciones(): void
    {
        $autor = User::factory()->create([
            'phone' => '9998881122',
        ]);

        $lector = User::factory()->create();

        $fechaVencimiento = now()->addDays(10);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'nombre' => 'Audífonos JBL',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => true,
            'fecha_vencimiento' => $fechaVencimiento,
            'area_zona' => 'Edificio B',
            'lugar_hallazgo' => 'Edificio B - Aula B-2',
            'situacion_resguardo' => Publicacion::RESGUARDO_AUTOR,
        ]);

        $response = $this->actingAs($lector)->get(route('publicaciones.show', $publicacion->id));
        $response->assertOk();

        // 1. El teléfono permanece en texto visible y seleccionable en el DOM
        $response->assertSee('Teléfono: 9998881122');
        $response->assertSee('id="contacto-telefono-vigente"', false);

        // 2. El botón «Llamar» es un enlace tel: nativo del cliente sin llamadas POST/PUT
        $response->assertSee('href="tel:9998881122"', false);

        // 3. No existen formularios de reclamación, reserva ni componentes de chat
        $response->assertDontSee('action="http://localhost/reclamar', false);
        $response->assertDontSee('action="http://localhost/reclamaciones', false);
        $response->assertDontSee('action="http://localhost/reservar', false);
        $response->assertDontSee('Reclamar objeto');
        $response->assertDontSee('Reservar objeto');
        $response->assertDontSee('chat', false);

        // 4. Verificación de inmutabilidad en la base de datos
        $publicacion->refresh();
        $this->assertEquals(Publicacion::ESTADO_APROBADA, $publicacion->estado);
        $this->assertTrue((bool) $publicacion->disponible);
        $this->assertEquals($fechaVencimiento->format('Y-m-d H:i:s'), $publicacion->fecha_vencimiento->format('Y-m-d H:i:s'));
        $this->assertEquals(Publicacion::RESGUARDO_AUTOR, $publicacion->situacion_resguardo);
    }
}
