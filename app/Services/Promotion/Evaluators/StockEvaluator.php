<?php

namespace App\Services\Promotion\Evaluators;

use App\Models\Producto;
use App\Models\PromotionRule;
use App\Services\Promotion\Contracts\PromotionRuleEvaluator;

class StockEvaluator implements PromotionRuleEvaluator
{
    public function evaluate(PromotionRule $rule, Producto $producto): bool
    {
        $stock = $producto->sucursales()->sum('cantidad_fisica');
        $ruleValue = (float) $rule->value;

        return match ($rule->operator) {
            '>' => $stock > $ruleValue,
            '<' => $stock < $ruleValue,
            '=' => abs($stock - $ruleValue) < 0.001,
            '>=' => $stock >= $ruleValue,
            '<=' => $stock <= $ruleValue,
            default => false,
        };
    }
}
