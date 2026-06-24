<?php

namespace App\Services\Promotion\Evaluators;

use App\Models\Producto;
use App\Models\PromotionRule;
use App\Services\Promotion\Contracts\PromotionRuleEvaluator;

class ProductMarginEvaluator implements PromotionRuleEvaluator
{
    public function evaluate(PromotionRule $rule, Producto $producto): bool
    {
        if ($producto->precio_venta <= 0) {
            return false;
        }

        $price = $producto->precio_venta;
        $margin = $price;

        $ruleValue = (float) $rule->value;

        return match ($rule->operator) {
            '>' => $margin > $ruleValue,
            '<' => $margin < $ruleValue,
            '=' => abs($margin - $ruleValue) < 0.01,
            '>=' => $margin >= $ruleValue,
            '<=' => $margin <= $ruleValue,
            default => false,
        };
    }
}
