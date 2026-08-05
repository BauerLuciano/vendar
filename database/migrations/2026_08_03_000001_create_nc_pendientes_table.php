<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nc_pendientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercio_id')->constrained('comercios')->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->enum('tipo_operacion', ['anulacion', 'devolucion']);
            $table->string('motivo')->nullable();
            $table->json('items')->nullable();
            $table->decimal('monto_devuelto', 12, 2)->nullable();
            $table->enum('estado', ['pendiente', 'resuelto'])->default('pendiente');
            $table->text('motivo_fallo');
            $table->unsignedInteger('intentos')->default(0);
            $table->timestamps();

            $table->index(['comercio_id', 'estado']);
            $table->index(['venta_id', 'tipo_operacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nc_pendientes');
    }
};
