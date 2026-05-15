<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_web_items', function (Blueprint $table) {
            $table->id();
            
            // Relacionamos con el pedido y con el producto de tu catálogo
            $table->foreignId('pedido_web_id')->constrained('pedidos_web')->cascadeOnDelete();
            // Asumo que tu tabla de productos se llama 'productos'. Cambialo si se llama distinto.
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete(); 
            
            // Congelamos el precio al momento de la compra (por si mañana aumenta, que no cambie el historial)
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_web_items');
    }
};