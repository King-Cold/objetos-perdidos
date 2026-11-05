<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * HU-11 Tarea 3: Tabla para persistir devoluciones físicas documentadas por el autor.
     */
    public function up(): void
    {
        Schema::create('devoluciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publicacion_id')->constrained('publicaciones')->cascadeOnDelete();
            $table->foreignId('user_id')->comment('Autor responsable que entrega el objeto')->constrained('users')->cascadeOnDelete();
            
            // CA-03, CA-04: Datos del receptor
            $table->string('receptor_nombre', 150);
            $table->string('receptor_identificador', 50);
            
            // CA-03, CA-05: Momentos desacoplados
            $table->date('fecha_entrega');
            $table->time('hora_entrega');
            $table->dateTime('fecha_captura')->comment('Momento en que se registró en el sistema');
            
            // CA-03, CA-04: Explicación de comprobación de propiedad
            $table->text('explicacion_propiedad');
            
            // CA-06: Fotografía de evidencia privada
            $table->string('fotografia_ruta', 255);
            $table->string('fotografia_nombre_original', 255)->nullable();
            $table->string('fotografia_mime_type', 100)->nullable();
            $table->unsignedInteger('fotografia_tamano_bytes')->nullable();
            
            // CA-07, CA-08: Estado del registro
            $table->string('estado', 60)->default('Pendiente de verificación');
            
            $table->timestamps();

            // Índice para optimizar búsquedas y trazabilidad
            $table->index(['publicacion_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devoluciones');
    }
};
