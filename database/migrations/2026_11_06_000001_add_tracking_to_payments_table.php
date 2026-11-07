<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->date('period_start')->nullable()->after('amount');
            $table->date('period_end')->nullable()->after('period_start');
            $table->string('payment_method', 30)->nullable()->after('period_end');
            $table->foreignId('created_by_id')
                ->nullable()
                ->after('payment_method')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('idempotency_key', 191)->nullable()->after('created_by_id');

            $table->index(
                ['payable_type', 'payable_id', 'period_end'],
                'payments_payable_period_index'
            );
            $table->unique('idempotency_key', 'payments_idempotency_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_idempotency_key_unique');
            $table->dropIndex('payments_payable_period_index');
            $table->dropConstrainedForeignId('created_by_id');
            $table->dropColumn([
                'period_start',
                'period_end',
                'payment_method',
                'idempotency_key',
            ]);
        });
    }
};
