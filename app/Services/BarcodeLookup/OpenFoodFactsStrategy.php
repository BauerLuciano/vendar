<?php

namespace App\Services\BarcodeLookup;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenFoodFactsStrategy
{
    private function fetchFromEndpoint(string $endpoint, string $barcode): ?BarcodeResult
    {
        $response = Http::timeout(5)->withOptions(['connect_timeout' => 3])->get("{$endpoint}/api/v0/product/{$barcode}.json");

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
        );
    }

    public function lookup(string $barcode): ?BarcodeResult
    {
        $endpoints = [
            'https://world.openfoodfacts.org',
            'https://argentina.openfoodfacts.org',
        ];

        foreach ($endpoints as $endpoint) {
            try {
                $result = $this->fetchFromEndpoint($endpoint, $barcode);
                if ($result) return $result;
            } catch (\Throwable $e) {
                Log::warning("OpenFoodFacts ({$endpoint}) lookup failed for {$barcode}: {$e->getMessage()}");
            }
        }

        return null;
    }
}
