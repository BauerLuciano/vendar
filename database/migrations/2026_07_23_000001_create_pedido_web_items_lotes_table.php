<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_web_items_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_web_item_id')->constrained('pedido_web_items')->restrictOnDelete();
            $table->foreignId('lote_id')->constrained('lotes')->restrictOnDelete();
            $table->decimal('cantidad', 10, 2);
            $table->timestamps();

            $table->unique(['pedido_web_item_id', 'lote_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_web_items_lotes');
    }
};
