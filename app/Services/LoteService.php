<?php

namespace App\Services;

use App\Models\Lote;
use Illuminate\Support\Facades\DB;

class LoteService
{
    /**
     * Busca un lote existente (mismo producto + sucursal + vencimiento) y lo bloquea.
     * Si existe, incrementa stock_inicial y stock_actual.
     * Si no existe, crea uno nuevo.
     *
     * @return Lote El lote creado o actualizado
     */
    public function upsert(
        int $productoId,
        int $sucursalId,
        string $fechaVencimiento,
        float $cantidad
    ): Lote {
        $loteExistente = Lote::where('producto_id', $productoId)
            ->where('sucursal_id', $sucursalId)
            ->where('fecha_vencimiento', $fechaVencimiento)
            ->lockForUpdate()
            ->first();

        if ($loteExistente) {
            $loteExistente->update([
                'stock_inicial' => $loteExistente->stock_inicial + $cantidad,
                'stock_actual' => $loteExistente->stock_actual + $cantidad,
            ]);
            return $loteExistente;
        }

        return Lote::create([
            'producto_id' => $productoId,
            'sucursal_id' => $sucursalId,
            'fecha_vencimiento' => $fechaVencimiento,
            'stock_inicial' => $cantidad,
            'stock_actual' => $cantidad,
            'estado_liquidacion' => false,
        ]);
    }
}
