<?php

namespace Database\Seeders;

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
        User::firstOrCreate(
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
        User::firstOrCreate(
            ['email' => 'usuario@merida.tecnm.mx'],
            [
                'name' => 'Usuario General Campus Poniente',
                'phone' => '9991234567',
                'password' => Hash::make('UsuarioTecNM2026!'),
                'role' => User::ROLE_USUARIO_GENERAL,
                'email_verified_at' => now(),
            ]
        );
    }
}
