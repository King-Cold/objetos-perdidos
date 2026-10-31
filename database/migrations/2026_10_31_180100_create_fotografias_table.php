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
        if (! Schema::hasTable('fotografias')) {
            Schema::create('fotografias', function (Blueprint $table) {
                $table->id();
                $table->foreignId('publicacion_id')->constrained('publicaciones')->cascadeOnDelete();
                $table->string('ruta', 255);
                $table->string('nombre_original', 255)->nullable();
                $table->string('mime_type', 50)->nullable();
                $table->unsignedBigInteger('tamano_bytes')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fotografias');
    }
};

