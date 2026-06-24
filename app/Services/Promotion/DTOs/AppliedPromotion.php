<?php

namespace App\Services\Promotion\DTOs;

class AppliedPromotion
{
    public function __construct(
        public readonly PromotionData $promotion,
        public readonly float $originalPrice,
        public readonly float $finalPrice,
        public readonly float $discountAmount,
        public readonly ?string $discountLabel = null,
    ) {}

    public function toArray(): array
    {
        return [
            'promotion' => $this->promotion->toArray(),
            'original_price' => $this->originalPrice,
            'final_price' => $this->finalPrice,
            'discount_amount' => $this->discountAmount,
            'discount_label' => $this->discountLabel,
        ];
    }
}
