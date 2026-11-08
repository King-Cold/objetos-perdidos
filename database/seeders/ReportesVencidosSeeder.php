<?php

namespace Database\Seeders;

use App\Models\DecisionModeracion;
use App\Models\Fotografia;
use App\Models\Publicacion;
use App\Models\Recepcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ReportesVencidosSeeder extends Seeder
{
    /**
     * Crea usuarios y al menos 5 reportes vencidos para pruebas de HU-16:
     * - 3 reportes vencidos elegibles para registrar recepción física por vencimiento (Tarea 16.1)
     * - 1 reporte bajo resguardo con periodo anual vigente para probar cambio de ubicación (Tarea 16.2 y 16.3)
     * - 1 reporte bajo resguardo con periodo anual vencido para probar estado «Resguardo vencido» (Tarea 16.3)
     */
    public function run(): void
    {
        $timezone = Publicacion::TIMEZONE;
        $ahora = Carbon::now($timezone);

        // 1. Obtener o crear Administrador
        $admin = User::firstOrCreate(
            ['email' => 'admin@merida.tecnm.mx'],
            [
                'name' => 'Administrador Institucional Biblioteca',
                'phone' => '9999000000',
                'password' => Hash::make('AdminTecNM2026!'),
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => $ahora,
            ]
        );

        // 2. Obtener o crear Usuario General (autor)
        $autor = User::firstOrCreate(
            ['email' => 'usuario@merida.tecnm.mx'],
            [
                'name' => 'Usuario General Campus Poniente',
                'phone' => '9991234567',
                'password' => Hash::make('UsuarioTecNM2026!'),
                'role' => User::ROLE_USUARIO_GENERAL,
                'email_verified_at' => $ahora,
            ]
        );

        $fotosMuestra = [
            'hallazgos/4IuIevFEyjpFKGLbMCn354pborM57jODfdbvKo4t.jpg',
            'hallazgos/4vJH8PvRBKmQ0ZwGjeAE6O8krYk3TZ3JImR1FzVA.jpg',
            'hallazgos/5XXU0qRNmzQSfDxOiAgWcSpWEwVJrQLSs1NbcvMA.jpg',
            'hallazgos/8iOkRDTqwfvk5vQu7qqvtR9CrI4lsbDUhL6yBr8U.jpg',
            'hallazgos/Aa7O2gdNfqJswbfeBEtaVeO4pL9Rl7LqCo7zwsIh.jpg',
        ];

        // ---------------------------------------------------------------------------------
        // REPORTE 1: Calculadora Científica (Vencido, pendiente de entrega a biblioteca)
        // ---------------------------------------------------------------------------------
        $fechaAprobacion1 = $ahora->copy()->subDays(20);
        $fechaVencimiento1 = $fechaAprobacion1->copy()->addDays(14); // Venció hace 6 días

        $rep1 = Publicacion::create([
            'user_id' => $autor->id,
            'nombre' => 'Calculadora Científica Casio fx-991LAX',
            'descripcion' => 'Calculadora científica negra con tapa plástica protectora, olvidada en las bancas exteriores del Edificio H.',
            'fecha_hallazgo' => $fechaAprobacion1->copy()->subDays(2)->toDateString(),
            'hora_hallazgo' => '10:15:00',
            'area_zona' => 'Edificio H',
            'lugar_hallazgo' => 'Bancas exteriores frente al laboratorio de cómputo H-1',
            'categoria' => 'Electrónica y Accesorios',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'situacion_resguardo' => 'En posesión del autor',
            'devolucion_en_proceso' => false,
            'estado_devolucion' => null,
            'fecha_captura' => $fechaAprobacion1->copy()->subDays(1),
            'fecha_envio_revision' => $fechaAprobacion1->copy()->subDays(1),
            'fecha_primera_aprobacion' => $fechaAprobacion1,
            'fecha_aprobacion' => $fechaAprobacion1,
            'fecha_vencimiento' => $fechaVencimiento1,
            'version' => 1,
        ]);

        Fotografia::create([
            'publicacion_id' => $rep1->id,
            'ruta' => $fotosMuestra[0],
            'nombre_original' => 'calculadora_casio.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 117454,
            'es_principal' => true,
        ]);

        DecisionModeracion::create([
            'publicacion_id' => $rep1->id,
            'user_id' => $admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_APROBADA,
            'motivo' => 'Publicación aprobada conforme a lineamientos de moderación institucional.',
            'fecha_decision' => $fechaAprobacion1,
        ]);

        // ---------------------------------------------------------------------------------
        // REPORTE 2: Mochila Negra Swissgear (Vencido, pendiente de entrega a biblioteca)
        // ---------------------------------------------------------------------------------
        $fechaAprobacion2 = $ahora->copy()->subDays(16);
        $fechaVencimiento2 = $fechaAprobacion2->copy()->addDays(14); // Venció hace 2 días

        $rep2 = Publicacion::create([
            'user_id' => $autor->id,
            'nombre' => 'Mochila Negra Swissgear con Libretas',
            'descripcion' => 'Mochila color negro con detalles rojos, contiene tres libretas profesionales y una lapicera azul.',
            'fecha_hallazgo' => $fechaAprobacion2->copy()->subDays(1)->toDateString(),
            'hora_hallazgo' => '13:40:00',
            'area_zona' => 'Edificio C',
            'lugar_hallazgo' => 'Mesa central de la sala de estudio 2',
            'categoria' => 'Mochilas y Bolsos',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'situacion_resguardo' => 'En posesión del autor',
            'devolucion_en_proceso' => false,
            'estado_devolucion' => null,
            'fecha_captura' => $fechaAprobacion2->copy()->subHours(2),
            'fecha_envio_revision' => $fechaAprobacion2->copy()->subHours(1),
            'fecha_primera_aprobacion' => $fechaAprobacion2,
            'fecha_aprobacion' => $fechaAprobacion2,
            'fecha_vencimiento' => $fechaVencimiento2,
            'version' => 1,
        ]);

        Fotografia::create([
            'publicacion_id' => $rep2->id,
            'ruta' => $fotosMuestra[1],
            'nombre_original' => 'mochila_swissgear.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 53617,
            'es_principal' => true,
        ]);

        DecisionModeracion::create([
            'publicacion_id' => $rep2->id,
            'user_id' => $admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_APROBADA,
            'motivo' => 'Publicación aprobada para difusión en el muro de objetos perdidos.',
            'fecha_decision' => $fechaAprobacion2,
        ]);

        // ---------------------------------------------------------------------------------
        // REPORTE 3: Audífonos Sony Inalámbricos (Vencido, pendiente de entrega a biblioteca)
        // ---------------------------------------------------------------------------------
        $fechaAprobacion3 = $ahora->copy()->subDays(25);
        $fechaVencimiento3 = $fechaAprobacion3->copy()->addDays(14); // Venció hace 11 días

        $rep3 = Publicacion::create([
            'user_id' => $autor->id,
            'nombre' => 'Audífonos Sony WH-CH520 Inalámbricos',
            'descripcion' => 'Audífonos de diadema inalámbricos color azul marino con cable de carga USB-C incluido.',
            'fecha_hallazgo' => $fechaAprobacion3->copy()->subDays(2)->toDateString(),
            'hora_hallazgo' => '16:00:00',
            'area_zona' => 'Cafetería',
            'lugar_hallazgo' => 'Mesa exterior de cafetería cerca de jardineras',
            'categoria' => 'Electrónica y Accesorios',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'situacion_resguardo' => 'En posesión del autor',
            'devolucion_en_proceso' => false,
            'estado_devolucion' => null,
            'fecha_captura' => $fechaAprobacion3->copy()->subDays(1),
            'fecha_envio_revision' => $fechaAprobacion3->copy()->subDays(1),
            'fecha_primera_aprobacion' => $fechaAprobacion3,
            'fecha_aprobacion' => $fechaAprobacion3,
            'fecha_vencimiento' => $fechaVencimiento3,
            'version' => 1,
        ]);

        Fotografia::create([
            'publicacion_id' => $rep3->id,
            'ruta' => $fotosMuestra[2],
            'nombre_original' => 'audifonos_sony.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 56650,
            'es_principal' => true,
        ]);

        DecisionModeracion::create([
            'publicacion_id' => $rep3->id,
            'user_id' => $admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_APROBADA,
            'motivo' => 'Publicación aprobada institucionalmente.',
            'fecha_decision' => $fechaAprobacion3,
        ]);

        // ---------------------------------------------------------------------------------
        // REPORTE 4: Tableta Samsung Galaxy Tab (Bajo resguardo en biblioteca, anual VIGENTE)
        // Permite probar Inventario (16.2) y el botón «Cambiar ubicación» (16.3)
        // ---------------------------------------------------------------------------------
        $fechaAprobacion4 = $ahora->copy()->subDays(35);
        $fechaVencimiento4 = $fechaAprobacion4->copy()->addDays(14); // Venció hace 21 días
        $fechaRecepcion4 = $ahora->copy()->subDays(15);

        $rep4 = Publicacion::create([
            'user_id' => $autor->id,
            'nombre' => 'Tableta Samsung Galaxy Tab A8',
            'descripcion' => 'Tableta gris oscuro con funda azul marino y protector de pantalla, recibida físicamente en biblioteca tras vencimiento.',
            'fecha_hallazgo' => $fechaAprobacion4->copy()->subDays(2)->toDateString(),
            'hora_hallazgo' => '11:00:00',
            'area_zona' => 'Edificio D',
            'lugar_hallazgo' => 'Aula magna D-101',
            'categoria' => 'Electrónica y Accesorios',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'situacion_resguardo' => 'En biblioteca',
            'devolucion_en_proceso' => false,
            'estado_devolucion' => null,
            'fecha_captura' => $fechaAprobacion4->copy()->subDays(1),
            'fecha_envio_revision' => $fechaAprobacion4->copy()->subDays(1),
            'fecha_primera_aprobacion' => $fechaAprobacion4,
            'fecha_aprobacion' => $fechaAprobacion4,
            'fecha_vencimiento' => $fechaVencimiento4,
            'version' => 1,
        ]);

        Fotografia::create([
            'publicacion_id' => $rep4->id,
            'ruta' => $fotosMuestra[3],
            'nombre_original' => 'samsung_tablet.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 69066,
            'es_principal' => true,
        ]);

        Recepcion::create([
            'publicacion_id' => $rep4->id,
            'user_id' => $admin->id,
            'fecha_recepcion' => $fechaRecepcion4->toDateString(),
            'hora_recepcion' => '11:30',
            'fecha_captura' => $fechaRecepcion4->copy()->setTime(12, 0, 0),
            'condicion_objeto' => 'Tableta encendiendo correctamente, pantalla en buen estado con leves rayones en la funda exterior.',
            'ubicacion_resguardo' => 'Estante Tecnológico A-2, Gaveta 3',
            'observaciones' => 'Entregada por el autor tras vencimiento del plazo en muro público.',
            'confirmacion_cotejo' => true,
            'fecha_limite_anual' => Recepcion::calcularFechaLimiteAnual($fechaRecepcion4->toDateString(), '11:30')->toDateString(),
        ]);

        DecisionModeracion::create([
            'publicacion_id' => $rep4->id,
            'user_id' => $admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_RECEPCION_BIBLIOTECA,
            'motivo' => 'Recepción física registrada en biblioteca. Ubicación asignada: Estante Tecnológico A-2, Gaveta 3. Condición: Tableta encendiendo correctamente.',
            'fecha_decision' => $fechaRecepcion4,
        ]);

        // ---------------------------------------------------------------------------------
        // REPORTE 5: Termo Metálico Stanley (Bajo resguardo en biblioteca, anual VENCIDO)
        // Permite probar el estado automático «Resguardo vencido» en el Inventario (16.3)
        // ---------------------------------------------------------------------------------
        $fechaRecepcion5 = $ahora->copy()->subYear()->subDays(15); // Recibido hace 1 año y 15 días
        $fechaAprobacion5 = $fechaRecepcion5->copy()->subDays(20);
        $fechaVencimiento5 = $fechaAprobacion5->copy()->addDays(14);

        $rep5 = Publicacion::create([
            'user_id' => $autor->id,
            'nombre' => 'Termo Metálico Stanley Verde 1.2L',
            'descripcion' => 'Termo térmico color verde militar con asa plástica y pajilla rígida, bajo custodia institucional de biblioteca.',
            'fecha_hallazgo' => $fechaAprobacion5->copy()->subDays(2)->toDateString(),
            'hora_hallazgo' => '09:00:00',
            'area_zona' => 'Biblioteca Central',
            'lugar_hallazgo' => 'Mesa de consulta general 3',
            'categoria' => 'Objetos Personales',
            'estado' => Publicacion::ESTADO_APROBADA,
            'disponible' => false,
            'situacion_resguardo' => 'En biblioteca',
            'devolucion_en_proceso' => false,
            'estado_devolucion' => null,
            'fecha_captura' => $fechaAprobacion5->copy()->subDays(1),
            'fecha_envio_revision' => $fechaAprobacion5->copy()->subDays(1),
            'fecha_primera_aprobacion' => $fechaAprobacion5,
            'fecha_aprobacion' => $fechaAprobacion5,
            'fecha_vencimiento' => $fechaVencimiento5,
            'version' => 1,
        ]);

        Fotografia::create([
            'publicacion_id' => $rep5->id,
            'ruta' => $fotosMuestra[4],
            'nombre_original' => 'termo_stanley.jpg',
            'mime_type' => 'image/jpeg',
            'tamano_bytes' => 4491,
            'es_principal' => true,
        ]);

        Recepcion::create([
            'publicacion_id' => $rep5->id,
            'user_id' => $admin->id,
            'fecha_recepcion' => $fechaRecepcion5->toDateString(),
            'hora_recepcion' => '09:00',
            'fecha_captura' => $fechaRecepcion5->copy()->setTime(9, 30, 0),
            'condicion_objeto' => 'Termo limpio con pequeñas marcas de raspaduras en la base metálica.',
            'ubicacion_resguardo' => 'Estante Histórico C-1, Casillero Inferior',
            'observaciones' => 'Resguardo prolongado en biblioteca. Periodo anual institucional concluido.',
            'confirmacion_cotejo' => true,
            'fecha_limite_anual' => Recepcion::calcularFechaLimiteAnual($fechaRecepcion5->toDateString(), '09:00')->toDateString(),
        ]);

        DecisionModeracion::create([
            'publicacion_id' => $rep5->id,
            'user_id' => $admin->id,
            'tipo_decision' => DecisionModeracion::TIPO_RECEPCION_BIBLIOTECA,
            'motivo' => 'Recepción física registrada en biblioteca. Ubicación asignada: Estante Histórico C-1, Casillero Inferior.',
            'fecha_decision' => $fechaRecepcion5,
        ]);

        $this->command?->info('¡5 reportes vencidos creados exitosamente para pruebas de HU-16!');
    }
}

