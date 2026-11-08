<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('publicaciones')) {
            Schema::table('publicaciones', function (Blueprint $table) {
                if (!Schema::hasColumn('publicaciones', 'publicacion_excepcional_autorizada')) {
                    $table->boolean('publicacion_excepcional_autorizada')
                        ->default(false)
                        ->after('tiene_incidencia');
                }

                if (!Schema::hasColumn('publicaciones', 'fecha_publicacion_excepcional')) {
                    $table->dateTime('fecha_publicacion_excepcional')
                        ->nullable()
                        ->after('publicacion_excepcional_autorizada');
                }

                if (!Schema::hasColumn('publicaciones', 'fecha_vencimiento_excepcional')) {
                    $table->dateTime('fecha_vencimiento_excepcional')
                        ->nullable()
                        ->after('fecha_publicacion_excepcional');
                }

                if (!Schema::hasColumn('publicaciones', 'autorizada_excepcional_por_id')) {
                    $table->foreignId('autorizada_excepcional_por_id')
                        ->nullable()
                        ->after('fecha_vencimiento_excepcional')
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('publicaciones', 'motivo_publicacion_excepcional')) {
                    $table->text('motivo_publicacion_excepcional')
                        ->nullable()
                        ->after('autorizada_excepcional_por_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('publicaciones')) {
            Schema::table('publicaciones', function (Blueprint $table) {
                if (Schema::hasColumn('publicaciones', 'autorizada_excepcional_por_id')) {
                    $table->dropConstrainedForeignId('autorizada_excepcional_por_id');
                }

                $columns = [
                    'publicacion_excepcional_autorizada',
                    'fecha_publicacion_excepcional',
                    'fecha_vencimiento_excepcional',
                    'motivo_publicacion_excepcional',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('publicaciones', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};

