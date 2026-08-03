<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('precio_costo', 10, 2)->nullable()->change();
            $table->integer('stock_minimo')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('precio_costo', 10, 2)->default(0)->nullable(false)->change();
            $table->integer('stock_minimo')->default(0)->nullable(false)->change();
        });
    }
};
