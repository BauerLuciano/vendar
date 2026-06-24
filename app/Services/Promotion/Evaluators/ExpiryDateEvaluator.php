<?php

namespace App\Services\Promotion\Evaluators;

use App\Models\Producto;
use App\Models\PromotionRule;
use App\Services\Promotion\Contracts\PromotionRuleEvaluator;
use Carbon\Carbon;

class ExpiryDateEvaluator implements PromotionRuleEvaluator
{
    public function evaluate(PromotionRule $rule, Producto $producto): bool
    {
        $nearestExpiry = $this->getNearestExpiry($producto);
        if ($nearestExpiry === null) {
            return false;
        }

        $ruleValue = (int) $rule->value;

        $daysToExpiry = now()->diffInDays($nearestExpiry, false);

        return match ($rule->operator) {
            '>' => $daysToExpiry > $ruleValue,
            '<' => $daysToExpiry < $ruleValue,
            '=' => (int) $daysToExpiry === $ruleValue,
            '>=' => $daysToExpiry >= $ruleValue,
            '<=' => $daysToExpiry <= $ruleValue,
            default => false,
        };
    }

    private function getNearestExpiry(Producto $producto): ?Carbon
    {
        if (!$producto->relationLoaded('lotes')) {
            return null;
        }

        $nearest = $producto->lotes()
            ->where('fecha_vencimiento', '>=', now())
            ->where('cantidad', '>', 0)
            ->orderBy('fecha_vencimiento')
            ->first();

        return $nearest?->fecha_vencimiento;
    }
}
