<?php

namespace Tests\Feature;

use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicacionContactoVigenteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * CA-16: Detalle de publicación muestra el teléfono vigente del autor cuando no está en biblioteca.
     */
    public function test_detalle_publicacion_muestra_telefono_vigente_del_autor(): void
    {
        $autor = User::factory()->create([
            'phone' => '9991234567',
        ]);

        $lector = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'estado' => 'Aprobada',
            'disponible' => true,
            'area_zona' => 'Edificio G - Sistemas',
            'lugar_hallazgo' => 'Edificio G - Aula G-102',
        ]);

        $response = $this->actingAs($lector)->get(route('publicaciones.show', $publicacion->id));

        $response->assertOk();
        $response->assertSee('9991234567');
        $response->assertSee('Teléfono: 9991234567');
        $response->assertSee('Llamar');
    }

    /**
     * CA-16: Al actualizar el teléfono del autor y volver a consultar el detalle,
     * se muestra el nuevo número automáticamente.
     */
    public function test_detalle_publicacion_se_actualiza_automaticamente_al_modificar_telefono_del_autor(): void
    {
        $autor = User::factory()->create([
            'phone' => '9991112233',
        ]);

        $lector = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'estado' => 'Aprobada',
            'disponible' => true,
            'area_zona' => 'Canchas Deportivas',
            'lugar_hallazgo' => 'Gradas de la cancha de básquetbol',
        ]);

        // 1. Primera consulta: muestra teléfono anterior
        $res1 = $this->actingAs($lector)->get(route('publicaciones.show', $publicacion->id));
        $res1->assertSee('9991112233');

        // 2. El autor actualiza su teléfono desde Mi Perfil
        $this->actingAs($autor)->patch('/profile', [
            'phone' => '9998889900',
        ]);

        // 3. Segunda consulta (recarga del detalle): refleja inmediatamente el nuevo teléfono
        $res2 = $this->actingAs($lector)->get(route('publicaciones.show', $publicacion->id));
        $res2->assertSee('9998889900');
        $res2->assertDontSee('9991112233');
    }

    /**
     * CA-17 y CA-22: La actualización del teléfono no modifica el estado, vigencia,
     * fechas ni evidencias de la publicación.
     */
    public function test_actualizacion_de_telefono_no_altera_estado_vigencia_ni_evidencias_de_publicacion(): void
    {
        $autor = User::factory()->create(['phone' => '9991112233']);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'estado' => 'Aprobada',
            'disponible' => true,
            'area_zona' => 'Laboratorio de Cómputo',
            'lugar_hallazgo' => 'Laboratorio de Cómputo A',
        ]);

        $foto = Fotografia::create([
            'publicacion_id' => $publicacion->id,
            'ruta' => 'hallazgos/evidencia_test.jpg',
            'nombre_original' => 'evidencia_test.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 1024,
        ]);

        $estadoOriginal = $publicacion->estado;
        $disponibleOriginal = $publicacion->disponible;
        $fechaCapturaOriginal = $publicacion->fecha_captura;
        $createdAtOriginal = $publicacion->created_at;

        // Actualizar teléfono del autor
        $this->actingAs($autor)->patch('/profile', [
            'phone' => '9997776655',
        ]);

        $publicacion->refresh();

        // Comprobar que atributos críticos no sufrieron mutaciones
        $this->assertSame($estadoOriginal, $publicacion->estado);
        $this->assertSame($disponibleOriginal, $publicacion->disponible);
        $this->assertEquals($fechaCapturaOriginal, $publicacion->fecha_captura);
        $this->assertEquals($createdAtOriginal, $publicacion->created_at);

        // Evidencias (fotografías) permanecen intactas
        $this->assertDatabaseHas('fotografias', [
            'id' => $foto->id,
            'publicacion_id' => $publicacion->id,
            'ruta' => 'hallazgos/evidencia_test.jpg',
        ]);
    }

    /**
     * CA-20: Cuando el objeto está bajo resguardo confirmado de biblioteca, el detalle
     * muestra la ubicación, el horario y las instrucciones de recuperación en lugar del teléfono del autor.
     */
    public function test_objeto_bajo_resguardo_de_biblioteca_muestra_instrucciones_y_horario_en_lugar_del_telefono(): void
    {
        $autor = User::factory()->create([
            'phone' => '9993334455',
        ]);

        $lector = User::factory()->create();

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'estado' => 'Aprobada',
            'disponible' => true,
            'area_zona' => 'Biblioteca Campus Poniente',
            'lugar_hallazgo' => 'Biblioteca Campus Poniente - Mesa de estudio 3',
        ]);

        $response = $this->actingAs($lector)->get(route('publicaciones.show', $publicacion->id));

        $response->assertOk();
        // Muestra información de resguardo e instrucciones de biblioteca
        $response->assertSee('Objeto bajo resguardo confirmado de Biblioteca');
        $response->assertSee('Horario de atención:');
        $response->assertSee('Lunes a Viernes de 7:00 a 19:00 hrs.');
        $response->assertSee('Instrucciones de recuperación:');
        $response->assertSee('credencial institucional vigente');

        // En lugar del teléfono del autor, el teléfono no se muestra
        $response->assertDontSee('9993334455');
    }

    /**
     * CA-19: El teléfono actualizado permanece oculto para visitantes sin sesión y en tarjetas públicas del muro.
     */
    public function test_telefono_permanece_oculto_para_visitantes_sin_sesion_y_en_el_muro(): void
    {
        $autor = User::factory()->create([
            'phone' => '9994445566',
        ]);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $autor->id,
            'estado' => 'Aprobada',
            'disponible' => true,
            'area_zona' => 'Cafetería',
            'lugar_hallazgo' => 'Mesa exterior de cafetería',
        ]);

        // 1. Visitante sin sesión en el detalle de la publicación
        $responseShow = $this->get(route('publicaciones.show', $publicacion->id));
        $responseShow->assertOk();
        $responseShow->assertDontSee('9994445566');
        $responseShow->assertSee('Información de contacto protegida');

        // 2. Visitante en el muro público
        $responseMuro = $this->get(route('muro'));
        $responseMuro->assertOk();
        $responseMuro->assertDontSee('9994445566');
    }

    /**
     * CA-16 en Mis Reportes: Muestra el contacto vigente del usuario en sus tarjetas de reporte.
     */
    public function test_mis_reportes_muestra_contacto_vigente_del_usuario(): void
    {
        $user = User::factory()->create([
            'phone' => '9995556677',
        ]);

        $publicacion = Publicacion::factory()->create([
            'user_id' => $user->id,
            'nombre' => 'Calculadora científica Casio',
            'titulo' => 'Calculadora científica Casio',
        ]);

        // 1. Consulta inicial en Mis Reportes
        $res1 = $this->actingAs($user)->get(route('reportes.mis-reportes'));
        $res1->assertOk();
        $res1->assertSee('9995556677');
        $res1->assertDontSee('Llamar');

        // 2. El usuario actualiza su teléfono
        $this->actingAs($user)->patch('/profile', [
            'phone' => '9990001122',
        ]);

        // 3. Al recargar Mis Reportes, refleja el nuevo número
        $res2 = $this->actingAs($user)->get(route('reportes.mis-reportes'));
        $res2->assertOk();
        $res2->assertSee('9990001122');
        $res2->assertDontSee('9995556677');
    }
}
