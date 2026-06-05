<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('consumidores', 'comercio_id')) {
            Schema::table('consumidores', function (Blueprint $table) {
                $table->foreignId('comercio_id')->nullable()->constrained('comercios')->nullOnDelete()->after('id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('consumidores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('comercio_id');
        });
    }
};
