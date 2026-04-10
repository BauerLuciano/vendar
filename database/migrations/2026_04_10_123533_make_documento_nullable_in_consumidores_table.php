<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('consumidores', function (Blueprint $table) {
            $table->string('documento')->nullable()->change();
            $table->string('telefono')->nullable()->change();
            $table->string('direccion')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumidores', function (Blueprint $table) {
            //
        });
    }
};
