<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F9: el ledger debe poder reconstruir fielmente cada comprobante
     * (arquitectura §18.1: "neto/IVA/total por alícuota"). Sin el desglose, una
     * Nota de Crédito parcial se reconstruiría desde los detalles de la venta y
     * mostraría el total completo. Se guarda el snapshot por línea (cantidad,
     * precio con IVA y alícuota, invariante 12).
     */
    public function up(): void
    {
        Schema::table('comprobantes_fiscales', function (Blueprint $table) {
            $table->jsonb('desglose')->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes_fiscales', function (Blueprint $table) {
            $table->dropColumn('desglose');
        });
    }
};
