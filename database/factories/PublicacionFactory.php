<?php

namespace Database\Factories;

use App\Models\Publicacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Publicacion>
 */
class PublicacionFactory extends Factory
{
    protected $model = Publicacion::class;

    public function definition(): array
    {
        $now = Carbon::now(Publicacion::TIMEZONE);
        $titulo = fake()->sentence(3);

        return [
            'user_id' => User::factory(),
            'folio' => 'OBJ-' . $now->format('Ymd') . '-' . fake()->unique()->numerify('####'),
            'nombre' => $titulo,
            'titulo' => $titulo,
            'descripcion' => 'Objeto encontrado en instalaciones del campus en buen estado de conservación general.',
            'fecha_hallazgo' => $now->format('Y-m-d'),
            'hora_hallazgo' => '10:00',
            'area_zona' => 'Biblioteca Campus Poniente',
            'ubicacion_especifica' => 'Mesa de estudio central',
            'fecha_captura' => $now,
            'fecha_envio_revision' => $now,
            'categoria' => 'Electrónicos',
            'lugar_hallazgo' => 'Biblioteca Campus Poniente - Mesa de estudio central',
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
            'disponible' => false,
            'estado' => 'Pendiente de aprobación',
            'fecha_primera_aprobacion' => null,
            'fecha_vencimiento' => null,
        ]);
    }

    public function requiereCorreccion(string $motivo = 'Favor de especificar con mayor detalle la ubicación del hallazgo'): static
    {
        return $this->state(fn () => [
            'disponible' => false,
            'estado' => 'Requiere corrección',
            'motivo_correccion' => $motivo,
            'observaciones_admin' => $motivo,
            'fecha_primera_aprobacion' => null,
            'fecha_vencimiento' => null,
        ]);
    }

    public function cancelado(string $motivo = 'Reporte duplicado por error'): static
    {
        return $this->state(fn () => [
            'disponible' => false,
            'estado' => 'Cancelado',
            'motivo_cancelacion' => $motivo,
            'fecha_cancelacion' => Carbon::now(Publicacion::TIMEZONE),
        ]);
    }

    public function bajoResguardo(): static
    {
        return $this->state(fn () => [
            'situacion_resguardo' => Publicacion::RESGUARDO_CONFIRMADO,
        ]);
    }

    public function expirado(): static
    {
        $pastApproval = Carbon::now(Publicacion::TIMEZONE)->subDays(20);
        return $this->state(fn () => [
            'disponible' => false,
            'estado' => 'Aprobada',
            'fecha_primera_aprobacion' => $pastApproval,
            'fecha_vencimiento' => $pastApproval->copy()->addDays(14),
        ]);
    }
}
