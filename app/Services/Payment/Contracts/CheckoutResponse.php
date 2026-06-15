<?php

namespace App\Services\Payment\Contracts;

use App\Enums\PaymentStatus;

class CheckoutResponse
{
    public function __construct(
        public readonly string $checkoutUrl,
        public readonly string $gatewayTransactionId,
        public readonly PaymentStatus $status,
        public readonly array $raw = [],
    ) {}
}
