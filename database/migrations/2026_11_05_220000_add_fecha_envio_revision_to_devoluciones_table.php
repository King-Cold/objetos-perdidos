<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * HU-12 Tarea 1: Fecha y hora del registro o último reenvío a revisión para la cola administrativa.
     */
    public function up(): void
    {
        Schema::table('devoluciones', function (Blueprint $table) {
            $table->dateTime('fecha_envio_revision')->nullable()->after('fecha_captura');
        });

        // Inicializar con la fecha de captura existente para registros previos
        DB::table('devoluciones')->whereNull('fecha_envio_revision')->update([
            'fecha_envio_revision' => DB::raw('fecha_captura'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devoluciones', function (Blueprint $table) {
            $table->dropColumn('fecha_envio_revision');
        });
    }
};

