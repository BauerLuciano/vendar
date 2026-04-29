<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingreso_detalles', function (Blueprint $table) {
            // Agregamos la columna después del costo
            $table->date('fecha_vencimiento')->nullable()->after('costo_unitario');
        });
    }

    public function down(): void
    {
        Schema::table('ingreso_detalles', function (Blueprint $table) {
            $table->dropColumn('fecha_vencimiento');
        });
    }
};