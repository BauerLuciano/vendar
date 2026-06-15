<?php

namespace App\Services\Payment;

use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\Contracts\CheckoutRequest;
use App\Services\Payment\Contracts\CheckoutResponse;
use App\Services\Payment\Contracts\WebhookPayload;
use Illuminate\Database\Eloquent\Model;

class PaymentRecorder
{
    public function createPending(
        Model $payable,
        string $provider,
        float $amount,
        PaymentChannel $channel = PaymentChannel::API,
        ?string $reference = null,
        ?string $gatewayTransactionId = null,
        ?string $currency = 'ARS',
        array $gatewayRequest = [],
        array $metadata = [],
    ): Payment {
        $lastAttempt = $payable->payments()
            ->where('provider', $provider)
            ->max('attempt') ?? 0;

        return $payable->payments()->create([
            'provider' => $provider,
            'channel' => $channel,
            'status' => PaymentStatus::PENDING,
            'gateway_transaction_id' => $gatewayTransactionId,
            'reference' => $reference,
            'attempt' => $lastAttempt + 1,
            'amount' => $amount,
            'currency' => $currency,
            'gateway_request' => $gatewayRequest,
            'metadata' => $metadata,
        ]);
    }

    public function approve(
        Model $payable,
        string $provider,
        ?string $gatewayTransactionId = null,
        ?array $gatewayResponse = null,
    ): ?Payment {
        $payment = $this->findPending($payable, $provider, $gatewayTransactionId);

        if (!$payment) {
            return null;
        }

        $update = [
            'status' => PaymentStatus::APPROVED,
            'approved_at' => now(),
        ];

        if ($gatewayResponse !== null) {
            $update['gateway_response'] = $gatewayResponse;
        }
        if ($gatewayTransactionId !== null) {
            $update['gateway_transaction_id'] = $gatewayTransactionId;
        }

        $payment->update($update);

        return $payment;
    }

    public function reject(
        Model $payable,
        string $provider,
        ?string $gatewayTransactionId = null,
        ?array $gatewayResponse = null,
    ): ?Payment {
        $payment = $this->findPending($payable, $provider, $gatewayTransactionId);

        if (!$payment) {
            return null;
        }

        $update = [
            'status' => PaymentStatus::REJECTED,
            'failed_at' => now(),
        ];

        if ($gatewayResponse !== null) {
            $update['gateway_response'] = $gatewayResponse;
        }

        $payment->update($update);

        return $payment;
    }

    public function cancel(
        Model $payable,
        string $provider,
        ?string $gatewayTransactionId = null,
    ): ?Payment {
        $payment = $this->findPending($payable, $provider, $gatewayTransactionId);

        if (!$payment) {
            return null;
        }

        $payment->update([
            'status' => PaymentStatus::CANCELLED,
            'cancelled_at' => now(),
        ]);

        return $payment;
    }

    public function refund(Payment $payment): Payment
    {
        $payment->update([
            'status' => PaymentStatus::REFUNDED,
            'refunded_at' => now(),
        ]);

        return $payment;
    }

    public function recordCheckout(
        Model $payable,
        string $provider,
        CheckoutRequest $request,
        CheckoutResponse $response,
    ): Payment {
        $lastAttempt = $payable->payments()
            ->where('provider', $provider)
            ->max('attempt') ?? 0;

        return $payable->payments()->create([
            'provider' => $provider,
            'channel' => PaymentChannel::API,
            'status' => $response->status,
            'gateway_transaction_id' => $response->gatewayTransactionId,
            'reference' => $request->referenceId,
            'attempt' => $lastAttempt + 1,
            'amount' => $request->amount,
            'currency' => $request->currencyId,
            'gateway_request' => [
                'reference_id' => $request->referenceId,
                'amount' => $request->amount,
                'currency' => $request->currencyId,
                'items' => $request->items,
            ],
            'gateway_response' => $response->raw,
        ]);
    }

    public function recordWebhook(
        Model $payable,
        string $provider,
        WebhookPayload $payload,
    ): Payment {
        $payment = $payable->payments()
            ->where('provider', $provider)
            ->where('gateway_transaction_id', $payload->gatewayTransactionId)
            ->first();

        if ($payment) {
            $timestamps = $this->statusTimestamps($payload->status);

            $payment->update(array_merge([
                'status' => $payload->status,
                'gateway_response' => $payload->raw,
            ], $timestamps));

            return $payment;
        }

        $lastAttempt = $payable->payments()
            ->where('provider', $provider)
            ->max('attempt') ?? 0;

        $timestamps = $this->statusTimestamps($payload->status);

        return $payable->payments()->create(array_merge([
            'provider' => $provider,
            'channel' => PaymentChannel::API,
            'status' => $payload->status,
            'gateway_transaction_id' => $payload->gatewayTransactionId,
            'provider_reference' => $payload->referenceId,
            'attempt' => $lastAttempt + 1,
            'amount' => $payload->amount,
            'gateway_response' => $payload->raw,
        ], $timestamps));
    }

    private function findPending(
        Model $payable,
        string $provider,
        ?string $gatewayTransactionId = null,
    ): ?Payment {
        $query = $payable->payments()
            ->where('provider', $provider)
            ->where('status', PaymentStatus::PENDING);

        if ($gatewayTransactionId) {
            $query->where('gateway_transaction_id', $gatewayTransactionId);
        }

        return $query->latest()->first();
    }

    private function statusTimestamps(PaymentStatus $status): array
    {
        $now = now();

        return match ($status) {
            PaymentStatus::APPROVED => ['approved_at' => $now],
            PaymentStatus::REJECTED => ['failed_at' => $now],
            PaymentStatus::CANCELLED => ['cancelled_at' => $now],
            PaymentStatus::REFUNDED => ['refunded_at' => $now],
            default => [],
        };
    }
}
