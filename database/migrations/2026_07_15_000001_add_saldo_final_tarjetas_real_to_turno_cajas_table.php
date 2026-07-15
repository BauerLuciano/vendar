<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turno_cajas', function (Blueprint $table) {
            $table->decimal('saldo_final_tarjetas_real', 12, 2)->default(0)->after('saldo_final_transf_real');
        });
    }

    public function down(): void
    {
        Schema::table('turno_cajas', function (Blueprint $table) {
            $table->dropColumn('saldo_final_tarjetas_real');
        });
    }
};
