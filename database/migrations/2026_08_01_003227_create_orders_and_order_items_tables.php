<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla principal de Pedidos/Órdenes
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // Ej: ORD-2026-0001
            $table->foreignId('created_by_user_id')->constrained('users'); // Usuario autenticado que crea la orden
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users'); // Usuario asignado a la orden
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->string('destination')->nullable(); // Ej: Salón 101, Oficina
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Detalle de ítems de la orden (vinculado a Productos Internos o Ventas)
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');

            // 💡 Agregamos sale_id y hacemos que internal_product_id sea nullable
            $table->foreignId('sale_id')->nullable()->constrained('sales')->onDelete('set null');
            $table->foreignId('internal_product_id')->nullable()->constrained('internal_products')->onDelete('set null');

            $table->integer('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
