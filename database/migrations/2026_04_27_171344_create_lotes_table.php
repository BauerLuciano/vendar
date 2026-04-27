<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            $table->foreignId('sucursal_id')->constrained('sucursales')->onDelete('cascade');
            
            // Datos del Vencimiento
            $table->date('fecha_vencimiento');
            
            // Control de Stock (Usamos decimal por si vendés cosas por Kilo)
            $table->decimal('stock_inicial', 10, 2);
            $table->decimal('stock_actual', 10, 2);
            
            // Bandera del Robot Liquidador
            $table->boolean('estado_liquidacion')->default(false);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lotes');
    }
};