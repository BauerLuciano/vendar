<?php

namespace App\Services\ProductLookup\Providers;

use App\Contracts\ProductProvider;
use App\Services\BarcodeLookup\BarcodeResult;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenFoodFactsProvider implements ProductProvider
{
    public function __construct(
        private readonly array $config,
    ) {}

    public function identifier(): string
    {
        return 'openfoodfacts';
    }

    public function confidence(): int
    {
        return 35;
    }

    public function lookup(string $barcode): ?BarcodeResult
    {
        $endpoints = $this->config['endpoints'] ?? [
            'https://world.openfoodfacts.org',
            'https://argentina.openfoodfacts.org',
        ];

        $timeout = $this->config['timeout'] ?? 5;
        $connectTimeout = $this->config['connect_timeout'] ?? 3;
        $userAgent = $this->config['user_agent'] ?? 'VendAR - SaaS - 1.0';

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (string $endpoint) => $pool
                ->as($endpoint)
                ->timeout($timeout)
                ->withOptions(['connect_timeout' => $connectTimeout])
                ->withUserAgent($userAgent)
                ->get("{$endpoint}/api/v0/product/{$barcode}.json"),
            $endpoints
        ));

        foreach ($endpoints as $endpoint) {
            $response = $responses[$endpoint] ?? null;

            if (!$response || $response->failed()) {
                continue;
            }

            $product = $response->json('product');
            if (!$product || ($response->json('status') ?? 0) !== 1) {
                continue;
            }

            Log::info("OpenFoodFacts found product {$barcode} via {$endpoint}");

            return new BarcodeResult(
                nombre: $product['product_name'] ?? null,
                marca: $product['brands'] ?? null,
                categoria: $product['categories'] ?? null,
                presentacion: $product['quantity'] ?? null,
                pesoGramos: $this->parseWeight($product),
                imagen: $product['image_url'] ?? null,
                descripcion: $product['ingredients_text'] ?? $product['description'] ?? null,
                fabricante: $product['manufacturing_places'] ?? $product['brands'] ?? null,
                paisOrigen: $product['countries'] ?? null,
                provider: $this->identifier(),
                datosExtra: $product,
            );
        }

        return null;
    }

    private function parseWeight(array $product): ?float
    {
        $value = $product['product_quantity'] ?? $product['quantity'] ?? null;
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (preg_match('/(\d+)\s*(kg|g|ml|l)/i', $value, $m)) {
            $num = (float) $m[1];
            return match (strtolower($m[2])) {
                'kg' => $num * 1000,
                'l' => $num * 1000,
                default => $num,
            };
        }

        return null;
    }
}
