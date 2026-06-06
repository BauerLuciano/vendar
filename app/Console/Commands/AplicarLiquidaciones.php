<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\ReglaLiquidacion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AplicarLiquidaciones extends Command
{
    protected $signature = 'inventario:liquidar-lotes';
    protected $description = 'Revisa lotes por vencer y les activa el estado de liquidación preventiva + promociones automáticas';

    public function handle()
    {
        $this->info("Iniciando el escaneo de Lotes para Liquidación Preventiva...");

        $reglasActivas = ReglaLiquidacion::where('estado', true)->get();

        if ($reglasActivas->isEmpty()) {
            $this->info("No hay reglas de liquidación activas en el sistema.");
            return;
        }

        $lotesAfectados = 0;
        $promosAfectadas = 0;

        DB::transaction(function () use ($reglasActivas, &$lotesAfectados, &$promosAfectadas) {
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
                    if ($producto && $producto->promocion_tipo !== 'manual') {
                        $precioPromo = round($producto->precio_venta * (1 - $regla->porcentaje_descuento / 100), 2);
                        $producto->update([
                            'precio_promocion' => $precioPromo,
                            'promocion_activa' => true,
                            'etiqueta_promocion' => '🔥 Promoción',
                            'promocion_tipo' => 'vencimiento',
                            'promocion_fin' => $fechaGatillo,
                        ]);
                        $promosAfectadas++;
                    }
                }
            }
        });

        $this->info("Proceso terminado. Lotes en liquidación: {$lotesAfectados}. Promociones activadas: {$promosAfectadas}.");
    }
}