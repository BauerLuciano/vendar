<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sucursal_producto');

        Schema::create('sucursal_producto', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('producto_id')->constrained('productos');
            
            $table->integer('cantidad_fisica')->default(0);
            $table->integer('cantidad_reservada')->default(0);

            $table->unique(['sucursal_id', 'producto_id'], 'uidx_sucursal_producto_unique');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursal_producto');
    }
};