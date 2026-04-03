<?php

namespace App\Jobs;

use App\Models\Producto;
use App\Models\TransferenciaSugerida;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AnalizarStockParaTransferencias implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        // 1. Traemos todos los productos activos
        $productos = Producto::where('estado', true)->get();

        foreach ($productos as $producto) {
            
            // 2. Buscamos sucursales que NECESITAN (Tienen menos cantidad física que el stock mínimo)
            $necesitadas = DB::table('branch_producto')
                ->where('producto_id', $producto->id)
                ->where('cantidad_fisica', '<', $producto->stock_minimo)
                ->get();

            // 3. Buscamos sucursales que tienen EXCESO (Tienen más cantidad que el stock mínimo)
            $conExceso = DB::table('branch_producto')
                ->where('producto_id', $producto->id)
                ->where('cantidad_fisica', '>', $producto->stock_minimo)
                ->orderBy('cantidad_fisica', 'desc') // Ordenamos para que "done" el que más tiene
                ->get();

            // Si hay sucursales que necesitan y otras que tienen de sobra, hacemos la magia
            if ($necesitadas->count() > 0 && $conExceso->count() > 0) {
                
                foreach ($necesitadas as $necesitada) {
                    // ¿Cuánto le falta para llegar al stock mínimo?
                    $cantidadFaltante = $producto->stock_minimo - $necesitada->cantidad_fisica;
                    
                    // Agarramos al que más tiene de la lista de exceso
                    $donante = $conExceso->first();
                    
                    // Calculamos cuánto realmente puede donar sin quedarse él mismo por debajo de su mínimo
                    $excesoDisponible = $donante->cantidad_fisica - $producto->stock_minimo;

                    if ($excesoDisponible > 0) {
                        // Transferimos lo que falta, o todo lo que sobra si el donante no tiene tanto
                        $cantidadSugerida = min($cantidadFaltante, $excesoDisponible);

                        // Verificamos que no hayamos creado ya esta misma sugerencia antes (para no duplicar)
                        $yaExiste = TransferenciaSugerida::where('origen_id', $donante->branch_id)
                            ->where('destino_id', $necesitada->branch_id)
                            ->where('producto_id', $producto->id)
                            ->where('estado', 'pendiente')
                            ->exists();

                        if (!$yaExiste && $cantidadSugerida > 0) {
                            TransferenciaSugerida::create([
                                'origen_id' => $donante->branch_id,
                                'destino_id' => $necesitada->branch_id,
                                'producto_id' => $producto->id,
                                'cantidad' => $cantidadSugerida,
                                'estado' => 'pendiente'
                            ]);
                        }
                    }
                }
            }
        }
    }
}