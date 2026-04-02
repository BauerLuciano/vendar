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
    Schema::create('movimientos_cuenta_corrientes', function (Blueprint $table) {
        $table->id();
        // Lo vinculamos a la cuenta corriente del cliente
        $table->foreignId('cuenta_corriente_id')->constrained('cuentas_corrientes')->cascadeOnDelete();
        
        $table->decimal('monto', 10, 2);
        $table->string('tipo')->default('pago'); // Puede ser 'pago', 'cargo_por_compra', etc.
        $table->string('descripcion')->nullable();
        
        $table->timestamps(); // Esto ya guarda la fecha exacta automáticamente
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimiento_cuenta_corrientes');
    }
};
