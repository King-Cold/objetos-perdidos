<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * HU-07 / Tarea 07.5: Registro de folio de referencia duplicado para cancelaciones administrativas.
     */
    public function up(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            $table->string('folio_referencia_duplicado', 50)->nullable()->after('observaciones');
        });

        Schema::table('decisiones_moderacion', function (Blueprint $table) {
            $table->string('folio_referencia_duplicado', 50)->nullable()->after('motivo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            $table->dropColumn('folio_referencia_duplicado');
        });

        Schema::table('decisiones_moderacion', function (Blueprint $table) {
            $table->dropColumn('folio_referencia_duplicado');
        });
    }
};

