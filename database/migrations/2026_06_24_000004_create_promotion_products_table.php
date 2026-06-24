<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('global_product_id')->constrained('global_products')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['promotion_id', 'global_product_id']);
            $table->index('global_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_products');
    }
};
