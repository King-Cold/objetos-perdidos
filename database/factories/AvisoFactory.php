<?php

namespace Database\Factories;

use App\Models\Aviso;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Aviso>
 */
class AvisoFactory extends Factory
{
    protected $model = Aviso::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'publicacion_id' => null,
            'devolucion_id' => null,
            'tipo_evento' => Aviso::TIPO_PUBLICACION_APROBADA,
            'titulo' => 'Publicación autorizada',
            'folio_reporte' => 'OBJ-' . date('Ymd') . '-' . fake()->numerify('####'),
            'mensaje' => 'Tu reporte de objeto encontrado ha sido verificado y publicado.',
            'motivo_autorizado' => null,
            'enlace_destino' => '/reportes/1',
            'leido' => false,
            'leido_at' => null,
            'clave_duplicado' => null,
        ];
    }

    public function leido(): static
    {
        return $this->state(fn (array $attributes) => [
            'leido' => true,
            'leido_at' => now(),
        ]);
    }

    public function noLeido(): static
    {
        return $this->state(fn (array $attributes) => [
            'leido' => false,
            'leido_at' => null,
        ]);
    }

    public function correccion(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_evento' => Aviso::TIPO_PUBLICACION_CORRECCION,
            'titulo' => 'Corrección requerida en tu reporte',
            'mensaje' => 'Se requiere corregir la información del reporte para proceder con la aprobación.',
            'motivo_autorizado' => 'Por favor especifica con mayor claridad el lugar del hallazgo.',
            'enlace_destino' => '/reportes/1/editar',
        ]);
    }

    public function vencido(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_evento' => Aviso::TIPO_PUBLICACION_VENCIDA,
            'titulo' => 'Periodo de vigencia concluido',
            'mensaje' => 'El objeto cumplió el periodo de 14 días. Debe ser entregado físicamente en la biblioteca.',
            'motivo_autorizado' => 'Plazo máximo ordinario alcanzado.',
            'enlace_destino' => '/reportes/1',
        ]);
    }
}

