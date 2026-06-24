<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_products', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_barras', 50)->unique()->index();
            $table->string('nombre')->nullable();
            $table->string('marca')->nullable();
            $table->string('categoria')->nullable();
            $table->string('presentacion')->nullable();
            $table->decimal('peso_gramos', 10, 2)->nullable();
            $table->string('imagen')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('fabricante')->nullable();
            $table->string('pais_origen')->nullable();
            $table->string('provider')->nullable();
            $table->json('datos_extra')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_products');
    }
};
