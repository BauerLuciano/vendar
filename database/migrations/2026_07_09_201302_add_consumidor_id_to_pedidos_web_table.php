<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_web', function (Blueprint $table) {
            $table->foreignId('consumidor_id')->nullable()->after('sucursal_id')->constrained('consumidores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_web', function (Blueprint $table) {
            $table->dropForeign(['consumidor_id']);
            $table->dropColumn('consumidor_id');
        });
    }
};
