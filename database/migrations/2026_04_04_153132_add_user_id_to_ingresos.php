<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingresos_mercaderias', function (Blueprint $table) {
            // Agregamos la columna user_id vinculada a la tabla users
            // La ponemos después de sucursal_id para que quede ordenado
            $table->foreignId('user_id')
                  ->nullable() 
                  ->after('sucursal_id')
                  ->constrained('users')
                  ->onDelete('set null'); 
        });
    }

    public function down(): void
    {
        Schema::table('ingresos_mercaderias', function (Blueprint $table) {
            // Para revertir, primero quitamos la clave foránea y luego la columna
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};