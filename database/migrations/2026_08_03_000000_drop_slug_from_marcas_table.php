<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El slug de marcas no se usa para ruteo ni referencias (se deriva del nombre).
        // Su unicidad global impedía que 2 comercios tuvieran "Coca Cola" → slug "coca-cola".
        Schema::table('marcas', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }

    public function down(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->string('slug')->unique()->nullable();
        });
    }
};
