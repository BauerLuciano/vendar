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
        Schema::create('movimientos_auditoria', function (Blueprint $table) {
            $table->id();
            // El usuario que hizo el lío
            $table->foreignId('usuario_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('accion');     // Ej: 'EDICION', 'CREACION'
            $table->string('tabla');      // Ej: 'productos'
            $table->unsignedBigInteger('registro_id'); // El ID del producto, categoría, etc.
            $table->jsonb('detalles')->nullable();     // Lo que cambió (antes y después)
            $table->timestamp('fecha')->useCurrent();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_auditoria');
    }
};
