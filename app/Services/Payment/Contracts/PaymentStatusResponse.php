<?php

namespace App\Services\Payment\Contracts;

use App\Enums\PaymentStatus;

class PaymentStatusResponse
{
    public function __construct(
        public readonly string $gatewayTransactionId,
        public readonly PaymentStatus $status,
        public readonly ?string $referenceId = null,
        public readonly ?float $amount = null,
        public readonly array $raw = [],
    ) {}
}
