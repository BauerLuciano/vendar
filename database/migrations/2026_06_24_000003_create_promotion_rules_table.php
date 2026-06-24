<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('condition_type'); // expiry_date | stock | category | margin | etc
            $table->string('operator'); // > < = >= <=
            $table->string('value');
            $table->string('action_type'); // discount_percent | fixed_price
            $table->decimal('action_value', 10, 2);
            $table->timestamps();

            $table->index('promotion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_rules');
    }
};
