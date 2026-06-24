<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercio_id')->nullable()->constrained('comercios')->nullOnDelete();
            $table->string('name');
            $table->string('type'); // MANUAL | AUTO
            $table->string('discount_type'); // percent | fixed_price | 2x1 | bundle
            $table->decimal('value', 10, 2)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('active')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->index('comercio_id');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
