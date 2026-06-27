<?php

namespace App\Observers;

use App\Models\StoreConfig;
use Illuminate\Support\Facades\Cache;

class StoreConfigObserver
{
    public function saved(StoreConfig $storeConfig): void
    {
        Cache::forget("store_config_{$storeConfig->comercio_id}");
    }

    public function deleted(StoreConfig $storeConfig): void
    {
        Cache::forget("store_config_{$storeConfig->comercio_id}");
    }
}
