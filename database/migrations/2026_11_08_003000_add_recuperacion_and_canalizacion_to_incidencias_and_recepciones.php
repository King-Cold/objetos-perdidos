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
        if (Schema::hasTable('incidencias')) {
            Schema::table('incidencias', function (Blueprint $table) {
                if (!Schema::hasColumn('incidencias', 'recepcion_id')) {
                    $table->foreignId('recepcion_id')->nullable()->after('devolucion_id')->constrained('recepciones')->nullOnDelete();
                }
                if (!Schema::hasColumn('incidencias', 'recuperacion_confirmada')) {
                    $table->boolean('recuperacion_confirmada')->default(false)->after('solicitud_recuperacion');
                }
                if (!Schema::hasColumn('incidencias', 'fecha_recuperacion')) {
                    $table->dateTime('fecha_recuperacion')->nullable()->after('fecha_revision');
                }
                if (!Schema::hasColumn('incidencias', 'responsable_canalizacion')) {
                    $table->string('responsable_canalizacion')->nullable()->after('recuperacion_confirmada');
                }
                if (!Schema::hasColumn('incidencias', 'observaciones_seguimiento')) {
                    $table->text('observaciones_seguimiento')->nullable()->after('responsable_canalizacion');
                }
            });
        }

        if (Schema::hasTable('recepciones')) {
            Schema::table('recepciones', function (Blueprint $table) {
                if (!Schema::hasColumn('recepciones', 'incidencia_id')) {
                    $table->foreignId('incidencia_id')->nullable()->after('publicacion_id')->constrained('incidencias')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('recepciones') && Schema::hasColumn('recepciones', 'incidencia_id')) {
            Schema::table('recepciones', function (Blueprint $table) {
                $table->dropConstrainedForeignId('incidencia_id');
            });
        }

        if (Schema::hasTable('incidencias')) {
            Schema::table('incidencias', function (Blueprint $table) {
                if (Schema::hasColumn('incidencias', 'recepcion_id')) {
                    $table->dropConstrainedForeignId('recepcion_id');
                }
                foreach (['recuperacion_confirmada', 'fecha_recuperacion', 'responsable_canalizacion', 'observaciones_seguimiento'] as $col) {
                    if (Schema::hasColumn('incidencias', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};

