<?php

use App\Enums\PaymentChannel;
use App\Enums\MetodoPago;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_method_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercio_id')->constrained()->cascadeOnDelete();
            $table->string('metodo_pago');
            $table->string('provider')->nullable();
            $table->string('channel')->default(PaymentChannel::MANUAL->value);
            $table->json('display_data')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['comercio_id', 'metodo_pago', 'provider'], 'pmc_comercio_metodo_provider_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_method_configurations');
    }
};
