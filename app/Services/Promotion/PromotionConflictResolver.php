<?php

namespace App\Services\Promotion;

use App\Models\Promotion;
use App\Services\Promotion\DTOs\AppliedPromotion;
use App\Services\Promotion\DTOs\PromotionData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PromotionConflictResolver
{
    private const DISCOUNT_TYPES_NO_PRICE = ['2x1', 'bundle', 'x_for_y'];

    public function resolve(Collection $promotions, float $basePrice): array
    {
        if ($promotions->isEmpty()) {
            return [[], null];
        }

        $sorted = $promotions->sortByDesc('priority')->values();
        $exclusive = $sorted->filter(fn(Promotion $p) => $p->exclusive);
        $nonExclusive = $sorted->filter(fn(Promotion $p) => !$p->exclusive);

        $bestApplied = null;
        $bestFinalPrice = $basePrice;

        if ($exclusive->isNotEmpty()) {
            $bestExclusive = $this->pickBestExclusive($exclusive, $basePrice);
            if ($bestExclusive !== null) {
                $bestApplied = $bestExclusive;
                $bestFinalPrice = $bestExclusive->finalPrice;
            }

            return [
                $bestApplied !== null ? collect([$bestApplied->promotion]) : collect(),
                $bestApplied,
            ];
        }

        if ($nonExclusive->isEmpty()) {
            return [collect(), null];
        }

        $cumulative = $nonExclusive->filter(fn(Promotion $p) => $p->cumulative);
        $nonCumulative = $nonExclusive->filter(fn(Promotion $p) => !$p->cumulative);

        $appliedPromotions = collect();
        $currentPrice = $basePrice;

        if ($cumulative->isNotEmpty()) {
            $totalDiscount = 0;
            foreach ($cumulative as $promo) {
                $discount = $this->resolveDiscount($promo, $currentPrice);
                $totalDiscount += $discount;
            }
            $totalDiscount = min($totalDiscount, $currentPrice);
            $currentPrice = $basePrice - $totalDiscount;
            $appliedPromotions = $appliedPromotions->concat($cumulative);
        }

        if ($nonCumulative->isNotEmpty()) {
            foreach ($nonCumulative as $promo) {
                $discount = $this->resolveDiscount($promo, $basePrice);
                $appliedPromotions->push($promo);

                if ($basePrice - $discount < $currentPrice) {
                    $currentPrice = $basePrice - $discount;
                }
            }
        }

        $bestApplied = new AppliedPromotion(
            promotion: PromotionData::fromModel($sorted->first()),
            originalPrice: $basePrice,
            finalPrice: max(0, $currentPrice),
            discountAmount: max(0, $basePrice - max(0, $currentPrice)),
            discountLabel: $this->makeDiscountLabel($sorted->first(), max(0, $basePrice - max(0, $currentPrice))),
        );

        return [$appliedPromotions, $bestApplied];
    }

    private function pickBestExclusive(Collection $exclusive, float $basePrice): ?AppliedPromotion
    {
        $best = null;
        $bestFinal = $basePrice;

        foreach ($exclusive as $promo) {
            if (in_array($promo->discount_type, self::DISCOUNT_TYPES_NO_PRICE, true)) {
                continue;
            }

            $discount = $this->resolveDiscount($promo, $basePrice);
            $final = max(0, $basePrice - $discount);

            if ($final < $bestFinal) {
                $bestFinal = $final;
                $best = new AppliedPromotion(
                    promotion: PromotionData::fromModel($promo),
                    originalPrice: $basePrice,
                    finalPrice: $final,
                    discountAmount: $discount,
                    discountLabel: $this->makeDiscountLabel($promo, $discount),
                );
            }
        }

        return $best;
    }

    public function resolveDiscount(Promotion $promotion, float $basePrice): float
    {
        if ($basePrice <= 0) {
            return 0;
        }

        return match ($promotion->discount_type) {
            'percent' => $basePrice * ((float) $promotion->value / 100),
            'fixed_amount' => (float) $promotion->value,
            'fixed_price' => max(0, $basePrice - (float) $promotion->value),
            default => 0,
        };
    }

    public function calculateFinalPrice(Promotion $promotion, float $basePrice): float
    {
        $discount = $this->resolveDiscount($promotion, $basePrice);
        if ($promotion->discount_type === 'fixed_price') {
            return max(0, min($basePrice, (float) $promotion->value));
        }
        return max(0, $basePrice - $discount);
    }

    public function makeDiscountLabel(Promotion $promotion, float $discountAmount): string
    {
        return match ($promotion->discount_type) {
            'percent' => "-{$promotion->value}%",
            'fixed_amount' => "-\${$discountAmount}",
            'fixed_price' => '$' . number_format((float) $promotion->value, 2),
            '2x1' => '2x1',
            'bundle' => 'Combo',
            'x_for_y' => "{$promotion->discount_config['x']}x{$promotion->discount_config['y']}",
            default => 'Descuento',
        };
    }
}
