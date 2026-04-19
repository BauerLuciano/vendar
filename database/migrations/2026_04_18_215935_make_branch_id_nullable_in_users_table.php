<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Hacemos que la sucursal sea opcional (nullable)
            $table->unsignedBigInteger('branch_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Si hacemos un rollback, vuelve a ser obligatoria
            $table->unsignedBigInteger('branch_id')->nullable(false)->change();
        });
    }
};