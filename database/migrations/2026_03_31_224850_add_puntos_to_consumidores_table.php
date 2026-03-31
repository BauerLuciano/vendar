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
        Schema::table('consumidores', function (Blueprint $table) {
            // Agregamos la columna después del límite de crédito
            $table->integer('puntos_acumulados')->default(0)->after('limite_cuenta_corriente');
        });
    }

    public function down(): void
    {
        Schema::table('consumidores', function (Blueprint $table) {
            $table->dropColumn('puntos_acumulados');
        });
    }
};
