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
        Schema::create('internal_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_product_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['add', 'use']); // add = agregar stock, use = usar
            $table->integer('quantity');
            $table->string('reason')->nullable(); // Ej: "Compra", "Uso en clase A", "Mantenimiento"
            $table->string('used_by')->nullable(); // Persona que usó el producto
            $table->string('destination')->nullable(); // Ej: "Salón 101", "Oficina", etc.
            $table->integer('year')->nullable(); // Año del movimiento
            $table->foreignId('user_id')->constrained(); // Quién registró el movimiento
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_movements');
    }
};
