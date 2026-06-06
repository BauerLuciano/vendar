<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Producto;
use App\Models\Categoria;

class PromocionesClearDemo extends Command
{
    protected $signature = 'promociones:clear-demo';
    protected $description = 'Elimina todos los productos demo creados con promociones:seed-demo';

    private const PREFIX = 'DEMO - ';

    public function handle()
    {
        $demoProductos = Producto::where('nombre', 'like', self::PREFIX . '%')->pluck('id');

        $count = $demoProductos->count();

        if ($count === 0) {
            $this->info('No hay productos demo para limpiar.');
            return;
        }

        // Detach from all sucursales
        $bar = $this->output->createProgressBar(3);
        $bar->start();

        \Illuminate\Support\Facades\DB::table('producto_sucursal')
            ->whereIn('producto_id', $demoProductos)
            ->delete();

        $bar->advance();
        $this->line('');

        // Delete productos
        Producto::whereIn('id', $demoProductos)->delete();
        $bar->advance();

        $bar->finish();
        $this->newLine();
        $this->info("✅ Se eliminaron {$count} productos demo.");
    }
}
