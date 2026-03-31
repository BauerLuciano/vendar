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
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            
            // Relaciones (Claves Foráneas)
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->foreignId('marca_id')->constrained('marcas')->restrictOnDelete();
            
            // Datos del producto
            $table->string('nombre');
            $table->string('sku')->unique(); // ¡Acá cumplimos el ítem 4 del checklist a nivel base de datos!
            $table->text('descripcion')->nullable();
            
            // Precios (decimal con 10 dígitos en total y 2 decimales)
            $table->decimal('precio_costo', 10, 2);
            $table->decimal('precio_venta', 10, 2);
            
            // Imagen que pide el ítem 3
            $table->string('imagen')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
