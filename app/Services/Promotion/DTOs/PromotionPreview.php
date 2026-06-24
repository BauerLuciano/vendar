<?php

namespace App\Services\Promotion\DTOs;

class PromotionPreview
{
    public function __construct(
        public readonly int $totalProducts = 0,
        public readonly array $productPreviews = [],
        public readonly ?float $originalPrice = null,
        public readonly ?float $finalPrice = null,
        public readonly ?float $discountAmount = null,
        public readonly ?string $discountLabel = null,
        public readonly array $warnings = [],
        public readonly array $conflicts = [],
    ) {}

    public function toArray(): array
    {
        return [
            'total_products' => $this->totalProducts,
            'product_previews' => $this->productPreviews,
            'original_price' => $this->originalPrice,
            'final_price' => $this->finalPrice,
            'discount_amount' => $this->discountAmount,
            'discount_label' => $this->discountLabel,
            'warnings' => $this->warnings,
            'conflicts' => $this->conflicts,
        ];
    }
}
