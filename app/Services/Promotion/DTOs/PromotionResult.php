<?php

namespace App\Services\Promotion\DTOs;

class PromotionResult
{
    public function __construct(
        public readonly array $promotions = [],
        public readonly ?float $originalPrice = null,
        public readonly ?float $finalPrice = null,
        public readonly ?float $discountAmount = null,
        public readonly ?string $discountLabel = null,
        public readonly ?AppliedPromotion $bestPromotion = null,
    ) {}

    public function toArray(): array
    {
        return [
            'promotions' => array_map(fn($p) => $p instanceof AppliedPromotion ? $p->toArray() : $p->toArray(), $this->promotions),
            'original_price' => $this->originalPrice,
            'final_price' => $this->finalPrice,
            'discount_amount' => $this->discountAmount,
            'discount_label' => $this->discountLabel,
            'best_promotion' => $this->bestPromotion?->toArray(),
        ];
    }
}
