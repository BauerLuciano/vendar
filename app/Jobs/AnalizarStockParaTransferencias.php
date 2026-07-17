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
        $productos = Producto::where('estado', true)->get();

        foreach ($productos as $producto) {
            DB::transaction(function () use ($producto) {
                $necesitadas = DB::table('producto_sucursal')
                    ->join('sucursales', 'producto_sucursal.sucursal_id', '=', 'sucursales.id')
                    ->where('producto_sucursal.producto_id', $producto->id)
                    ->where('producto_sucursal.cantidad_fisica', '<', $producto->stock_minimo)
                    ->select('producto_sucursal.*')
                    ->lockForUpdate()
                    ->get();

                $conExceso = DB::table('producto_sucursal')
                    ->join('sucursales', 'producto_sucursal.sucursal_id', '=', 'sucursales.id')
                    ->where('producto_sucursal.producto_id', $producto->id)
                    ->where('producto_sucursal.cantidad_fisica', '>', $producto->stock_minimo)
                    ->orderBy('producto_sucursal.cantidad_fisica', 'desc')
                    ->select('producto_sucursal.*')
                    ->lockForUpdate()
                    ->get();

                if ($necesitadas->count() > 0 && $conExceso->count() > 0) {
                    foreach ($necesitadas as $necesitada) {
                        $cantidadFaltante = $producto->stock_minimo - $necesitada->cantidad_fisica;

                        $donante = $conExceso->first();

                        $excesoDisponible = $donante->cantidad_fisica - $producto->stock_minimo;

                        if ($excesoDisponible > 0) {
                            $cantidadSugerida = min($cantidadFaltante, $excesoDisponible);

                            $yaExiste = TransferenciaSugerida::where('origen_id', $donante->sucursal_id)
                                ->where('destino_id', $necesitada->sucursal_id)
                                ->where('producto_id', $producto->id)
                                ->where('estado', 'pendiente')
                                ->lockForUpdate()
                                ->exists();

                            if (!$yaExiste && $cantidadSugerida > 0) {
                                TransferenciaSugerida::create([
                                    'origen_id' => $donante->sucursal_id,
                                    'destino_id' => $necesitada->sucursal_id,
                                    'producto_id' => $producto->id,
                                    'cantidad' => $cantidadSugerida,
                                    'estado' => 'pendiente',
                                ]);
                            }
                        }
                    }
                }
            });
        }
    }
}
