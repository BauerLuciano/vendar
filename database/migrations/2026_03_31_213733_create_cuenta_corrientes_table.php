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
        Schema::create('cuentas_corrientes', function (Blueprint $table) {
            $table->id();
            // Clave foránea que lo une al cliente
            $table->foreignId('consumidor_id')->constrained('consumidores')->cascadeOnDelete();
            
            $table->decimal('saldo_deudor', 10, 2)->default(0);
            $table->dateTime('fecha_ultimo_movimiento')->nullable();
            $table->boolean('estado')->default(true); // true = Activa, false = Suspendida/Bloqueada
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuenta_corrientes');
    }
};
