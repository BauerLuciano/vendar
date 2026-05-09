<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            // Agregamos la columna como nullable por si ya tenés sucursales viejas creadas
            if (!Schema::hasColumn('sucursales', 'comercio_id')) {
                $table->foreignId('comercio_id')
                      ->nullable()
                      ->constrained('comercios')
                      ->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            if (Schema::hasColumn('sucursales', 'comercio_id')) {
                $table->dropForeign(['comercio_id']);
                $table->dropColumn('comercio_id');
            }
        });
    }
};