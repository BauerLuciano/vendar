<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historico_costos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained()->cascadeOnDelete();
            $table->decimal('costo_anterior', 12, 2);
            $table->decimal('costo_nuevo', 12, 2);
            $table->decimal('precio_venta_anterior', 12, 2)->nullable();
            $table->decimal('precio_venta_nuevo', 12, 2)->nullable();
            $table->foreignId('user_id')->constrained();
            $table->string('origen_tipo');
            $table->unsignedBigInteger('origen_id')->nullable();
            $table->timestamps();

            $table->index(['producto_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_costos');
    }
};
