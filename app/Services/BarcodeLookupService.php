<?php

namespace App\Services;

use App\Models\BarcodeCache;
use App\Models\Producto;
use App\Services\BarcodeLookup\BarcodeResult;
use App\Services\BarcodeLookup\BarcodeLookupProviderRegistry;

class BarcodeLookupService
{
    public function __construct(
        private readonly BarcodeLookupProviderRegistry $registry,
    ) {}

    public function lookupLocal(string $barcode, int $comercioId): ?Producto
    {
        return Producto::where('codigo_barras', $barcode)
            ->whereHas('sucursales', fn ($q) => $q->where('comercio_id', $comercioId))
            ->first();
    }

    public function lookupExternal(string $barcode): ?BarcodeResult
    {
        $cached = BarcodeCache::where('codigo_barras', $barcode)->first();
        if ($cached) {
            return new BarcodeResult(
                nombre: $cached->nombre,
                marca: $cached->marca,
                categoria: $cached->categoria,
                presentacion: $cached->presentacion,
                imagen: $cached->imagen,
                descripcion: $cached->descripcion,
            );
        }

        $result = null;
        foreach ($this->registry->getAllEnabled() as $provider) {
            try {
                $providerResult = $provider->lookup($barcode);
                if ($providerResult) {
                    $result = $result
                        ? $result->merge($providerResult)
                        : $providerResult;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    "Barcode provider {$provider->identifier()} failed for {$barcode}: {$e->getMessage()}"
                );
            }
        }

        if ($result) {
            BarcodeCache::updateOrCreate(
                ['codigo_barras' => $barcode],
                [
                    'nombre' => $result->nombre,
                    'marca' => $result->marca,
                    'categoria' => $result->categoria,
                    'presentacion' => $result->presentacion,
                    'imagen' => $result->imagen,
                    'descripcion' => $result->descripcion,
                ]
            );
        }

        return $result;
    }
}
