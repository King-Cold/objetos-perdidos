<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Configuración de la cuenta administrativa inicial (CA-10).
     */
    public function run(): void
    {
        $adminEmail = env('ADMIN_DEFAULT_EMAIL', 'admin@merida.tecnm.mx');
        $adminPassword = env('ADMIN_DEFAULT_PASSWORD', 'AdminTecNM2026!');

        User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Administrador Institucional Biblioteca',
                'phone' => '9999000000',
                'password' => Hash::make($adminPassword),
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
            ]
        );
    }
}

