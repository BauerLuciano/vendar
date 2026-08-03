<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planes', function (Blueprint $table) {
            $table->unsignedInteger('trial_dias')->nullable()->after('usuarios_limit');
            $table->unsignedInteger('dias_mora')->nullable()->after('trial_dias');
        });
    }

    public function down(): void
    {
        Schema::table('planes', function (Blueprint $table) {
            $table->dropColumn(['trial_dias', 'dias_mora']);
        });
    }
};
