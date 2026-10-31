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
            if (! Schema::hasColumn('publicaciones', 'folio')) {
                $table->string('folio', 35)->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('publicaciones', 'nombre')) {
                $table->string('nombre', 150)->nullable()->after('folio');
            }
            if (! Schema::hasColumn('publicaciones', 'fecha_hallazgo')) {
                $table->date('fecha_hallazgo')->nullable()->after('descripcion');
            }
            if (! Schema::hasColumn('publicaciones', 'hora_hallazgo')) {
                $table->string('hora_hallazgo', 8)->nullable()->after('fecha_hallazgo');
            }
            if (! Schema::hasColumn('publicaciones', 'area_zona')) {
                $table->string('area_zona', 100)->nullable()->after('hora_hallazgo');
            }
            if (! Schema::hasColumn('publicaciones', 'ubicacion_especifica')) {
                $table->text('ubicacion_especifica')->nullable()->after('area_zona');
            }
            if (! Schema::hasColumn('publicaciones', 'fecha_captura')) {
                $table->dateTime('fecha_captura')->nullable()->after('ubicacion_especifica');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['folio', 'nombre', 'fecha_hallazgo', 'hora_hallazgo', 'area_zona', 'ubicacion_especifica', 'fecha_captura'] as $col) {
                if (Schema::hasColumn('publicaciones', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};

