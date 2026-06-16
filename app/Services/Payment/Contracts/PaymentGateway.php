<?php

namespace App\Services\Payment\Contracts;

use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Models\Comercio;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function identifier(): string;

    public function displayName(): string;

    public function getWebhookUrl(?Comercio $comercio = null): ?string;

    public function createCheckout(CheckoutRequest $request): CheckoutResponse;

    public function getPaymentStatus(string $gatewayTransactionId): PaymentStatusResponse;

    public function normalizeStatus(string $gatewayStatus, ?string $orderStatus = null): PaymentStatus;

    public function verifyWebhookSignature(Request $request): bool;

    public function parseWebhookPayload(Request $request): WebhookPayload;

    public function supportsCheckout(): bool;

    public function supportsWebhook(): bool;

    public function supportsRecurring(): bool;

    public function supportsChannel(PaymentChannel $channel): bool;

    public function initiatePayment(CheckoutRequest $request, PaymentChannel $channel, array $options = []): CheckoutResponse;
}
