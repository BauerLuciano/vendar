<?php

namespace App\Observers;

use App\Models\Producto;
use App\Models\MovimientoAuditoria;
use Illuminate\Support\Facades\Auth;

class ProductoObserver
{
    public function updated(Producto $producto): void
    {
        if ($producto->wasChanged(['nombre', 'precio_costo', 'precio_venta'])) {
            
            $cambios = $producto->getChanges();
            unset($cambios['updated_at']);

            if (empty($cambios)) return;

            MovimientoAuditoria::create([
                'usuario_id' => Auth::id(),
                'sucursal_id' => Auth::user()?->branch_id, 
                'accion' => 'ACTUALIZACION_PRODUCTO',
                'tabla' => 'productos',
                'registro_id' => $producto->id,
                'detalles' => [
                    'antes' => array_intersect_key($producto->getOriginal(), $cambios),
                    'despues' => $cambios,
                ],
                'fecha' => now(),
            ]);
        }
    }
}