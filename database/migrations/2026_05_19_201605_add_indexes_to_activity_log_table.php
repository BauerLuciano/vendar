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
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('causer_id');
            $table->index('causer_type');
            $table->index('subject_type');
            $table->index('event');
            $table->index(['causer_id', 'causer_type']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['causer_id']);
            $table->dropIndex(['causer_type']);
            $table->dropIndex(['subject_type']);
            $table->dropIndex(['event']);
            $table->dropIndex(['causer_id', 'causer_type']);
            $table->dropIndex(['subject_type', 'subject_id']);
        });
    }
};
