<?php

namespace App\Services\ProductLookup;

use App\Models\GlobalProduct;

class ProductLookupResult
{
    public function __construct(
        public readonly bool $found,
        public readonly ?GlobalProduct $globalProduct = null,
        public readonly ?string $source = null,
    ) {}
}
