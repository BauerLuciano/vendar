<?php

namespace App\Services\Payment;

use App\Enums\PaymentChannel;
use App\Models\Comercio;
use App\Services\Payment\Contracts\PaymentGateway;
use App\Services\Payment\Contracts\CheckoutRequest;
use App\Services\Payment\Contracts\CheckoutResponse;
use App\Services\Payment\Contracts\PaymentStatusResponse;
use App\Services\Payment\Exceptions\PaymentException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PaymentService
{
    private string $context = 'commerce';
    private ?Comercio $comercio = null;

    public function forCommerce(Comercio $comercio): static
    {
        $this->context = 'commerce';
        $this->comercio = $comercio;
        return $this;
    }

    public function forPlatform(): static
    {
        $this->context = 'platform';
        $this->comercio = null;
        return $this;
    }

    public function isGatewayProvider(string $provider): bool
    {
        return class_exists($this->gatewayClass($provider));
    }

    public function getRegisteredProviders(): array
    {
        $path = app_path('Services/Payment/Gateways');
        $files = glob($path . '/*Gateway.php');
        $providers = [];

        foreach ($files as $file) {
            $basename = basename($file, 'Gateway.php');
            $providers[] = strtolower($basename);
        }

        return $providers;
    }

    public function gateway(string $provider): PaymentGateway
    {
        $config = match ($this->context) {
            'commerce' => $this->resolveCommerceConfig($provider),
            'platform' => $this->resolvePlatformConfig($provider),
        };

        return $this->buildGateway($provider, $config);
    }

    public function createCheckout(string $provider, CheckoutRequest $request): CheckoutResponse
    {
        return $this->gateway($provider)->createCheckout($request);
    }

    public function initiatePosPayment(
        string $provider,
        CheckoutRequest $request,
        PaymentChannel $channel,
        array $options = [],
    ): CheckoutResponse {
        $gateway = $this->gateway($provider);

        if (!$gateway->supportsChannel($channel)) {
            throw new PaymentException("{$provider} no soporta el canal {$channel->value}");
        }

        return $gateway->initiatePayment($request, $channel, $options);
    }

    public function getPaymentStatus(string $provider, string $gatewayTransactionId): PaymentStatusResponse
    {
        return $this->gateway($provider)->getPaymentStatus($gatewayTransactionId);
    }

    public function availableGateways(Comercio $comercio): Collection
    {
        return $comercio->paymentGateways
            ->where('enabled', true)
            ->map(fn ($pg) => $this->buildGateway($pg->provider, $pg->configuration ?? []));
    }

    private function gatewayClass(string $provider): string
    {
        $className = Str::studly($provider) . 'Gateway';
        return 'App\\Services\\Payment\\Gateways\\' . $className;
    }

    private function resolveCommerceConfig(string $provider): array
    {
        if (!$this->comercio) {
            throw new PaymentException('No hay comercio seleccionado. Usá forCommerce().');
        }

        $pg = $this->comercio->paymentGateways
            ->where('provider', $provider)
            ->first();

        if ($pg) {
            return $pg->configuration ?? [];
        }

        if ($provider === 'mercadopago') {
            return ['access_token' => $this->comercio->mp_access_token];
        }

        return [];
    }

    private function resolvePlatformConfig(string $provider): array
    {
        $gatewayClass = $this->gatewayClass($provider);

        if (!class_exists($gatewayClass)) {
            throw new PaymentException("Proveedor de pago no soportado: {$provider}");
        }

        return config("services.{$provider}", []);
    }

    private function buildGateway(string $provider, array $config): PaymentGateway
    {
        $class = $this->gatewayClass($provider);

        if (!class_exists($class)) {
            throw new PaymentException("Proveedor de pago no soportado: {$provider}");
        }

        return app()->make($class, ['config' => $config]);
    }
}
