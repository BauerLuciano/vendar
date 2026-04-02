<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CuentaCorriente;
use App\Models\MovimientoCuentaCorriente;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GenerarInteresesMora extends Command
{
    // El nombre del comando en la consola
    protected $signature = 'app:generar-intereses-mora';

    // La descripción que aparece al poner php artisan list
    protected $description = 'Calcula y aplica intereses a las cuentas corrientes con saldos vencidos';

    public function handle()
    {
        // 1. Parámetros (Más adelante los sacaremos de la tabla Ajustes)
        $diasGracia = 30;
        $porcentajeInteres = 0.05; // 5% de recargo

        // Calculamos la fecha límite (cuentas que no se movieron desde hace 30 días)
        $fechaLimite = Carbon::now()->subDays($diasGracia);

        $this->info("Buscando cuentas con saldo deudor inactivas desde antes del " . $fechaLimite->format('d/m/Y') . "...");

        // 2. Buscar cuentas que deben plata y están "congeladas" en el tiempo
        $cuentasVencidas = CuentaCorriente::where('saldo_deudor', '>', 0)
            ->where('updated_at', '<', $fechaLimite)
            ->get();

        if ($cuentasVencidas->isEmpty()) {
            $this->info('¡Todo al día! No hay cuentas vencidas para aplicar intereses hoy.');
            return;
        }

        $this->info("Procesando {$cuentasVencidas->count()} cuenta(s) vencida(s)...");

        foreach ($cuentasVencidas as $cuenta) {
            DB::transaction(function () use ($cuenta, $porcentajeInteres) {
                // Calculamos el 5% de lo que debe
                $montoInteres = $cuenta->saldo_deudor * $porcentajeInteres;

                // Registramos el movimiento en el historial del cliente
                MovimientoCuentaCorriente::create([
                    'cuenta_corriente_id' => $cuenta->id,
                    'monto' => $montoInteres,
                    'tipo' => 'recargo', // Tu BD lo acepta perfecto
                    'descripcion' => 'Interés automático por mora (5%)',
                ]);

                // Le aumentamos la deuda al cliente
                $cuenta->increment('saldo_deudor', $montoInteres);
                
                // DATAZO: Al hacer 'increment', la fecha 'updated_at' de la cuenta 
                // cambia a HOY. Esto resetea el reloj y evita que mañana 
                // se le vuelva a cobrar otro 5% por error. ¡Magia pura!
            });
        }

        $this->info('Intereses aplicados correctamente.');
    }
}