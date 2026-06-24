<?php

namespace App\Console\Commands;

use App\Models\GlobalProduct;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Promotion;
use App\Models\ReglaLiquidacion;
use App\Models\Sucursal;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AplicarLiquidaciones extends Command
{
    protected $signature = 'inventario:liquidar-lotes';
    protected $description = 'Revisa lotes por vencer y les activa promociones por liquidación';

    public function handle()
    {
        $this->info("Iniciando el escaneo de Lotes para Liquidación Preventiva...");

        $reglasActivas = ReglaLiquidacion::where('estado', true)->get();

        if ($reglasActivas->isEmpty()) {
            $this->info("No hay reglas de liquidación activas en el sistema.");
            return;
        }

        $lotesAfectados = 0;
        $promosCreadas = 0;

        DB::transaction(function () use ($reglasActivas, &$lotesAfectados, &$promosCreadas) {
            foreach ($reglasActivas as $regla) {
                $fechaGatillo = Carbon::now()->addDays($regla->dias_anticipacion);

                $lotesPorVencer = Lote::where('producto_id', $regla->producto_id)
                    ->where('stock_actual', '>', 0)
                    ->where('estado_liquidacion', false)
                    ->whereDate('fecha_vencimiento', '<=', $fechaGatillo)
                    ->lockForUpdate()
                    ->get();

                $lotesRegla = 0;
                foreach ($lotesPorVencer as $lote) {
                    $lote->estado_liquidacion = true;
                    $lote->save();
                    $lotesRegla++;
                }

                $lotesAfectados += $lotesRegla;

                if ($lotesRegla > 0) {
                    $producto = Producto::find($regla->producto_id);
                    if (!$producto) continue;

                    $gp = GlobalProduct::where('codigo_barras', $producto->codigo_barras)->first();
                    if (!$gp) continue;

                    $sucursal = $producto->sucursales()->first();
                    $comercioId = $sucursal?->comercio_id;
                    if (!$comercioId) continue;

                    $existing = Promotion::where('name', "Liquidación - {$producto->nombre}")
                        ->where('comercio_id', $comercioId)
                        ->where('type', 'MANUAL')
                        ->where('active', true)
                        ->first();

                    if ($existing) continue;

                    $promo = Promotion::create([
                        'comercio_id'    => $comercioId,
                        'name'           => "Liquidación - {$producto->nombre}",
                        'description'    => "Liquidación automática por vencimiento de lote (Regla: {$regla->id})",
                        'type'           => 'MANUAL',
                        'discount_type'  => 'percent',
                        'value'          => $regla->porcentaje_descuento,
                        'starts_at'      => now(),
                        'ends_at'        => $fechaGatillo,
                        'active'         => true,
                        'priority'       => 100,
                        'exclusive'      => false,
                        'cumulative'     => false,
                    ]);

                    $promo->products()->syncWithoutDetaching([$gp->id]);
                    $promosCreadas++;
                }
            }
        });

        $this->info("Proceso terminado. Lotes en liquidación: {$lotesAfectados}. Promociones creadas: {$promosCreadas}.");
    }
}
