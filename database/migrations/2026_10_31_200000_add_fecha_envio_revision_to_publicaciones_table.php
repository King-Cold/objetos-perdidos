<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * HU-07 / Tarea 07.1: Incorporar fecha_envio_revision para diferenciar
     * la fecha de captura original de la fecha y hora del último envío/reenvío a revisión.
     */
    public function up(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('publicaciones', 'fecha_envio_revision')) {
                $table->dateTime('fecha_envio_revision')->nullable()->after('fecha_captura');
            }
        });

        // Población retroactiva respetando los datos y fechas existentes
        if (Schema::hasColumn('publicaciones', 'fecha_envio_revision')) {
            DB::statement("UPDATE publicaciones SET fecha_envio_revision = COALESCE(fecha_captura, created_at, CURRENT_TIMESTAMP) WHERE fecha_envio_revision IS NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            if (Schema::hasColumn('publicaciones', 'fecha_envio_revision')) {
                $table->dropColumn('fecha_envio_revision');
            }
        });
    }
};

