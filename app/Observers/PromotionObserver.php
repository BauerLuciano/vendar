<?php

namespace App\Observers;

use App\Models\Promotion;
use Illuminate\Support\Facades\Cache;

class PromotionObserver
{
    private function clearPromotionCache(?int $comercioId): void
    {
        $suffix = $comercioId === null ? 'global' : $comercioId;
        Cache::forget("promotions_manual_{$suffix}");
        Cache::forget("promotions_auto_{$suffix}");
    }

    public function saved(Promotion $promotion): void
    {
        $this->clearPromotionCache($promotion->comercio_id);
    }

    public function deleted(Promotion $promotion): void
    {
        $this->clearPromotionCache($promotion->comercio_id);
    }
}
