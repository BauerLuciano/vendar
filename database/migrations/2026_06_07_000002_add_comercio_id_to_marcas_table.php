<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            if (!Schema::hasColumn('marcas', 'comercio_id')) {
                $table->foreignId('comercio_id')
                    ->nullable()
                    ->constrained('comercios')
                    ->cascadeOnDelete()
                    ->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('comercio_id');
        });
    }
};
