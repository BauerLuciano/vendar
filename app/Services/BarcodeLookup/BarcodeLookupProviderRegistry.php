<?php

namespace App\Services\BarcodeLookup;

use App\Services\BarcodeLookup\Contracts\BarcodeLookupProvider;

class BarcodeLookupProviderRegistry
{
    private array $providers = [];

    public function register(BarcodeLookupProvider $provider): void
    {
        $this->providers[$provider->identifier()] = $provider;
    }

    public function getAllEnabled(): array
    {
        return array_values($this->providers);
    }

    public function getProvider(string $identifier): ?BarcodeLookupProvider
    {
        return $this->providers[$identifier] ?? null;
    }

    public function hasProvider(string $identifier): bool
    {
        return isset($this->providers[$identifier]);
    }
}
