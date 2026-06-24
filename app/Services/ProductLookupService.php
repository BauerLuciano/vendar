<?php

namespace App\Services;

use App\Contracts\ProductProvider;
use App\Models\GlobalProduct;
use App\Services\BarcodeLookup\BarcodeResult;
use App\Services\ProductLookup\ProductLookupResult;
use Illuminate\Support\Facades\Log;

class ProductLookupService
{
    public function __construct(
        private readonly array $providers,
    ) {}

    public function lookup(string $barcode): ProductLookupResult
    {
        $gp = GlobalProduct::where('codigo_barras', $barcode)->first();
        if ($gp) {
            Log::info("GlobalProduct hit for {$barcode}");
            return new ProductLookupResult(true, $gp, 'global_product');
        }

        foreach ($this->providers as $provider) {
            try {
                $result = $provider->lookup($barcode);
                if ($result) {
                    $gp = $this->persist($barcode, $result);
                    Log::info("GlobalProduct created from {$provider->identifier()} for {$barcode}");
                    return new ProductLookupResult(true, $gp, $provider->identifier());
                }
            } catch (\Throwable $e) {
                Log::warning("ProductProvider {$provider->identifier()} failed for {$barcode}: {$e->getMessage()}");
            }
        }

        Log::info("Product not found anywhere for {$barcode}");
        return new ProductLookupResult(false);
    }

    public function createFromManual(array $data): GlobalProduct
    {
        $gp = GlobalProduct::updateOrCreate(
            ['codigo_barras' => $data['codigo_barras']],
            [
                'nombre' => $data['nombre'] ?? null,
                'marca' => $data['marca'] ?? null,
                'categoria' => $data['categoria'] ?? null,
                'presentacion' => $data['presentacion'] ?? null,
                'peso_gramos' => $data['peso_gramos'] ?? null,
                'imagen' => $data['imagen'] ?? null,
                'descripcion' => $data['descripcion'] ?? null,
                'fabricante' => $data['fabricante'] ?? null,
                'pais_origen' => $data['pais_origen'] ?? null,
                'provider' => 'manual',
                'datos_extra' => null,
            ]
        );

        Log::info("GlobalProduct manually created for {$data['codigo_barras']}");

        return $gp;
    }

    private function persist(string $barcode, BarcodeResult $result): GlobalProduct
    {
        return GlobalProduct::updateOrCreate(
            ['codigo_barras' => $barcode],
            [
                'nombre' => $result->nombre,
                'marca' => $result->marca,
                'categoria' => $result->categoria,
                'presentacion' => $result->presentacion,
                'peso_gramos' => $result->pesoGramos,
                'imagen' => $result->imagen,
                'descripcion' => $result->descripcion,
                'fabricante' => $result->fabricante,
                'pais_origen' => $result->paisOrigen,
                'provider' => $result->provider,
                'datos_extra' => $result->datosExtra,
            ]
        );
    }
}
