<?php

namespace App\Services\Payment\Contracts;

use App\Enums\PaymentStatus;

class CheckoutResponse
{
    public function __construct(
        public readonly ?string $checkoutUrl = null,
        public readonly string $gatewayTransactionId = '',
        public readonly PaymentStatus $status = PaymentStatus::PENDING,
        public readonly ?CheckoutPresentation $presentation = null,
        public readonly array $raw = [],
    ) {}
}
