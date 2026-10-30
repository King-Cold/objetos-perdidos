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
        if (! Schema::hasTable('publicaciones')) {
            Schema::create('publicaciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('titulo', 150);
                $table->text('descripcion');
                $table->string('categoria', 60)->default('General');
                $table->string('lugar_hallazgo', 100)->nullable();
                $table->string('estado', 50)->default('Aprobada');
                $table->boolean('disponible')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publicaciones');
    }
};

