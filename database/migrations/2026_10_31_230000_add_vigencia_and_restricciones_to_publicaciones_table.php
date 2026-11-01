<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('publicaciones', 'fecha_primera_aprobacion')) {
            Schema::table('publicaciones', function (Blueprint $table) {
                $table->timestamp('fecha_primera_aprobacion')
                    ->nullable();
            });
        }

        if (! Schema::hasColumn('publicaciones', 'fecha_aprobacion')) {
            Schema::table('publicaciones', function (Blueprint $table) {
                $table->timestamp('fecha_aprobacion')
                    ->nullable();
            });
        }

        if (! Schema::hasColumn('publicaciones', 'fecha_vencimiento')) {
            Schema::table('publicaciones', function (Blueprint $table) {
                $table->timestamp('fecha_vencimiento')
                    ->nullable();
            });
        }

        if (! Schema::hasColumn('publicaciones', 'devolucion_en_proceso')) {
            Schema::table('publicaciones', function (Blueprint $table) {
                $table->boolean('devolucion_en_proceso')
                    ->default(false);
            });
        }

        if (! Schema::hasColumn('publicaciones', 'incidencia_abierta')) {
            Schema::table('publicaciones', function (Blueprint $table) {
                $table->boolean('incidencia_abierta')
                    ->default(false);
            });
        }
    }

    public function down(): void
    {
        // Las fechas se conservan porque pueden haber sido creadas
        // por migraciones anteriores y contener datos existentes.

        if (Schema::hasColumn('publicaciones', 'incidencia_abierta')) {
            Schema::table('publicaciones', function (Blueprint $table) {
                $table->dropColumn('incidencia_abierta');
            });
        }

        if (Schema::hasColumn('publicaciones', 'devolucion_en_proceso')) {
            Schema::table('publicaciones', function (Blueprint $table) {
                $table->dropColumn('devolucion_en_proceso');
            });
        }
    }
};