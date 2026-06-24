<?php

namespace App\Services\Promotion\Evaluators;

use App\Models\Producto;
use App\Models\PromotionRule;
use App\Services\Promotion\Contracts\PromotionRuleEvaluator;

class ProductEvaluator implements PromotionRuleEvaluator
{
    public function evaluate(PromotionRule $rule, Producto $producto): bool
    {
        $targetId = (int) $rule->value;
        $productId = $producto->id;

        return match ($rule->operator) {
            '=' => $productId === $targetId,
            '!=' => $productId !== $targetId,
            'in' => $productId !== null && in_array($productId, array_map('intval', explode(',', $rule->value))),
            default => false,
        };
    }
}
