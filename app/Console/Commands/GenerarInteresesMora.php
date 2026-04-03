<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CuentaCorriente;
use App\Models\MovimientoCuentaCorriente;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GenerarInteresesMora extends Command
{
    protected $signature = 'app:generar-intereses-mora';
    protected $description = 'Aplica un 5% de interés a cuentas con saldo deudor inactivas por más de 30 días';

    public function handle()
    {
        $diasGracia = 30;
        $porcentajeInteres = 0.05; // 5%
        $fechaLimite = Carbon::now()->subDays($diasGracia);

        $this->info("Buscando cuentas con saldo deudor inactivas desde antes del {$fechaLimite->format('d/m/Y')}...");

        $cuentasVencidas = CuentaCorriente::where('saldo_deudor', '>', 0)
            ->where('updated_at', '<', $fechaLimite)
            ->get();

        if ($cuentasVencidas->isEmpty()) {
            $this->info('No hay cuentas vencidas para procesar.');
            return;
        }

        foreach ($cuentasVencidas as $cuenta) {
            DB::transaction(function () use ($cuenta, $porcentajeInteres) {
                $montoInteres = $cuenta->saldo_deudor * $porcentajeInteres;

                MovimientoCuentaCorriente::create([
                    'cuenta_corriente_id' => $cuenta->id,
                    'tipo_movimiento'     => 'Interés por Mora',
                    'monto'               => $montoInteres,
                    'descripcion'         => 'Recargo automático del 5% por falta de pago (30 días)',
                    'fecha_movimiento'    => now(),
                ]);

                $cuenta->increment('saldo_deudor', $montoInteres);
                $cuenta->touch(); 
            });
        }

        $this->info('Intereses aplicados correctamente.');
    }
}