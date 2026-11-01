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
        Schema::table('publicaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('publicaciones', 'fecha_primera_aprobacion')) {
                $table->dateTime('fecha_primera_aprobacion')->nullable()->after('fecha_captura');
            }
            if (! Schema::hasColumn('publicaciones', 'fecha_vencimiento')) {
                $table->dateTime('fecha_vencimiento')->nullable()->after('fecha_primera_aprobacion');
            }
            if (! Schema::hasColumn('publicaciones', 'motivo_correccion')) {
                $table->text('motivo_correccion')->nullable()->after('fecha_vencimiento');
            }
            if (! Schema::hasColumn('publicaciones', 'observaciones_admin')) {
                $table->text('observaciones_admin')->nullable()->after('motivo_correccion');
            }
            if (! Schema::hasColumn('publicaciones', 'situacion_resguardo')) {
                $table->string('situacion_resguardo', 80)->default('En posesión del autor')->after('observaciones_admin');
            }
            if (! Schema::hasColumn('publicaciones', 'motivo_cancelacion')) {
                $table->text('motivo_cancelacion')->nullable()->after('situacion_resguardo');
            }
            if (! Schema::hasColumn('publicaciones', 'fecha_cancelacion')) {
                $table->dateTime('fecha_cancelacion')->nullable()->after('motivo_cancelacion');
            }
            if (! Schema::hasColumn('publicaciones', 'estado_devolucion')) {
                $table->string('estado_devolucion', 60)->nullable()->after('fecha_cancelacion');
            }
            if (! Schema::hasColumn('publicaciones', 'tiene_incidencia')) {
                $table->boolean('tiene_incidencia')->default(false)->after('estado_devolucion');
            }
        });

        if (Schema::hasTable('fotografias')) {
            Schema::table('fotografias', function (Blueprint $table) {
                if (! Schema::hasColumn('fotografias', 'es_principal')) {
                    $table->boolean('es_principal')->default(false)->after('tamano_bytes');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            $cols = [
                'fecha_primera_aprobacion',
                'fecha_vencimiento',
                'motivo_correccion',
                'observaciones_admin',
                'situacion_resguardo',
                'motivo_cancelacion',
                'fecha_cancelacion',
                'estado_devolucion',
                'tiene_incidencia',
            ];
            $existing = [];
            foreach ($cols as $col) {
                if (Schema::hasColumn('publicaciones', $col)) {
                    $existing[] = $col;
                }
            }
            if (! empty($existing)) {
                $table->dropColumn($existing);
            }
        });

        if (Schema::hasTable('fotografias')) {
            Schema::table('fotografias', function (Blueprint $table) {
                if (Schema::hasColumn('fotografias', 'es_principal')) {
                    $table->dropColumn('es_principal');
                }
            });
        }
    }
};

