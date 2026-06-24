<?php

namespace App\Services\BarcodeLookup\Providers;

use App\Services\BarcodeLookup\BarcodeResult;
use App\Services\BarcodeLookup\Contracts\BarcodeLookupProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenFoodFactsProvider implements BarcodeLookupProvider
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

        foreach ($endpoints as $endpoint) {
            try {
                $result = $this->fetchFromEndpoint($endpoint, $barcode);
                if ($result) {
                    return $result;
                }
            } catch (\Throwable $e) {
                Log::warning("OpenFoodFacts ({$endpoint}) lookup failed for {$barcode}: {$e->getMessage()}");
            }
        }

        return null;
    }

    private function fetchFromEndpoint(string $endpoint, string $barcode): ?BarcodeResult
    {
        $timeout = $this->config['timeout'] ?? 5;
        $connectTimeout = $this->config['connect_timeout'] ?? 3;

        $response = Http::timeout($timeout)
            ->withOptions(['connect_timeout' => $connectTimeout])
            ->get("{$endpoint}/api/v0/product/{$barcode}.json");

        if (!$response->successful() || ($response->json('status') ?? 0) !== 1) {
            return null;
        }

        $product = $response->json('product', []);

        return new BarcodeResult(
            nombre: $product['product_name'] ?? null,
            marca: $product['brands'] ?? null,
            categoria: $product['categories'] ?? null,
            presentacion: $product['quantity'] ?? null,
            imagen: $product['image_url'] ?? null,
            descripcion: $product['ingredients_text'] ?? null,
            provider: $this->identifier(),
            datosExtra: $product,
        );
    }
}
