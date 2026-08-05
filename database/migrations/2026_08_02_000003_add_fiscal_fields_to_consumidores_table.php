<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumidores', function (Blueprint $table) {
            $table->string('cuit')->nullable()->after('documento');
            $table->string('tipo_documento')->nullable()->after('cuit');
            $table->string('razon_social')->nullable()->after('apellido');
            $table->string('domicilio_fiscal')->nullable()->after('direccion');
        });
    }

    public function down(): void
    {
        Schema::table('consumidores', function (Blueprint $table) {
            $table->dropColumn(['domicilio_fiscal', 'razon_social', 'tipo_documento', 'cuit']);
        });
    }
};
