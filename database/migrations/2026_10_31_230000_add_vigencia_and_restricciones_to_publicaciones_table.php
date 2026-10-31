<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * HU-07 / Tarea 07.4: Vigencia de publicación de 14 días y restricciones de disponibilidad.
     */
    public function up(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            $table->timestamp('fecha_primera_aprobacion')->nullable()->after('fecha_envio_revision');
            $table->timestamp('fecha_aprobacion')->nullable()->after('fecha_primera_aprobacion');
            $table->timestamp('fecha_vencimiento')->nullable()->after('fecha_aprobacion');
            $table->boolean('devolucion_en_proceso')->default(false)->after('disponible');
            $table->boolean('incidencia_abierta')->default(false)->after('devolucion_en_proceso');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            $table->dropColumn([
                'fecha_primera_aprobacion',
                'fecha_aprobacion',
                'fecha_vencimiento',
                'devolucion_en_proceso',
                'incidencia_abierta',
            ]);
        });
    }
};

