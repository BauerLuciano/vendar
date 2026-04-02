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
            $table->foreignId('proveedor_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('sucursal_id')->constrained('branches')->restrictOnDelete();
            $table->date('fecha_comprobante');
            $table->decimal('total_factura', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingresos_mercaderias');
    }
};