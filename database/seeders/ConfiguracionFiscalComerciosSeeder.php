<?php

namespace Database\Seeders;

use App\Models\Comercio;
use App\Models\ConfiguracionFiscalComercio;
use Illuminate\Database\Seeder;

class ConfiguracionFiscalComerciosSeeder extends Seeder
{
    public function run(): void
    {
        $comercios = Comercio::all();

        if ($comercios->isEmpty()) {
            $this->command->info('No comercios found. Skipping ConfiguracionFiscalComerciosSeeder.');

            return;
        }

        foreach ($comercios as $comercio) {
            ConfiguracionFiscalComercio::firstOrCreate(
                ['comercio_id' => $comercio->id],
                [
                    'entorno' => 'produccion',
                    'estado_modulo' => 'sin_datos',
                ]
            );

            $this->command->info("Configuracion fiscal created for comercio: {$comercio->nombre}");
        }
    }
}
