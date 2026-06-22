<?php

namespace App\Services;

use App\Models\BarcodeCache;
use App\Models\Producto;
use App\Services\BarcodeLookup\BarcodeResult;
use App\Services\BarcodeLookup\OpenFoodFactsStrategy;

class BarcodeLookupService
{
    public function __construct(
        private readonly OpenFoodFactsStrategy $openFoodFacts,
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

        $result = $this->openFoodFacts->lookup($barcode);

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
