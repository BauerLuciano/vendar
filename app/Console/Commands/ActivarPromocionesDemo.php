<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Producto;
use Carbon\Carbon;

class ActivarPromocionesDemo extends Command
{
    protected $signature = 'promociones:demo';
    protected $description = 'Activa promociones de prueba en los primeros 10 productos activos para visualizar el MVP';

    private const ETIQUETAS = ['🔥 Promoción', '⚡ Oferta especial', '💰 Ahorrá hoy', '🏆 Oferta única'];

    private const DESCUENTOS = [10, 15, 20, 25, 30];

    public function handle()
    {
        $productos = Producto::where('estado', true)
            ->where('precio_venta', '>', 0)
            ->limit(10)
            ->get();

        if ($productos->isEmpty()) {
            $this->warn('No hay productos activos con precio válido.');
            return;
        }

        $bar = $this->output->createProgressBar($productos->count());
        $bar->start();

        foreach ($productos as $producto) {
            $descuento = self::DESCUENTOS[array_rand(self::DESCUENTOS)];
            $precioPromo = round($producto->precio_venta * (1 - $descuento / 100), 2);
            $etiqueta = self::ETIQUETAS[array_rand(self::ETIQUETAS)];

            $producto->update([
                'precio_promocion' => $precioPromo,
                'promocion_activa' => true,
                'etiqueta_promocion' => $etiqueta,
                'promocion_tipo' => 'manual',
                'promocion_fin' => Carbon::today()->addDays(30),
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Se activaron {$productos->count()} promociones de prueba.");
    }
}
