<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_secuencias_fiscales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercio_id')->constrained('comercios')->cascadeOnDelete();
            $table->unsignedInteger('punto_venta');
            $table->string('tipo');
            $table->unsignedBigInteger('ultimo_numero')->default(0);
            $table->timestamps();

            $table->unique(['comercio_id', 'punto_venta', 'tipo'], 'control_secuencias_fiscales_pv_tipo_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_secuencias_fiscales');
    }
};
