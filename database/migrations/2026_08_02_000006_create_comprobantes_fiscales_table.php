<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes_fiscales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->foreignId('comercio_id')->constrained('comercios')->restrictOnDelete();
            $table->unsignedInteger('punto_venta');
            $table->string('tipo');
            $table->string('letra', 1);
            $table->unsignedBigInteger('numero');
            $table->string('cae', 14)->nullable();
            $table->date('vencimiento_cae')->nullable();
            $table->decimal('neto', 12, 2);
            $table->decimal('iva', 12, 2);
            $table->decimal('total', 12, 2);
            $table->text('qr')->nullable();
            $table->foreignId('comprobante_original_id')->nullable()->constrained('comprobantes_fiscales')->nullOnDelete();
            $table->string('estado')->default('pendiente_emision');
            $table->text('motivo_fallo')->nullable();
            $table->timestamps();

            $table->unique(['comercio_id', 'punto_venta', 'tipo', 'numero'], 'comprobantes_fiscales_numero_unique');
            $table->index(['comercio_id', 'venta_id']);
            $table->index(['comercio_id', 'estado']);
            $table->index(['comercio_id', 'comprobante_original_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes_fiscales');
    }
};
