<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega datos personales opcionales sin tocar columnas existentes.
     * `name` sigue siendo el nombre completo visible para el resto del sistema.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('apellido', 100)->nullable();
            $table->string('telefono', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['apellido', 'telefono']);
        });
    }
};
