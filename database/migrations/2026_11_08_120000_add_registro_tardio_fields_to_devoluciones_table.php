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
        Schema::table('devoluciones', function (Blueprint $table) {
            $table->boolean('es_registro_extemporaneo')
                ->default(false)
                ->after('admin_id')
                ->comment('Indica si la devolución fue documentada fuera de tiempo por el bibliotecario');

            $table->foreignId('registrado_por_id')
                ->nullable()
                ->after('es_registro_extemporaneo')
                ->comment('Bibliotecario o administrador responsable que capturó el registro tardío')
                ->constrained('users')
                ->nullOnDelete();

            $table->text('motivo_registro_tardio')
                ->nullable()
                ->after('explicacion_propiedad')
                ->comment('Motivo obligatorio del registro tardío de la devolución');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devoluciones', function (Blueprint $table) {
            $table->dropForeign(['registrado_por_id']);
            $table->dropColumn([
                'es_registro_extemporaneo',
                'registrado_por_id',
                'motivo_registro_tardio',
            ]);
        });
    }
};

