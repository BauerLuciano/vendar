<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orden_compra_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('orden_compras')->cascadeOnDelete();
            $table->string('estado'); // Estado al que transicionó
            $table->foreignId('user_id')->constrained('users');
            $table->text('motivo')->nullable(); // Para cancelaciones/rechazos
            $table->json('detalle')->nullable(); // Info adicional (cantidades recibidas, etc.)
            $table->timestamps();

            $table->index(['orden_compra_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_compra_historial');
    }
};
