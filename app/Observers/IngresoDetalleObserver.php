<?php

namespace App\Observers;

use App\Models\IngresoDetalle;

class IngresoDetalleObserver
{
    public function created(IngresoDetalle $detalle): void
    {
        $ingreso = $detalle->ingreso;
        $branch = $ingreso->branch;
        $producto_id = $detalle->producto_id;

        $pivot = $branch->productos()->where('producto_id', $producto_id)->first();

        if ($pivot) {
            $branch->productos()->updateExistingPivot($producto_id, [
                'cantidad_fisica' => $pivot->pivot->cantidad_fisica + $detalle->cantidad_recibida
            ]);
        } else {
            $branch->productos()->attach($producto_id, [
                'cantidad_fisica' => $detalle->cantidad_recibida,
                'cantidad_reservada' => 0
            ]);
        }

        if (!empty($detalle->precio_costo_actualizado)) {
            $detalle->producto->update([
                'precio_costo' => $detalle->precio_costo_actualizado
            ]);
        }
    }
}