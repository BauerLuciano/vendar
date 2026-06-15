<?php

use App\Enums\PaymentChannel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable');
            $table->string('provider')->nullable();
            $table->string('channel')->default(PaymentChannel::API->value);
            $table->string('status');
            $table->string('gateway_transaction_id')->nullable();
            $table->string('authorization_code')->nullable();
            $table->string('reference')->nullable();
            $table->string('provider_reference')->nullable();
            $table->tinyInteger('attempt')->default(1);
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('ARS');
            $table->json('gateway_request')->nullable();
            $table->json('gateway_response')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'gateway_transaction_id'], 'payments_provider_tx_unique');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
