<?php

namespace Database\Seeders;

use App\Models\Comercio;
use App\Models\StoreConfig;
use Illuminate\Database\Seeder;

class StoreConfigSeeder extends Seeder
{
    public function run(): void
    {
        $comercios = Comercio::all();

        if ($comercios->isEmpty()) {
            $this->command->info('No comercios found. Skipping StoreConfigSeeder.');

            return;
        }

        foreach ($comercios as $comercio) {
            StoreConfig::firstOrCreate(
                ['comercio_id' => $comercio->id],
                ['config' => StoreConfig::defaultConfig()],
            );

            $this->command->info("StoreConfig created for comercio: {$comercio->nombre}");
        }
    }
}
