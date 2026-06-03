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
        $config = Configuracion::pluck('valor', 'clave');

        $diasDeGracia = (int) ($config['mora_dias_gracia'] ?? 15);
        $tasaInteres = (float) ($config['mora_tasa_interes'] ?? 5);

        $this->info("Iniciando proceso de mora (Gracia: {$diasDeGracia} días, Tasa: {$tasaInteres}%)...");

        $fechaLimite = Carbon::now()->subDays($diasDeGracia);

        $cuentasAfectadas = 0;

        DB::transaction(function () use ($fechaLimite, $tasaInteres, $diasDeGracia, &$cuentasAfectadas) {
            $cuentasEnMora = CuentaCorriente::where('estado', true)
                ->where('saldo_deudor', '>', 0)
                ->whereDate('fecha_ultimo_movimiento', '<=', $fechaLimite)
                ->lockForUpdate()
                ->get();

            foreach ($cuentasEnMora as $cuenta) {
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