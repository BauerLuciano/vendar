<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorefrontConfigRequest;
use App\Models\StoreConfig;
use Illuminate\Support\Facades\Cache;

class StorefrontConfigController extends Controller
{
    public function update(StorefrontConfigRequest $request)
    {
        $user = $request->user();
        $comercioId = $user->comercio_id ?? $user->branch?->comercio_id;

        $storeConfig = StoreConfig::firstOrCreate(
            ['comercio_id' => $comercioId],
            ['config' => StoreConfig::defaultConfig()]
        );

        $merged = array_replace_recursive(
            $storeConfig->config,
            $request->validated()
        );

        $storeConfig->update(['config' => $merged]);

        Cache::forget("store_config_{$comercioId}");

        return redirect()->back()->with('success', 'Configuración de tienda actualizada con éxito.');
    }

    public function reset()
    {
        $user = request()->user();
        $comercioId = $user->comercio_id ?? $user->branch?->comercio_id;

        $storeConfig = StoreConfig::firstOrCreate(
            ['comercio_id' => $comercioId],
            ['config' => StoreConfig::defaultConfig()]
        );

        $storeConfig->update(['config' => StoreConfig::defaultConfig()]);

        Cache::forget("store_config_{$comercioId}");

        return redirect()->back()->with('success', 'Colores restablecidos a los valores originales.');
    }
}
