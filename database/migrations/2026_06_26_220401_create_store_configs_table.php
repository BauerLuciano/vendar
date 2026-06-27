<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercio_id')->constrained('comercios')->cascadeOnDelete()->unique();
            $table->jsonb('config');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_configs');
    }
};
