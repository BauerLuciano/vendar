<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('alicuota_iva', 5, 2)->nullable()->after('precio_venta');
        });

        // Backfill a la alícuota general (21%) para registros existentes.
        // Decisión documentada en el Build Plan F1: se aplica como supuesto fiscal por defecto
        // a los productos previos al módulo; es editable por producto en F5 y reversible vía down().
        DB::table('productos')->whereNull('alicuota_iva')->update(['alicuota_iva' => 21.00]);
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('alicuota_iva');
        });
    }
};
