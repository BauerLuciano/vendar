<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->boolean('exclusive')->default(false)->after('priority');
            $table->boolean('cumulative')->default(false)->after('exclusive');
            $table->json('discount_config')->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['description', 'exclusive', 'cumulative', 'discount_config']);
        });
    }
};
