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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('barcode')->unique(); // para escáner o cámara
            $table->foreignId('supplier_id')->constrained()->onDelete('restrict');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('purchase_price', 10, 2); // precio de compra
            $table->decimal('selling_price', 10, 2);  // precio de venta
            $table->integer('stock')->default(0);
            $table->string('location')->nullable(); // estante, pasillo, vitrina
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
