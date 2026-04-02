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
        Schema::create('reglas_liquidaciones', function (Blueprint $table) {
            $table->id();
            // Vinculamos la regla directamente al producto
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            
            $table->integer('dias_anticipacion')->comment('Cuántos días antes de vencer aplica');
            $table->decimal('porcentaje_descuento', 5, 2); // Ej: 15.50 para 15.5%
            $table->boolean('estado')->default(true); // El famoso Toggle
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regla_liquidacions');
    }
};
