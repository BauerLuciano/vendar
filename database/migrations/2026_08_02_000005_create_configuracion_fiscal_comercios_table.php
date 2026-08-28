<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_fiscal_comercios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercio_id')->unique()->constrained('comercios')->cascadeOnDelete();
            $table->string('cuit')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('condicion_fiscal')->nullable();
            $table->string('domicilio_fiscal')->nullable();
            $table->enum('entorno', ['produccion', 'homologacion'])->default('homologacion');
            $table->unsignedInteger('punto_venta_activo')->nullable();
            $table->string('estado_modulo')->default('sin_datos');
            $table->foreignId('certificado_id')->nullable()->constrained('certificados_fiscales')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_fiscal_comercios');
    }
};
