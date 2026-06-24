<?php

namespace App\Contracts;

use App\Services\BarcodeLookup\BarcodeResult;

interface ProductProvider
{
    public function identifier(): string;

    public function lookup(string $barcode): ?BarcodeResult;

    public function confidence(): int;
}
