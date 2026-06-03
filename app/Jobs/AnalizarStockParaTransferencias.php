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
                $necesitadas = DB::table('branch_producto')
                    ->join('sucursales', 'branch_producto.branch_id', '=', 'sucursales.id')
                    ->where('branch_producto.producto_id', $producto->id)
                    ->where('branch_producto.cantidad_fisica', '<', $producto->stock_minimo)
                    ->select('branch_producto.*')
                    ->lockForUpdate()
                    ->get();

                $conExceso = DB::table('branch_producto')
                    ->join('sucursales', 'branch_producto.branch_id', '=', 'sucursales.id')
                    ->where('branch_producto.producto_id', $producto->id)
                    ->where('branch_producto.cantidad_fisica', '>', $producto->stock_minimo)
                    ->orderBy('branch_producto.cantidad_fisica', 'desc')
                    ->select('branch_producto.*')
                    ->lockForUpdate()
                    ->get();

                if ($necesitadas->count() > 0 && $conExceso->count() > 0) {
                    foreach ($necesitadas as $necesitada) {
                        $cantidadFaltante = $producto->stock_minimo - $necesitada->cantidad_fisica;

                        $donante = $conExceso->first();

                        $excesoDisponible = $donante->cantidad_fisica - $producto->stock_minimo;

                        if ($excesoDisponible > 0) {
                            $cantidadSugerida = min($cantidadFaltante, $excesoDisponible);

                            $yaExiste = TransferenciaSugerida::where('origen_id', $donante->branch_id)
                                ->where('destino_id', $necesitada->branch_id)
                                ->where('producto_id', $producto->id)
                                ->where('estado', 'pendiente')
                                ->lockForUpdate()
                                ->exists();

                            if (!$yaExiste && $cantidadSugerida > 0) {
                                TransferenciaSugerida::create([
                                    'origen_id' => $donante->branch_id,
                                    'destino_id' => $necesitada->branch_id,
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