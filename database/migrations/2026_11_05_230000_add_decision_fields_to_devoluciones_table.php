<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * HU-12 Tarea 2: Campos de resolución administrativa para devoluciones físicas.
     */
    public function up(): void
    {
        Schema::table('devoluciones', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->dateTime('fecha_resolucion')->nullable()->after('fecha_envio_revision');
            $table->text('motivo_correccion')->nullable()->after('explicacion_propiedad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devoluciones', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->dropColumn(['admin_id', 'fecha_resolucion', 'motivo_correccion']);
        });
    }
};

