<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados_fiscales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercio_id')->constrained('comercios')->cascadeOnDelete();
            $table->enum('entorno', ['produccion', 'homologacion'])->default('produccion');
            $table->text('archivo_pfx');
            $table->text('password_pfx');
            $table->string('distinguished_name')->nullable();
            $table->string('numero_serie')->nullable();
            $table->date('vigencia_desde')->nullable();
            $table->date('vigencia_hasta')->nullable();
            $table->timestamps();

            $table->index(['comercio_id', 'entorno']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados_fiscales');
    }
};
