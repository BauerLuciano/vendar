<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El slug no se usa para ruteo (la tienda filtra por id de categoría).
        // La unicidad global impedía que 2 comercios tuvieran "Bebidas" → slug "bebidas".
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropUnique('categorias_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->unique('slug');
        });
    }
};
