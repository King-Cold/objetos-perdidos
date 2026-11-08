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
        Schema::table('incidencias', function (Blueprint $table) {
            if (!Schema::hasColumn('incidencias', 'responsable_revision_id')) {
                $table->foreignId('responsable_revision_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('incidencias', 'cerrada_por_id')) {
                $table->foreignId('cerrada_por_id')->nullable()->after('responsable_revision_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('incidencias', 'resultado')) {
                $table->string('resultado')->nullable()->after('estado');
            }
            if (!Schema::hasColumn('incidencias', 'motivo_resolucion')) {
                $table->text('motivo_resolucion')->nullable()->after('resultado');
            }
            if (!Schema::hasColumn('incidencias', 'solicitud_recuperacion')) {
                $table->text('solicitud_recuperacion')->nullable()->after('motivo_resolucion');
            }
            if (!Schema::hasColumn('incidencias', 'fecha_revision')) {
                $table->dateTime('fecha_revision')->nullable()->after('fecha_apertura');
            }
            if (!Schema::hasColumn('incidencias', 'fecha_cierre')) {
                $table->dateTime('fecha_cierre')->nullable()->after('fecha_revision');
            }
        });

        Schema::create('incidencia_observaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incidencia_id')->constrained('incidencias')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo')->default('Aclaración');
            $table->string('medio_contacto')->nullable();
            $table->text('descripcion');
            $table->text('resultado_obtenido')->nullable();
            $table->dateTime('fecha_registro');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidencia_observaciones');

        Schema::table('incidencias', function (Blueprint $table) {
            $columns = [
                'responsable_revision_id',
                'cerrada_por_id',
                'resultado',
                'motivo_resolucion',
                'solicitud_recuperacion',
                'fecha_revision',
                'fecha_cierre',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('incidencias', $col)) {
                    if (in_array($col, ['responsable_revision_id', 'cerrada_por_id'], true)) {
                        $table->dropConstrainedForeignId($col);
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });
    }
};

