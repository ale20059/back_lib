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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->enum('type', ['in', 'out']); // entrada o salida
            $table->integer('quantity');
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->text('reason')->nullable(); // compra, ajuste, venta, merma
            $table->foreignId('user_id')->constrained(); // quién registró
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
