<?php

namespace App\Services\Promotion;

use App\Models\Promotion;
use App\Models\PromotionRule;
use Illuminate\Support\Collection;

class PromotionRuleService
{
    public function getRules(Promotion $promotion): Collection
    {
        return $promotion->rules()->get();
    }

    public function createRule(Promotion $promotion, array $data): PromotionRule
    {
        return $promotion->rules()->create($data);
    }

    public function updateRule(PromotionRule $rule, array $data): PromotionRule
    {
        $rule->update($data);

        return $rule->fresh();
    }

    public function deleteRule(PromotionRule $rule): void
    {
        $rule->delete();
    }
}
