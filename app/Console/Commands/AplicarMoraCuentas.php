<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CuentaCorriente;
use App\Models\MovimientoCuentaCorriente;
use App\Models\Configuracion; // Importamos el modelo de configuración
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AplicarMoraCuentas extends Command
{
    protected $signature = 'cuentas:aplicar-mora';
    protected $description = 'Aplica intereses por mora usando los parámetros de la configuración de la empresa';

    public function handle()
    {
        $this->info("Iniciando proceso de mora...");

        $cuentasAfectadas = 0;
        $configPorComercio = [];

        DB::transaction(function () use (&$cuentasAfectadas, &$configPorComercio) {
            $cuentasEnMora = CuentaCorriente::with('consumidor')
                ->where('estado', true)
                ->where('saldo_deudor', '>', 0)
                ->lockForUpdate()
                ->get();

            foreach ($cuentasEnMora as $cuenta) {
                $comercioId = $cuenta->consumidor?->comercio_id;

                if (!isset($configPorComercio[$comercioId])) {
                    $configPorComercio[$comercioId] = Configuracion::paraComercio($comercioId);
                }
                $config = $configPorComercio[$comercioId];

                $diasDeGracia = (int) ($config['mora_dias_gracia'] ?? 15);
                $tasaInteres = (float) ($config['mora_tasa_interes'] ?? 5);

                if (!$cuenta->fecha_ultimo_movimiento) {
                    continue;
                }

                $fechaLimite = Carbon::now()->subDays($diasDeGracia);
                if ($cuenta->fecha_ultimo_movimiento->greaterThan($fechaLimite)) {
                    continue;
                }

                $montoInteres = $cuenta->saldo_deudor * ($tasaInteres / 100);

                if ($montoInteres > 0) {
                    MovimientoCuentaCorriente::create([
                        'cuenta_corriente_id' => $cuenta->id,
                        'monto' => $montoInteres,
                        'tipo' => 'Cargo',
                        'descripcion' => "Interés automático por mora ({$tasaInteres}%). Configuración: {$diasDeGracia} días de gracia.",
                    ]);

                    $cuenta->saldo_deudor += $montoInteres;
                    $cuenta->fecha_ultimo_movimiento = now();
                    $cuenta->save();

                    $cuentasAfectadas++;
                }
            }
        });

        $this->info("¡Éxito! Se aplicó mora a {$cuentasAfectadas} cuentas.");
    }
}