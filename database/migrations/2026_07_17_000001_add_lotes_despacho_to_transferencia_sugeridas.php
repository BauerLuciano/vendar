<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transferencia_sugeridas', function (Blueprint $table) {
            $table->json('lotes_despacho')->nullable()->after('cantidad');
        });
    }

    public function down(): void
    {
        Schema::table('transferencia_sugeridas', function (Blueprint $table) {
            $table->dropColumn('lotes_despacho');
        });
    }
};
