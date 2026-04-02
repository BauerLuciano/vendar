<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_auditoria', function (Blueprint $table) {
            // 1. Borramos la regla vieja que apunta a la tabla equivocada
            $table->dropForeign('movimientos_auditoria_sucursal_id_foreign');
            
            // 2. Creamos la regla correcta apuntando a tu tabla real "branches"
            $table->foreign('sucursal_id')->references('id')->on('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_auditoria', function (Blueprint $table) {
            $table->dropForeign(['sucursal_id']);
        });
    }
};