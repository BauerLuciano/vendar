<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comercios', function (Blueprint $table) {
            $table->foreignId('plan_id')
                ->nullable()
                ->after('status')
                ->constrained('planes')
                ->restrictOnDelete();

            $table->foreignId('pending_plan_id')
                ->nullable()
                ->after('plan_id')
                ->constrained('planes')
                ->restrictOnDelete();

            $table->integer('limite_usuarios')
                ->default(0)
                ->after('limite_sucursales');
        });
    }

    public function down(): void
    {
        Schema::table('comercios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pending_plan_id');
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn('limite_usuarios');
        });
    }
};
