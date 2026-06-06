<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Producto;

class LimpiarPromocionesDemo extends Command
{
    protected $signature = 'promociones:limpiar-demo';
    protected $description = 'Elimina todas las promociones de prueba activadas con promociones:demo';

    public function handle()
    {
        $count = Producto::where('promocion_activa', true)
            ->where('promocion_tipo', 'manual')
            ->update([
                'precio_promocion' => null,
                'promocion_activa' => false,
                'etiqueta_promocion' => null,
                'promocion_tipo' => null,
                'promocion_fin' => null,
            ]);

        $this->info("Se limpiaron {$count} promociones de prueba.");
    }
}
