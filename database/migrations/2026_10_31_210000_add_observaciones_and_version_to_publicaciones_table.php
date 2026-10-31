<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * HU-07 / Tarea 07.2: Agregar campo observaciones de corrección y mecanismo de versión
     * para detección de cambios concurrentes en la moderación administrativa.
     */
    public function up(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('publicaciones', 'observaciones')) {
                $table->text('observaciones')->nullable()->after('estado');
            }
            if (! Schema::hasColumn('publicaciones', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('observaciones');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('publicaciones', function (Blueprint $table) {
            if (Schema::hasColumn('publicaciones', 'version')) {
                $table->dropColumn('version');
            }
            if (Schema::hasColumn('publicaciones', 'observaciones')) {
                $table->dropColumn('observaciones');
            }
        });
    }
};

