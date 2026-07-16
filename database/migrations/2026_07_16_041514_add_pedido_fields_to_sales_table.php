<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('grado')->nullable()->after('payment_method');
            $table->string('estudiante')->nullable()->after('grado');
            $table->string('talla')->nullable()->after('estudiante');
            $table->string('boleta')->nullable()->after('talla');
            $table->string('quien_entrego')->nullable()->after('boleta');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['grado', 'estudiante', 'talla', 'boleta', 'quien_entrego']);
        });
    }
};
