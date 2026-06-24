<?php

namespace App\Services\Promotion\Evaluators;

use App\Models\Producto;
use App\Models\PromotionRule;
use App\Services\Promotion\Contracts\PromotionRuleEvaluator;

class CategoryEvaluator implements PromotionRuleEvaluator
{
    public function evaluate(PromotionRule $rule, Producto $producto): bool
    {
        $categoryId = $producto->categoria_id;
        if ($categoryId === null) {
            return false;
        }

        $ruleValue = (int) $rule->value;

        return match ($rule->operator) {
            '=' => $categoryId === $ruleValue,
            '!=' => $categoryId !== $ruleValue,
            'in' => in_array($categoryId, array_map('intval', explode(',', $rule->value))),
            default => false,
        };
    }
}
