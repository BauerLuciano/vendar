<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingreso_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingreso_mercaderia_id')->constrained('ingresos_mercaderias')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            
            $table->integer('cantidad_recibida');
            $table->decimal('costo_unitario', 10, 2); // A cuánto nos cobró el proveedor hoy
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingreso_detalles');
    }
};