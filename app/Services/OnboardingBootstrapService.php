<?php

namespace App\Services;

use App\Models\Comercio;
use App\Models\User;
use Illuminate\Support\Str;

class OnboardingBootstrapService
{
    public function ensureComercioExists(User $user): Comercio
    {
        $comercio = $user->comercio ?? $user->branch?->comercio;

        if ($comercio) {
            return $comercio;
        }

        $comercio = Comercio::create([
            'nombre'             => $user->name,
            'slug'               => Str::slug($user->name . '-' . Str::random(6)),
            'limite_sucursales'  => 1,
            'limite_usuarios'    => 5,
            'status'             => 'activo',
        ]);

        $user->update(['comercio_id' => $comercio->id]);

        if ($user->branch) {
            $user->branch->update(['comercio_id' => $comercio->id]);
        }

        return $comercio;
    }
}
