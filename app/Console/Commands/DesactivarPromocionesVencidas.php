<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Producto;

class DesactivarPromocionesVencidas extends Command
{
    protected $signature = 'promociones:desactivar-vencidas';
    protected $description = 'Desactiva promociones cuya fecha_fin ya pasó';

    public function handle()
    {
        $count = Producto::where('promocion_activa', true)
            ->whereNotNull('promocion_fin')
            ->where('promocion_fin', '<', now())
            ->update([
                'precio_promocion' => null,
                'promocion_activa' => false,
                'etiqueta_promocion' => null,
                'promocion_tipo' => null,
                'promocion_fin' => null,
            ]);

        $this->info("Se desactivaron {$count} promociones vencidas.");
    }
}
