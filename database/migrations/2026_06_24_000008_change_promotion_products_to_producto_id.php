<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_products', function (Blueprint $table) {
            $table->dropForeign(['global_product_id']);
            $table->dropUnique(['promotion_id', 'global_product_id']);
            $table->dropIndex(['global_product_id']);
            $table->dropColumn('global_product_id');

            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->unique(['promotion_id', 'producto_id']);
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::table('promotion_products', function (Blueprint $table) {
            $table->dropForeign(['producto_id']);
            $table->dropUnique(['promotion_id', 'producto_id']);
            $table->dropIndex(['producto_id']);
            $table->dropColumn('producto_id');

            $table->foreignId('global_product_id')->constrained('global_products')->cascadeOnDelete();
            $table->unique(['promotion_id', 'global_product_id']);
            $table->index('global_product_id');
        });
    }
};
