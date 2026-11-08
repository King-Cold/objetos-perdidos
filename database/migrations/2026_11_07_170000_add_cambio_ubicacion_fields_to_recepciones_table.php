<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recepciones', function (Blueprint $table) {
            $table->foreignId('actualizado_por_id')->nullable()->after('confirmacion_cotejo')->constrained('users')->nullOnDelete();
            $table->dateTime('fecha_cambio_ubicacion')->nullable()->after('actualizado_por_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recepciones', function (Blueprint $table) {
            $table->dropForeign(['actualizado_por_id']);
            $table->dropColumn(['actualizado_por_id', 'fecha_cambio_ubicacion']);
        });
    }
};

