<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('unidad_compra')->nullable()->after('unidad_medida');
            $table->decimal('cantidad_por_compra', 8, 2)->nullable()->after('unidad_compra');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['unidad_compra', 'cantidad_por_compra']);
        });
    }
};
