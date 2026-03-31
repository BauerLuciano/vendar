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
    Schema::table('productos', function (Blueprint $table) {
        // Agregamos la columna, por defecto en 5, justo después del precio_venta
        $table->integer('stock_minimo')->default(5)->after('precio_venta');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            //
        });
    }
};
