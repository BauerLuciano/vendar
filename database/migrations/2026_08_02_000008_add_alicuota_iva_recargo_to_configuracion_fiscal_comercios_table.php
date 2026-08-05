<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_fiscal_comercios', function (Blueprint $table) {
            $table->decimal('alicuota_iva_recargo', 5, 2)->default(21.00)->after('estado_modulo');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_fiscal_comercios', function (Blueprint $table) {
            $table->dropColumn('alicuota_iva_recargo');
        });
    }
};
