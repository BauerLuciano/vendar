<?php

namespace App\Services\Payment\Contracts;

class CheckoutPresentation
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $data = null,
        public readonly ?string $deviceId = null,
        public readonly ?array $metadata = null,
    ) {}
}
