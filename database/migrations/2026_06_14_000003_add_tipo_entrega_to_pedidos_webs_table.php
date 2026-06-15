<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_web', function (Blueprint $table) {
            $table->string('tipo_entrega', 20)->default('delivery')->after('sucursal_id');
        });

        DB::table('pedidos_web')
            ->where('cliente_direccion', 'Retiro en local')
            ->update(['tipo_entrega' => 'local']);
    }

    public function down(): void
    {
        Schema::table('pedidos_web', function (Blueprint $table) {
            $table->dropColumn('tipo_entrega');
        });
    }
};
