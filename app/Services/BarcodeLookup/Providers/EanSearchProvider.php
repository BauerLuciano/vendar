<?php

namespace App\Services\BarcodeLookup\Providers;

use App\Services\BarcodeLookup\BarcodeResult;
use App\Services\BarcodeLookup\Contracts\BarcodeLookupProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EanSearchProvider implements BarcodeLookupProvider
{
    private const int DEFAULT_TIMEOUT = 5;
    private const int DEFAULT_CONNECT_TIMEOUT = 3;
    private const int DEFAULT_MAX_REQUESTS_PER_MONTH = 100;
    private const string API_BASE = 'https://api.ean-search.org/api';
    private const string CACHE_QUOTA_KEY = 'barcode_lookup:eansearch:monthly_quota';

    public function __construct(
        private readonly array $config,
    ) {}

    public function identifier(): string
    {
        return 'eansearch';
    }

    public function confidence(): int
    {
        return 50;
    }

    public function lookup(string $barcode): ?BarcodeResult
    {
        $token = $this->config['api_token'] ?? env('EANSEARCH_API_TOKEN');
        if (blank($token)) {
            Log::warning('EAN-Search lookup skipped: no API token configured');
            return null;
        }

        if (!$this->checkQuota()) {
            Log::warning("EAN-Search lookup skipped for {$barcode}: monthly quota exhausted");
            return null;
        }

        try {
            $result = $this->fetchFromApi($token, $barcode);
            if ($result) {
                $this->incrementQuota();
            }
            return $result;
        } catch (\Throwable $e) {
            Log::error("EAN-Search lookup failed for {$barcode}: {$e->getMessage()}", [
                'exception' => $e,
                'barcode' => $barcode,
            ]);
            return null;
        }
    }

    private function fetchFromApi(string $token, string $barcode): ?BarcodeResult
    {
        $timeout = $this->config['timeout'] ?? self::DEFAULT_TIMEOUT;
        $connectTimeout = $this->config['connect_timeout'] ?? self::DEFAULT_CONNECT_TIMEOUT;

        $response = Http::timeout($timeout)
            ->withOptions(['connect_timeout' => $connectTimeout])
            ->withUserAgent($this->config['user_agent'] ?? 'VendAR - SaaS - 1.0')
            ->get(self::API_BASE, [
                'token' => $token,
                'op' => 'barcode-lookup',
                'format' => 'json',
                'ean' => $barcode,
            ]);

        if (!$response->successful()) {
            Log::warning("EAN-Search returned HTTP {$response->status()} for {$barcode}", [
                'body' => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();
        if (!is_array($data)) {
            return null;
        }

        $product = $this->extractProductData($data);
        if ($product === null) {
            return null;
        }

        return new BarcodeResult(
            nombre: $product['name'] ?? $product['product_name'] ?? null,
            marca: $product['brand'] ?? null,
            categoria: $product['category'] ?? null,
            presentacion: $product['quantity'] ?? $product['size'] ?? null,
            pesoGramos: $this->parseWeight($product),
            imagen: $product['image'] ?? $product['image_url'] ?? null,
            descripcion: $product['description'] ?? null,
            fabricante: $product['manufacturer'] ?? $product['brand'] ?? null,
            paisOrigen: $product['origin'] ?? $product['country'] ?? null,
            provider: $this->identifier(),
            datosExtra: $product,
        );
    }

    private function extractProductData(array $data): ?array
    {
        if (!empty($data['product']) && is_array($data['product'])) {
            return $data['product'];
        }

        if (!empty($data['products']) && is_array($data['products'])) {
            foreach ($data['products'] as $product) {
                if (is_array($product) && !empty($product)) {
                    return $product;
                }
            }
        }

        if (!empty($data['name'])) {
            return $data;
        }

        if (isset($data['error']) || isset($data['errors'])) {
            return null;
        }

        return null;
    }

    private function parseWeight(array $product): ?float
    {
        $value = $product['weight'] ?? $product['grams'] ?? null;
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (preg_match('/\b(\d+)\s*g\b/i', $value, $m)) {
            return (float) $m[1];
        }

        return null;
    }

    private function checkQuota(): bool
    {
        $max = $this->config['max_requests_per_month'] ?? self::DEFAULT_MAX_REQUESTS_PER_MONTH;
        $used = Cache::get(self::CACHE_QUOTA_KEY, 0);

        return $used < $max;
    }

    private function incrementQuota(): void
    {
        if (!Cache::has(self::CACHE_QUOTA_KEY)) {
            Cache::set(self::CACHE_QUOTA_KEY, 0, now()->endOfMonth());
        }

        Cache::increment(self::CACHE_QUOTA_KEY);
    }
}
