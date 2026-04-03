<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferencia_sugeridas', function (Blueprint $table) {
            $table->id();
            // Referencias a la tabla branches (sucursales)
            $table->foreignId('origen_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('destino_id')->constrained('branches')->onDelete('cascade');
            // Referencia al producto
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            
            $table->integer('cantidad'); // Cuántas unidades sugerimos mover
            
            // El estado de la sugerencia
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencia_sugeridas');
    }
};