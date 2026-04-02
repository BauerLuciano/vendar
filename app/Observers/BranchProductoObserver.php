<?php

namespace App\Observers;

use App\Models\BranchProducto;
use App\Models\MovimientoAuditoria;
use Illuminate\Support\Facades\Auth;

class BranchProductoObserver
{
    public function updated(BranchProducto $pivot): void
    {
        $cambios = $pivot->getChanges();
        unset($cambios['updated_at']);

        if (empty($cambios)) return;

        MovimientoAuditoria::create([
            'usuario_id' => Auth::id(),
            'sucursal_id' => $pivot->branch_id, 
            'accion' => 'ACTUALIZACION_STOCK',
            'tabla' => 'branch_producto',
            'registro_id' => $pivot->producto_id, 
            'detalles' => [
                'antes' => array_intersect_key($pivot->getOriginal(), $cambios),
                'despues' => $cambios,
            ],
            'fecha' => now(),
        ]);
    }
}