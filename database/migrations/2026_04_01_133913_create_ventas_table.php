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
    Schema::create('ventas', function (Blueprint $table) {
        $table->id();
        $table->foreignId('consumidor_id')->nullable()->constrained('consumidores')->nullOnDelete();
        $table->foreignId('sucursal_id')->constrained(table: 'branches', indexName: 'ventas_branch_id_foreign'); // Para saber de dónde salió el stock
        $table->decimal('total', 12, 2)->default(0);
        $table->string('metodo_pago'); // efectivo, fiado, mercadopago
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
