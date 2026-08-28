<?php

namespace App\Services\Payment\Gateways;

use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Models\Comercio;
use App\Services\Payment\Contracts\CheckoutPresentation;
use App\Services\Payment\Contracts\CheckoutRequest;
use App\Services\Payment\Contracts\CheckoutResponse;
use App\Services\Payment\Contracts\PaymentGateway;
use App\Services\Payment\Contracts\PaymentStatusResponse;
use App\Services\Payment\Contracts\WebhookPayload;
use App\Services\Payment\Exceptions\PaymentException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MercadopagoGateway implements PaymentGateway
{
    private const API_BASE = 'https://api.mercadopago.com';

    public function __construct(
        private readonly array $config,
    ) {}

    public function identifier(): string
    {
        return 'mercadopago';
    }

    public function displayName(): string
    {
        return 'Mercado Pago';
    }

    public function getWebhookUrl(?Comercio $comercio = null): ?string
    {
        $params = $comercio ? "?comercio_id={$comercio->id}" : '';

        return config('services.mercadopago.public_url').'/api/mercadopago/notificacion'.$params;
    }

    public function supportsCheckout(): bool
    {
        return true;
    }

    public function supportsWebhook(): bool
    {
        return true;
    }

    public function supportsRecurring(): bool
    {
        return false;
    }

    public function supportsChannel(PaymentChannel $channel): bool
    {
        return in_array($channel, [
            PaymentChannel::API,
            PaymentChannel::QR,
            PaymentChannel::POINT,
        ], true);
    }

    public function initiatePayment(CheckoutRequest $request, PaymentChannel $channel, array $options = []): CheckoutResponse
    {
        return match ($channel) {
            PaymentChannel::QR => $this->createQrPayment($request, $options),
            PaymentChannel::POINT => $this->createPointPayment($request, $options),
            default => $this->createCheckout($request),
        };
    }

    public function createCheckout(CheckoutRequest $request): CheckoutResponse
    {
        $token = $this->getAccessToken();

        $payload = [
            'items' => $request->items,
            'external_reference' => $request->referenceId,
            'back_urls' => [
                'success' => $request->successUrl,
                'pending' => $request->pendingUrl,
                'failure' => $request->failureUrl,
            ],
            'notification_url' => $request->notificationUrl
                ?? $this->getWebhookUrl(
                    isset($request->metadata['comercio']) ? $request->metadata['comercio'] : null
                ),
            'binary_mode' => true,
        ];

        if ($request->successUrl) {
            $payload['auto_return'] = 'approved';
        }

        $response = Http::withToken($token)
            ->post(self::API_BASE.'/checkout/preferences', $payload);

        if (! $response->successful()) {
            throw new PaymentException('Error al crear preferencia en Mercado Pago: '.$response->body());
        }

        $data = $response->json();

        return new CheckoutResponse(
            checkoutUrl: $data['init_point'],
            gatewayTransactionId: $data['id'],
            status: PaymentStatus::PENDING,
            presentation: new CheckoutPresentation(
                type: 'redirect',
                data: $data['init_point'],
            ),
            raw: $data,
        );
    }

    public function getPaymentStatus(string $gatewayTransactionId): PaymentStatusResponse
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->get(self::API_BASE."/v1/payments/{$gatewayTransactionId}");

        if (! $response->successful()) {
            throw new PaymentException('Error al consultar pago en Mercado Pago: '.$response->body());
        }

        $data = $response->json();

        return new PaymentStatusResponse(
            gatewayTransactionId: (string) ($data['id'] ?? $gatewayTransactionId),
            status: $this->normalizeStatus($data['status'] ?? 'unknown'),
            referenceId: $data['external_reference'] ?? null,
            amount: isset($data['transaction_amount']) ? (float) $data['transaction_amount'] : null,
            raw: $data,
        );
    }

    public function normalizeStatus(string $gatewayStatus, ?string $orderStatus = null): PaymentStatus
    {
        return match ($gatewayStatus) {
            'approved' => PaymentStatus::APPROVED,
            'pending', 'in_process', 'in_mediation' => PaymentStatus::PENDING,
            'rejected' => PaymentStatus::REJECTED,
            'cancelled' => PaymentStatus::CANCELLED,
            'refunded' => PaymentStatus::REFUNDED,
            'charged_back' => PaymentStatus::REFUNDED,
            default => PaymentStatus::PENDING,
        };
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = $this->config['webhook_secret'] ?? null;

        if (! $secret) {
            if (app()->environment('production')) {
                \Log::critical('MercadoPago webhook secret is missing in production');

                return false;
            }
            \Log::warning('MercadoPago webhook secret not configured — skipping verification');

            return true;
        }

        $paymentId = $request->input('data.id') ?? $request->input('id');
        $signature = $request->header('X-Signature');

        if (! $signature || ! $paymentId) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signature) as $part) {
            $segments = explode('=', $part, 2);
            if (count($segments) === 2) {
                $parts[trim($segments[0])] = trim($segments[1]);
            }
        }

        $ts = $parts['ts'] ?? null;
        $v1 = $parts['v1'] ?? null;

        if (! $ts || ! $v1) {
            return false;
        }

        if (abs(time() - (int) $ts) > 300) {
            \Log::warning('MercadoPago webhook rejected: timestamp too old');

            return false;
        }

        $expected = hash_hmac('sha256', "{$paymentId}|{$ts}|{$secret}", $secret);

        return hash_equals($expected, $v1);
    }

    public function parseWebhookPayload(Request $request): WebhookPayload
    {
        $paymentId = $request->input('data.id') ?? $request->input('id');

        if (! $paymentId) {
            throw new PaymentException('No payment ID in webhook payload');
        }

        $status = $this->getPaymentStatus($paymentId);

        return new WebhookPayload(
            gatewayTransactionId: $status->gatewayTransactionId,
            status: $status->status,
            referenceId: $status->referenceId,
            amount: $status->amount,
            raw: $status->raw,
        );
    }

    private function createQrPayment(CheckoutRequest $request, array $options): CheckoutResponse
    {
        $token = $this->getAccessToken();
        $userId = $options['user_id'] ?? $this->config['user_id'] ?? null;
        $storeId = $options['store_id'] ?? $this->config['store_id'] ?? null;

        if (! $userId || ! $storeId) {
            throw new PaymentException(
                'MP QR requiere user_id y store_id configurados en payment_gateways'
            );
        }

        $payload = [
            'external_reference' => $request->referenceId,
            'title' => $request->title,
            'description' => $request->description,
            'notification_url' => $request->notificationUrl,
            'total_amount' => $request->amount,
            'items' => $request->items,
            'cash_out' => ['amount' => 0],
            'sponsor' => [
                'id' => $this->config['sponsor_id'] ?? null,
            ],
        ];

        $response = Http::withToken($token)
            ->post(self::API_BASE."/instore/orders/qr/seller/collectors/{$userId}/stores/{$storeId}/orders", $payload);

        if (! $response->successful()) {
            throw new PaymentException('Error al crear QR en Mercado Pago: '.$response->body());
        }

        $data = $response->json();

        return new CheckoutResponse(
            gatewayTransactionId: $data['id'],
            status: PaymentStatus::PENDING,
            presentation: new CheckoutPresentation(
                type: 'qr',
                data: $data['qr_data'],
            ),
            raw: $data,
        );
    }

    private function createPointPayment(CheckoutRequest $request, array $options): CheckoutResponse
    {
        throw new PaymentException('MP Point no implementado aún');
    }

    private function getAccessToken(): string
    {
        $token = $this->config['access_token'] ?? null;

        if (! $token) {
            throw new PaymentException('Mercado Pago access token no configurado');
        }

        return trim($token);
    }
}
