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
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->string('url'); // ruta de la imagen
            $table->string('thumbnail_url')->nullable(); // miniatura
            $table->string('file_name')->nullable();
            $table->integer('file_size')->nullable();
            $table->string('mime_type')->nullable();

            // Relación polimórfica
            $table->morphs('imageable'); // imageable_id + imageable_type

            $table->boolean('is_main')->default(false); // imagen principal
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
