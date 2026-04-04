<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_sucursal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            
            // Acá vive la verdad del kiosco:
            $table->integer('cantidad_fisica')->default(0); 
            $table->integer('cantidad_reservada')->default(0); // Para pedidos web pendientes
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_sucursal');
    }
};