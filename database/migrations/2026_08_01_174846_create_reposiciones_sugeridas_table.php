<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reposiciones_sugeridas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercio_id')->constrained('comercios')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('estado')->default('pendiente');
            $table->timestamp('ignorado_hasta')->nullable();
            $table->timestamps();

            $table->unique(['comercio_id', 'sucursal_id', 'producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reposiciones_sugeridas');
    }
};
