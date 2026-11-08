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
        Schema::create('recepciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publicacion_id')->unique()->constrained('publicaciones')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->date('fecha_recepcion');
            $table->string('hora_recepcion', 10);
            $table->dateTime('fecha_captura');
            $table->text('condicion_objeto');
            $table->string('ubicacion_resguardo', 255);
            $table->text('observaciones')->nullable();
            $table->boolean('confirmacion_cotejo')->default(true);
            $table->date('fecha_limite_anual');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recepciones');
    }
};

