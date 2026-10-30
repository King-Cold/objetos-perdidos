<?php

namespace Database\Factories;

use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Publicacion>
 */
class PublicacionFactory extends Factory
{
    protected $model = Publicacion::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'titulo' => fake()->sentence(3),
            'descripcion' => fake()->paragraph(),
            'categoria' => 'Electrónicos',
            'lugar_hallazgo' => 'Biblioteca Campus Poniente',
            'estado' => 'Aprobada',
            'disponible' => true,
        ];
    }

    public function noDisponible(): static
    {
        return $this->state(fn () => [
            'disponible' => false,
            'estado' => 'Devuelto',
        ]);
    }

    public function pendiente(): static
    {
        return $this->state(fn () => [
            'disponible' => true,
            'estado' => 'Pendiente de aprobación',
        ]);
    }
}

