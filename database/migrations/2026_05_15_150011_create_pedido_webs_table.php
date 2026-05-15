<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_web', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercio_id')->constrained('comercios')->cascadeOnDelete();
            
            // Datos del cliente
            $table->string('cliente_nombre');
            $table->string('cliente_telefono');
            $table->string('cliente_direccion')->nullable();
            
            // Totales
            $table->decimal('subtotal', 10, 2);
            $table->decimal('costo_envio', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            
            // Pagos y Estados
            $table->enum('metodo_pago', ['efectivo', 'transferencia', 'mercadopago', 'payway']);
            $table->enum('estado_pago', ['pendiente', 'pagado', 'rechazado'])->default('pendiente');
            $table->enum('estado_pedido', ['nuevo', 'preparando', 'en_camino', 'entregado', 'cancelado'])->default('nuevo');
            
            // Comprobantes e integraciones
            $table->string('comprobante_transferencia_url')->nullable();
            $table->string('pasarela_payment_id')->nullable(); 
            
            $table->text('notas')->nullable(); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_web');
    }
};