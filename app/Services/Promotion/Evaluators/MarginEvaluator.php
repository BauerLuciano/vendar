<?php

namespace App\Services\Promotion\Evaluators;

use App\Models\Producto;
use App\Models\PromotionRule;
use App\Services\Promotion\Contracts\PromotionRuleEvaluator;

class MarginEvaluator implements PromotionRuleEvaluator
{
    public function evaluate(PromotionRule $rule, Producto $producto): bool
    {
        if ($producto->precio_venta <= 0) {
            return false;
        }

        $cost = $producto->precio_costo;
        $price = $producto->precio_venta;
        $margin = (($price - $cost) / $price) * 100;

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
