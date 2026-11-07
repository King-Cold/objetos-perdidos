<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * HU: Avisos — Task 1: Estructura de almacenamiento de avisos de procesos.
     */
    public function up(): void
    {
        Schema::create('avisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('publicacion_id')->nullable()->constrained('publicaciones')->nullOnDelete();
            $table->foreignId('devolucion_id')->nullable()->constrained('devoluciones')->nullOnDelete();
            
            // Metadatos del evento institucional
            $table->string('tipo_evento', 60); // ej. publicacion_aprobada, publicacion_correccion, etc.
            $table->string('titulo', 150);
            $table->string('folio_reporte', 50)->nullable();
            
            // Contenido histórico descriptivo
            $table->text('mensaje');
            $table->text('motivo_autorizado')->nullable();
            $table->string('enlace_destino', 255)->nullable();
            
            // Estado de lectura
            $table->boolean('leido')->default(false);
            $table->dateTime('leido_at')->nullable();
            
            // Preparación para idempotencia y prevención de duplicados (Task 4)
            $table->string('clave_duplicado', 100)->nullable()->unique();
            
            $table->timestamps();

            // Índices para optimizar la consulta cronológica y por usuario
            $table->index(['user_id', 'leido', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avisos');
    }
};

