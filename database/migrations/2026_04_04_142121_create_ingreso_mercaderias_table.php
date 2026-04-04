<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingresos_mercaderias', function (Blueprint $table) {
            $table->id();
            // Asumo que tenés una tabla proveedores (si no la tenés, sacale el constrained por ahora)
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            
            $table->string('numero_remito')->nullable();
            $table->date('fecha_ingreso');
            $table->decimal('total_costo', 12, 2)->default(0);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingresos_mercaderias');
    }
};