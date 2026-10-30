<?php

namespace Database\Seeders;

use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Cuenta de Administrador/bibliotecario
        $admin = User::firstOrCreate(
            ['email' => 'admin@merida.tecnm.mx'],
            [
                'name' => 'Administrador Institucional Biblioteca',
                'phone' => '9999000000',
                'password' => Hash::make('AdminTecNM2026!'),
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
            ]
        );

        // 2. Cuenta de Usuario general
        $usuario = User::firstOrCreate(
            ['email' => 'usuario@merida.tecnm.mx'],
            [
                'name' => 'Usuario General Campus Poniente',
                'phone' => '9991234567',
                'password' => Hash::make('UsuarioTecNM2026!'),
                'role' => User::ROLE_USUARIO_GENERAL,
                'email_verified_at' => now(),
            ]
        );

        // 3. Publicaciones de ejemplo en el muro
        Publicacion::firstOrCreate(
            ['titulo' => 'Calculadora Científica Casio fx-991LAX'],
            [
                'user_id' => $admin->id,
                'descripcion' => 'Encontrada en las mesas de estudio de la Biblioteca Campus Poniente.',
                'categoria' => 'Electrónicos',
                'lugar_hallazgo' => 'Biblioteca Campus Poniente',
                'estado' => 'Aprobada',
                'disponible' => true,
            ]
        );

        Publicacion::firstOrCreate(
            ['titulo' => 'Credencial Institucional TecNM'],
            [
                'user_id' => $admin->id,
                'descripcion' => 'Extraviada cerca de los laboratorios de cómputo del edificio C.',
                'categoria' => 'Documentos',
                'lugar_hallazgo' => 'Edificio C',
                'estado' => 'Aprobada',
                'disponible' => true,
            ]
        );
    }
}
