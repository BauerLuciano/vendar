<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recargos_tarjetas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercio_id')->constrained()->cascadeOnDelete();
            $table->string('banco');
            $table->enum('tipo_tarjeta', ['DEBITO', 'CREDITO']);
            $table->unsignedSmallInteger('cuotas')->default(1);
            $table->decimal('porcentaje', 5, 2)->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['comercio_id', 'banco', 'tipo_tarjeta', 'cuotas']);
            $table->index('comercio_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recargos_tarjetas');
    }
};
