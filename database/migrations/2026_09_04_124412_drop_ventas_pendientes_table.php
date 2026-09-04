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
        Schema::dropIfExists('ventas_pendientes');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('ventas_pendientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('turno_caja_id')->constrained('turno_cajas');
            $table->foreignId('consumidor_id')->nullable()->constrained('consumidores');
            $table->json('items');
            $table->decimal('total', 12, 2);
            $table->string('estado')->default('activa');
            $table->timestamps();
        });
    }
};
