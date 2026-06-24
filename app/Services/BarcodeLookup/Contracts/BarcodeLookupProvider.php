<?php

namespace App\Services\BarcodeLookup\Contracts;

use App\Services\BarcodeLookup\BarcodeResult;

interface BarcodeLookupProvider
{
    public function identifier(): string;

    public function lookup(string $barcode): ?BarcodeResult;

    public function confidence(): int;
}
