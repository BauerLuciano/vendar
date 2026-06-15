<?php

namespace App\Services\Payment\Contracts;

class CheckoutRequest
{
    public function __construct(
        public readonly string $referenceId,
        public readonly float $amount,
        public readonly string $title,
        public readonly string $description,
        public readonly array $items,
        public readonly string $currencyId = 'ARS',
        public readonly ?string $successUrl = null,
        public readonly ?string $failureUrl = null,
        public readonly ?string $pendingUrl = null,
        public readonly ?string $notificationUrl = null,
        public readonly array $metadata = [],
    ) {}
}
