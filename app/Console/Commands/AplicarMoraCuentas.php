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
        // 🛠️ 1. OBTENEMOS LOS PARÁMETROS DESDE LA BASE DE DATOS
        // Usamos valores por defecto (15 y 5) por si no existen las llaves aún
        $config = Configuracion::pluck('valor', 'clave');
        
        $diasDeGracia = (int) ($config['mora_dias_gracia'] ?? 15);
        $tasaInteres = (float) ($config['mora_tasa_interes'] ?? 5);

        $this->info("Iniciando proceso de mora (Gracia: {$diasDeGracia} días, Tasa: {$tasaInteres}%)...");

        // 2. BUSCAMOS DEUDORES ATRASADOS
        $fechaLimite = Carbon::now()->subDays($diasDeGracia);

        $cuentasEnMora = CuentaCorriente::where('estado', true)
            ->where('saldo_deudor', '>', 0)
            ->whereDate('fecha_ultimo_movimiento', '<=', $fechaLimite)
            ->get();

        if ($cuentasEnMora->isEmpty()) {
            $this->info("No hay cuentas que cumplan los criterios de mora hoy.");
            return;
        }

        $cuentasAfectadas = 0;

        DB::beginTransaction();
        try {
            foreach ($cuentasEnMora as $cuenta) {
                // Calculamos el interés
                $montoInteres = $cuenta->saldo_deudor * ($tasaInteres / 100);

                if ($montoInteres > 0) {
                    // Registramos el movimiento
                    MovimientoCuentaCorriente::create([
                        'cuenta_corriente_id' => $cuenta->id,
                        'monto' => $montoInteres,
                        'tipo' => 'Cargo',
                        'descripcion' => "Interés automático por mora ({$tasaInteres}%). Configuración: {$diasDeGracia} días de gracia.",
                    ]);

                    // Actualizamos saldo y fecha
                    $cuenta->saldo_deudor += $montoInteres;
                    $cuenta->fecha_ultimo_movimiento = now(); 
                    $cuenta->save();

                    $cuentasAfectadas++;
                }
            }
            DB::commit();
            $this->info("¡Éxito! Se aplicó mora a {$cuentasAfectadas} cuentas.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error crítico: " . $e->getMessage());
        }
    }
}