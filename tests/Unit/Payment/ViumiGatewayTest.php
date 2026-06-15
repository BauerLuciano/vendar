<?php

namespace Tests\Unit\Payment;

use App\Enums\PaymentStatus;
use App\Models\Comercio;
use App\Services\Payment\Contracts\CheckoutRequest;
use App\Services\Payment\Contracts\CheckoutResponse;
use App\Services\Payment\Contracts\PaymentStatusResponse;
use App\Services\Payment\Contracts\WebhookPayload;
use App\Services\Payment\Gateways\ViumiGateway;
use App\Services\Payment\Exceptions\PaymentException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ViumiGatewayTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'environment' => 'sandbox',
        ];
    }

    public function test_identifier(): void
    {
        $gateway = new ViumiGateway($this->config);
        $this->assertEquals('viumi', $gateway->identifier());
    }

    public function test_display_name(): void
    {
        $gateway = new ViumiGateway($this->config);
        $this->assertEquals('viüMi', $gateway->displayName());
    }

    public function test_supports_checkout(): void
    {
        $gateway = new ViumiGateway($this->config);
        $this->assertTrue($gateway->supportsCheckout());
    }

    public function test_supports_webhook(): void
    {
        $gateway = new ViumiGateway($this->config);
        $this->assertTrue($gateway->supportsWebhook());
    }

    public function test_supports_recurring(): void
    {
        $gateway = new ViumiGateway($this->config);
        $this->assertFalse($gateway->supportsRecurring());
    }

    public function test_get_webhook_url(): void
    {
        $gateway = new ViumiGateway($this->config);
        $this->assertEquals(url('/api/webhook/viumi'), $gateway->getWebhookUrl());
    }

    public function test_throws_on_missing_credentials(): void
    {
        $this->expectException(PaymentException::class);
        $gateway = new ViumiGateway([]);
        $gateway->createCheckout($this->makeCheckoutRequest());
    }

    public function test_create_checkout_success(): void
    {
        $orderUuid = '550e8400-e29b-41d4-a716-446655440000';
        $checkoutUrl = 'https://api-mpos-macro.stg.geopagos.io/orders/' . $orderUuid;

        $this->mockOAuth();
        $this->mockCreateOrder($orderUuid, $checkoutUrl);

        $gateway = new ViumiGateway($this->config);
        $request = $this->makeCheckoutRequest([
            'items' => [
                ['title' => 'Producto 1', 'unit_price' => 1500.00, 'quantity' => 2],
            ],
            'successUrl' => 'https://tienda.com/success',
            'failureUrl' => 'https://tienda.com/failure',
            'notificationUrl' => 'https://tienda.com/webhook',
        ]);

        $response = $gateway->createCheckout($request);

        $this->assertInstanceOf(CheckoutResponse::class, $response);
        $this->assertEquals($checkoutUrl, $response->checkoutUrl);
        $this->assertEquals($orderUuid, $response->gatewayTransactionId);
        $this->assertEquals(PaymentStatus::PENDING, $response->status);
    }

    public function test_create_checkout_without_optional_urls(): void
    {
        $orderUuid = '660e8400-e29b-41d4-a716-446655440001';

        $this->mockOAuth();
        Http::fake([
            'https://api-mpos-macro.stg.geopagos.io/api/v2/orders' => Http::response([
                'data' => [
                    'id' => '/api/v2/orders/' . $orderUuid,
                    'attributes' => [
                        'uuid' => $orderUuid,
                        'links' => [
                            'checkout' => 'https://api-mpos-macro.stg.geopagos.io/orders/' . $orderUuid,
                        ],
                    ],
                ],
            ], 201),
        ]);

        $gateway = new ViumiGateway($this->config);
        $request = $this->makeCheckoutRequest([
            'successUrl' => null,
            'failureUrl' => null,
            'notificationUrl' => null,
        ]);

        $response = $gateway->createCheckout($request);
        $this->assertNotNull($response->checkoutUrl);
    }

    public function test_get_payment_status_approved(): void
    {
        $orderUuid = '770e8400-e29b-41d4-a716-446655440002';

        $this->mockOAuth();
        Http::fake([
            "https://api-mpos-macro.stg.geopagos.io/api/v2/orders/{$orderUuid}" => Http::response([
                'data' => [
                    'attributes' => [
                        'uuid' => $orderUuid,
                        'status' => 'SUCCESS',
                        'price' => ['currency' => '032', 'amount' => 150000],
                        'payment' => [
                            'id' => 12345,
                            'status' => 'APPROVED',
                        ],
                    ],
                ],
            ]),
        ]);

        $gateway = new ViumiGateway($this->config);
        $status = $gateway->getPaymentStatus($orderUuid);

        $this->assertInstanceOf(PaymentStatusResponse::class, $status);
        $this->assertEquals(PaymentStatus::APPROVED, $status->status);
        $this->assertEquals('12345', $status->gatewayTransactionId);
        $this->assertEquals(1500.00, $status->amount);
    }

    public function test_get_payment_status_rejected(): void
    {
        $orderUuid = '880e8400-e29b-41d4-a716-446655440003';

        $this->mockOAuth();
        Http::fake([
            "https://api-mpos-macro.stg.geopagos.io/api/v2/orders/{$orderUuid}" => Http::response([
                'data' => [
                    'attributes' => [
                        'uuid' => $orderUuid,
                        'status' => 'SUCCESS',
                        'payment' => [
                            'id' => 12346,
                            'status' => 'REJECTED',
                        ],
                    ],
                ],
            ]),
        ]);

        $gateway = new ViumiGateway($this->config);
        $status = $gateway->getPaymentStatus($orderUuid);

        $this->assertEquals(PaymentStatus::REJECTED, $status->status);
    }

    public function test_get_payment_status_pending(): void
    {
        $orderUuid = '990e8400-e29b-41d4-a716-446655440004';

        $this->mockOAuth();
        Http::fake([
            "https://api-mpos-macro.stg.geopagos.io/api/v2/orders/{$orderUuid}" => Http::response([
                'data' => [
                    'attributes' => [
                        'uuid' => $orderUuid,
                        'status' => 'PENDING',
                        'payment' => [
                            'id' => 12347,
                            'status' => 'PENDING',
                        ],
                    ],
                ],
            ]),
        ]);

        $gateway = new ViumiGateway($this->config);
        $status = $gateway->getPaymentStatus($orderUuid);

        $this->assertEquals(PaymentStatus::PENDING, $status->status);
    }

    public function test_parse_webhook_payload_approved(): void
    {
        $gateway = new ViumiGateway($this->config);
        $request = Request::create('/webhook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'data' => [
                'type' => 'Payment',
                'order' => [
                    'uuid' => 'aa0e8400-e29b-41d4-a716-446655440005',
                    'status' => 'SUCCESS',
                ],
                'payment' => [
                    'id' => 12348,
                    'authorizationCode' => '901159',
                    'status' => 'APPROVED',
                ],
            ],
        ]));

        $payload = $gateway->parseWebhookPayload($request);

        $this->assertInstanceOf(WebhookPayload::class, $payload);
        $this->assertEquals(PaymentStatus::APPROVED, $payload->status);
        $this->assertEquals('12348', $payload->gatewayTransactionId);
        $this->assertEquals('aa0e8400-e29b-41d4-a716-446655440005', $payload->referenceId);
    }

    public function test_parse_webhook_payload_rejected(): void
    {
        $gateway = new ViumiGateway($this->config);
        $request = Request::create('/webhook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'data' => [
                'order' => [
                    'uuid' => 'bb0e8400-e29b-41d4-a716-446655440006',
                    'status' => 'SUCCESS',
                ],
                'payment' => [
                    'id' => 12349,
                    'status' => 'REJECTED',
                ],
            ],
        ]));

        $payload = $gateway->parseWebhookPayload($request);

        $this->assertEquals(PaymentStatus::REJECTED, $payload->status);
    }

    public function test_parse_webhook_payload_missing_data_throws(): void
    {
        $gateway = new ViumiGateway($this->config);
        $request = Request::create('/webhook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $this->expectException(PaymentException::class);
        $gateway->parseWebhookPayload($request);
    }

    public function test_verify_webhook_signature_returns_true(): void
    {
        $gateway = new ViumiGateway($this->config);
        $request = Request::create('/webhook', 'POST');

        $this->assertTrue($gateway->verifyWebhookSignature($request));
    }

    public function test_oauth_token_is_cached(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn('cached-token-123');

        Http::fake([
            'https://api-mpos-macro.stg.geopagos.io/api/v2/orders/*' => Http::response([
                'data' => [
                    'attributes' => [
                        'uuid' => 'cc0e8400-e29b-41d4-a716-446655440007',
                        'links' => ['checkout' => 'https://checkout.test/cc0e8400'],
                    ],
                ],
            ]),
        ]);

        $gateway = new ViumiGateway($this->config);
        $gateway->getPaymentStatus('cc0e8400-e29b-41d4-a716-446655440007');

        $this->assertTrue(true);
    }

    public function test_retries_on_401(): void
    {
        $orderUuid = 'dd0e8400-e29b-41d4-a716-446655440008';

        Cache::shouldReceive('remember')
            ->once()
            ->andReturn('expired-token');

        Cache::shouldReceive('forget')
            ->once()
            ->andReturn(true);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturn('fresh-token');

        Http::fake([
            "https://api-mpos-macro.stg.geopagos.io/api/v2/orders/{$orderUuid}" => Http::sequence()
                ->push([], 401)
                ->push([
                    'data' => [
                        'attributes' => [
                            'uuid' => $orderUuid,
                            'status' => 'SUCCESS',
                            'payment' => ['id' => 12350, 'status' => 'APPROVED'],
                        ],
                    ],
                ]),
        ]);

        $gateway = new ViumiGateway($this->config);
        $status = $gateway->getPaymentStatus($orderUuid);

        $this->assertEquals(PaymentStatus::APPROVED, $status->status);
    }

    public function test_production_uses_production_urls(): void
    {
        $prodConfig = [
            'client_id' => 'prod-client-id',
            'client_secret' => 'prod-client-secret',
            'environment' => 'production',
        ];

        Cache::shouldReceive('remember')
            ->once()
            ->andReturn('prod-token');

        Http::fake([
            'https://auth.prd.geopagos.io/*' => Http::response(['access_token' => 'x']),
            'https://api.viumi.com.ar/api/v2/orders/*' => Http::response([
                'data' => [
                    'attributes' => [
                        'uuid' => 'ee0e8400-e29b-41d4-a716-446655440009',
                        'status' => 'SUCCESS',
                        'payment' => ['id' => 12351, 'status' => 'APPROVED'],
                    ],
                ],
            ]),
        ]);

        $gateway = new ViumiGateway($prodConfig);
        $status = $gateway->getPaymentStatus('ee0e8400-e29b-41d4-a716-446655440009');

        $this->assertEquals(PaymentStatus::APPROVED, $status->status);
    }

    private function mockOAuth(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn('test-access-token');
    }

    private function mockCreateOrder(string $uuid, string $checkoutUrl): void
    {
        Http::fake([
            'https://api-mpos-macro.stg.geopagos.io/api/v2/orders' => Http::response([
                'data' => [
                    'id' => '/api/v2/orders/' . $uuid,
                    'type' => 'Order',
                    'attributes' => [
                        'uuid' => $uuid,
                        'links' => [
                            'checkout' => $checkoutUrl,
                        ],
                    ],
                ],
            ], 201),
        ]);
    }

    private function makeCheckoutRequest(array $overrides = []): CheckoutRequest
    {
        $defaults = [
            'referenceId' => '123',
            'amount' => 3000.00,
            'title' => 'Test Order',
            'description' => 'Test',
            'items' => [
                ['title' => 'Test Item', 'unit_price' => 3000.00, 'quantity' => 1],
            ],
            'currencyId' => 'ARS',
            'successUrl' => 'https://tienda.com/success',
            'failureUrl' => 'https://tienda.com/failure',
            'pendingUrl' => 'https://tienda.com/pending',
            'notificationUrl' => 'https://tienda.com/webhook',
            'metadata' => [],
        ];

        $merged = array_merge($defaults, $overrides);

        return new CheckoutRequest(
            referenceId: $merged['referenceId'],
            amount: $merged['amount'],
            title: $merged['title'],
            description: $merged['description'],
            items: $merged['items'],
            currencyId: $merged['currencyId'],
            successUrl: $merged['successUrl'],
            failureUrl: $merged['failureUrl'],
            pendingUrl: $merged['pendingUrl'],
            notificationUrl: $merged['notificationUrl'],
            metadata: $merged['metadata'],
        );
    }
}
