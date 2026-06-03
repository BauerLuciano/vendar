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

        DB::transaction(function () use ($reglasActivas, &$lotesAfectados) {
            foreach ($reglasActivas as $regla) {
                $fechaGatillo = Carbon::now()->addDays($regla->dias_anticipacion);

                $lotesPorVencer = Lote::where('producto_id', $regla->producto_id)
                    ->where('stock_actual', '>', 0)
                    ->where('estado_liquidacion', false)
                    ->whereDate('fecha_vencimiento', '<=', $fechaGatillo)
                    ->lockForUpdate()
                    ->get();

                foreach ($lotesPorVencer as $lote) {
                    $lote->estado_liquidacion = true;
                    $lote->save();

                    $lotesAfectados++;
                }
            }
        });

        $this->info("Proceso terminado. Se pusieron {$lotesAfectados} lote(s) en liquidación.");
    }
}