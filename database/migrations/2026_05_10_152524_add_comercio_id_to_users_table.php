<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('comercio_id')
                  ->nullable() 
                  ->after('id') // La ponemos arriba de todo para verla fácil
                  ->constrained('comercios')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['comercio_id']);
            $table->dropColumn('comercio_id');
        });
    }
};
