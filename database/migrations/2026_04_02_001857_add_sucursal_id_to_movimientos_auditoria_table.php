<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_auditoria', function (Blueprint $table) {
            $table->foreignId('sucursal_id')->nullable()->after('usuario_id')->constrained('sucursales')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_auditoria', function (Blueprint $table) {
            $table->dropColumn('sucursal_id');
        });
    }
};
