<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Lote;
use App\Models\ReglaLiquidacion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AplicarLiquidaciones extends Command
{
    protected $signature = 'inventario:liquidar-lotes';
    protected $description = 'Revisa lotes por vencer y les activa el estado de liquidación preventiva';

    public function handle()
    {
        $this->info("Iniciando el escaneo de Lotes para Liquidación Preventiva...");

        // 1. Buscamos todas las reglas que estén encendidas (estado = true)
        $reglasActivas = ReglaLiquidacion::where('estado', true)->get();

        if ($reglasActivas->isEmpty()) {
            $this->info("No hay reglas de liquidación activas en el sistema.");
            return;
        }

        $lotesAfectados = 0;

        DB::beginTransaction();
        try {
            foreach ($reglasActivas as $regla) {
                // 2. Calculamos la "Fecha Gatillo" (Hoy + los días de anticipación)
                // Ej: Si hoy es 01/05 y la regla es 5 días, la fecha gatillo es 06/05.
                $fechaGatillo = Carbon::now()->addDays($regla->dias_anticipacion);

                // 3. Buscamos lotes de este producto que tengan stock, que NO estén en liquidación aún,
                // y cuya fecha de vencimiento sea igual o menor a la fecha gatillo.
                $lotesPorVencer = Lote::where('producto_id', $regla->producto_id)
                    ->where('stock_actual', '>', 0)
                    ->where('estado_liquidacion', false)
                    ->whereDate('fecha_vencimiento', '<=', $fechaGatillo)
                    ->get();

                foreach ($lotesPorVencer as $lote) {
                    // 4. Encendemos la bandera de liquidación
                    $lote->estado_liquidacion = true;
                    $lote->save();
                    
                    $lotesAfectados++;
                }
            }
            DB::commit();
            $this->info("Proceso terminado. Se pusieron {$lotesAfectados} lote(s) en liquidación.");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Hubo un error en el robot liquidador: " . $e->getMessage());
        }
    }
}