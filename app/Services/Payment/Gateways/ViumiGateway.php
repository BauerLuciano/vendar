<?php

namespace App\Services\Payment\Gateways;

use App\Enums\PaymentStatus;
use App\Models\Comercio;
use App\Services\Payment\Contracts\PaymentGateway;
use App\Services\Payment\Contracts\CheckoutRequest;
use App\Services\Payment\Contracts\CheckoutResponse;
use App\Services\Payment\Contracts\PaymentStatusResponse;
use App\Services\Payment\Contracts\WebhookPayload;
use App\Services\Payment\Exceptions\PaymentException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ViumiGateway implements PaymentGateway
{
    private const CACHE_TTL = 3000;
    private const AUTH_BASE_SANDBOX = 'https://auth.stg.geopagos.io';
    private const AUTH_BASE_PRODUCTION = 'https://auth.prd.geopagos.io';
    private const API_BASE_SANDBOX = 'https://api-mpos-macro.stg.geopagos.io';
    private const API_BASE_PRODUCTION = 'https://api.viumi.com.ar';

    private bool $retried = false;

    public function __construct(
        private readonly array $config,
    ) {}

    public function identifier(): string
    {
        return 'viumi';
    }

    public function displayName(): string
    {
        return 'viüMi';
    }

    public function getWebhookUrl(?Comercio $comercio = null): ?string
    {
        return url('/api/webhook/viumi');
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

    public function createCheckout(CheckoutRequest $request): CheckoutResponse
    {
        $items = array_map(fn (array $item) => [
            'id' => $item['id'] ?? Str::random(8),
            'name' => $item['title'] ?? $item['name'] ?? 'Producto',
            'unitPrice' => [
                'currency' => '032',
                'amount' => $this->toCents($item['unit_price'] ?? $item['unitPrice'] ?? 0),
            ],
            'quantity' => (int) ($item['quantity'] ?? 1),
        ], $request->items);

        $attributes = [
            'currency' => '032',
            'items' => $items,
        ];

        if ($request->notificationUrl) {
            $attributes['webhookUrl'] = $request->notificationUrl;
        }

        $redirectUrls = [];
        if ($request->successUrl) {
            $redirectUrls['success'] = $request->successUrl;
        }
        if ($request->failureUrl) {
            $redirectUrls['failed'] = $request->failureUrl;
        }
        if ($redirectUrls) {
            $attributes['redirect_urls'] = $redirectUrls;
        }

        $payload = [
            'data' => [
                'attributes' => $attributes,
            ],
        ];

        $response = $this->apiPost('/api/v2/orders', $payload);

        $orderUuid = $response['data']['attributes']['uuid'] ?? null;
        $checkoutUrl = $response['data']['attributes']['links']['checkout']
            ?? $response['data']['links']['checkout']
            ?? null;

        if (!$orderUuid || !$checkoutUrl) {
            throw new PaymentException('viüMi: respuesta inesperada al crear orden');
        }

        return new CheckoutResponse(
            checkoutUrl: $checkoutUrl,
            gatewayTransactionId: $orderUuid,
            status: PaymentStatus::PENDING,
            raw: $response,
        );
    }

    public function getPaymentStatus(string $gatewayTransactionId): PaymentStatusResponse
    {
        $response = $this->apiGet("/api/v2/orders/{$gatewayTransactionId}");

        $attrs = $response['data']['attributes'] ?? [];
        $orderStatus = $attrs['status'] ?? 'UNKNOWN';
        $payment = $attrs['payment'] ?? $attrs['payments'][0] ?? null;
        $paymentStatus = $payment['status'] ?? 'UNKNOWN';

        return new PaymentStatusResponse(
            gatewayTransactionId: (string) ($payment['id'] ?? $gatewayTransactionId),
            status: $this->normalizeStatus($paymentStatus, $orderStatus),
            referenceId: $gatewayTransactionId,
            amount: isset($attrs['price']['amount'])
                ? (float) ($attrs['price']['amount'] / 100)
                : null,
            raw: $response,
        );
    }

    public function normalizeStatus(string $gatewayStatus, ?string $orderStatus = null): PaymentStatus
    {
        if ($gatewayStatus === 'APPROVED' && $orderStatus === 'SUCCESS') {
            return PaymentStatus::APPROVED;
        }

        if (in_array($gatewayStatus, ['REJECTED', 'CANCELLED', 'REFUNDED', 'CHARGED_BACK'])) {
            return PaymentStatus::REJECTED;
        }

        return PaymentStatus::PENDING;
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        return true;
    }

    public function parseWebhookPayload(Request $request): WebhookPayload
    {
        $body = $request->input('data');

        if (!$body) {
            throw new PaymentException('viüMi: payload de webhook inválido');
        }

        $order = $body['order'] ?? [];
        $payment = $body['payment'] ?? [];

        $orderUuid = $order['uuid'] ?? null;
        $orderStatus = $order['status'] ?? 'UNKNOWN';
        $paymentStatus = $payment['status'] ?? 'UNKNOWN';

        if (!$orderUuid) {
            throw new PaymentException('viüMi: webhook sin UUID de orden');
        }

        return new WebhookPayload(
            gatewayTransactionId: (string) ($payment['id'] ?? $orderUuid),
            status: $this->normalizeStatus($paymentStatus, $orderStatus),
            referenceId: $orderUuid,
            raw: $request->all(),
        );
    }

    private function getAccessToken(): string
    {
        $clientId = $this->config['client_id'] ?? null;
        $clientSecret = $this->config['client_secret'] ?? null;

        if (!$clientId || !$clientSecret) {
            throw new PaymentException('viüMi: client_id y client_secret no configurados');
        }

        $cacheKey = 'viumi_oauth_token_' . md5($clientId);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($clientId, $clientSecret) {
            $authBase = $this->isSandbox() ? self::AUTH_BASE_SANDBOX : self::AUTH_BASE_PRODUCTION;

            $response = Http::post("{$authBase}/oauth/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => '*',
            ]);

            if (!$response->successful()) {
                throw new PaymentException(
                    'viüMi: error de autenticación OAuth: ' . $response->body()
                );
            }

            $data = $response->json();
            $token = $data['access_token'] ?? null;

            if (!$token) {
                throw new PaymentException('viüMi: no se recibió access_token');
            }

            $expiresIn = $data['expires_in'] ?? 3600;
            Cache::put($cacheKey, $token, (int) $expiresIn - 60);

            return $token;
        });
    }

    private function apiPost(string $path, array $payload): array
    {
        $token = $this->getAccessToken();
        $base = $this->isSandbox() ? self::API_BASE_SANDBOX : self::API_BASE_PRODUCTION;

        $response = Http::withToken($token)
            ->withHeaders([
                'Content-Type' => 'application/vnd.api+json',
                'Accept' => 'application/vnd.api+json',
            ])
            ->post("{$base}{$path}", $payload);

        if ($response->status() === 401 && !$this->retried) {
            $this->retried = true;
            Cache::forget('viumi_oauth_token_' . md5($this->config['client_id'] ?? ''));
            return $this->apiPost($path, $payload);
        }

        if (!$response->successful()) {
            throw new PaymentException(
                "viüMi: error en POST {$path}: {$response->body()}"
            );
        }

        return $response->json() ?? [];
    }

    private function apiGet(string $path): array
    {
        $token = $this->getAccessToken();
        $base = $this->isSandbox() ? self::API_BASE_SANDBOX : self::API_BASE_PRODUCTION;

        $response = Http::withToken($token)
            ->withHeaders([
                'Content-Type' => 'application/vnd.api+json',
                'Accept' => 'application/vnd.api+json',
            ])
            ->get("{$base}{$path}");

        if ($response->status() === 401 && !$this->retried) {
            $this->retried = true;
            Cache::forget('viumi_oauth_token_' . md5($this->config['client_id'] ?? ''));
            return $this->apiGet($path);
        }

        if (!$response->successful()) {
            throw new PaymentException(
                "viüMi: error en GET {$path}: {$response->body()}"
            );
        }

        return $response->json() ?? [];
    }

    private function isSandbox(): bool
    {
        return ($this->config['environment'] ?? 'sandbox') === 'sandbox';
    }

    private function toCents(float|int $amount): int
    {
        return (int) round($amount * 100);
    }
}
