<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Comercio;
use App\Models\PaymentGateway;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercio_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->boolean('enabled')->default(false);
            $table->json('configuration')->nullable();
            $table->timestamps();

            $table->unique(['comercio_id', 'provider']);
        });

        foreach (Comercio::whereNotNull('mp_access_token')->cursor() as $comercio) {
            PaymentGateway::firstOrCreate(
                ['comercio_id' => $comercio->id, 'provider' => 'mercadopago'],
                [
                    'enabled' => true,
                    'configuration' => ['access_token' => $comercio->mp_access_token],
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
