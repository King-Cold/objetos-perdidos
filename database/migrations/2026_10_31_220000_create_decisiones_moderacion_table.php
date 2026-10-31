<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * HU-07 / Tarea 07.3: Historial de decisiones de moderación administrativa.
     */
    public function up(): void
    {
        Schema::create('decisiones_moderacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publicacion_id')->constrained('publicaciones')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo_decision', 50); // correccion_solicitada, aprobada, etc.
            $table->text('motivo')->nullable();
            $table->timestamp('fecha_decision');
            $table->timestamps();

            $table->index(['publicacion_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('decisiones_moderacion');
    }
};

