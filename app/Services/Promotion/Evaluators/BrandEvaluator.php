<?php

namespace App\Services\Promotion\Evaluators;

use App\Models\Producto;
use App\Models\PromotionRule;
use App\Services\Promotion\Contracts\PromotionRuleEvaluator;

class BrandEvaluator implements PromotionRuleEvaluator
{
    public function evaluate(PromotionRule $rule, Producto $producto): bool
    {
        $brandId = $producto->marca_id;
        if ($brandId === null) {
            return false;
        }

        $ruleValue = (int) $rule->value;

        return match ($rule->operator) {
            '=' => $brandId === $ruleValue,
            '!=' => $brandId !== $ruleValue,
            'in' => in_array($brandId, array_map('intval', explode(',', $rule->value))),
            default => false,
        };
    }
}
