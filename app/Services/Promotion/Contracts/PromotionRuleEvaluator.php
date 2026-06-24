<?php

namespace App\Services\Promotion\Contracts;

use App\Models\Producto;
use App\Models\PromotionRule;

interface PromotionRuleEvaluator
{
    public function evaluate(PromotionRule $rule, Producto $producto): bool;
}
