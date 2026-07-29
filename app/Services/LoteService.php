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

    /**
     * Consume stock de lotes en orden FIFO (vencimiento más antiguo primero).
     * Retorna array de ['lote_id' => int, 'cantidad' => float] para tracking.
     *
     * @return array<int, array{lote_id: int, cantidad: float}>
     */
    public function consumirFifo(
        int $productoId,
        int $sucursalId,
        float $cantidad
    ): array {
        $lotes = Lote::where('producto_id', $productoId)
            ->where('sucursal_id', $sucursalId)
            ->where('stock_actual', '>', 0)
            ->orderBy('fecha_vencimiento', 'asc')
            ->lockForUpdate()
            ->get();

        $pendiente = $consumidos = [];

        foreach ($lotes as $lote) {
            if ($cantidad <= 0) break;

            $disponible = (float) $lote->stock_actual;
            $aRestar = min($cantidad, $disponible);

            $lote->decrement('stock_actual', $aRestar);
            $cantidad -= $aRestar;

            $consumidos[] = [
                'lote_id' => $lote->id,
                'cantidad' => $aRestar,
            ];
        }

        return $consumidos;
    }

    /**
     * Restaura stock de lotes según tracking previo.
     * Cada registro debe contener 'lote_id' y 'cantidad'.
     *
     * @param array<int, array{lote_id: int, cantidad: float}> $consumidos
     */
    public function restaurarLotes(array $consumidos): void
    {
        foreach ($consumidos as $registro) {
            DB::table('lotes')
                ->where('id', $registro['lote_id'])
                ->increment('stock_actual', $registro['cantidad']);
        }
    }
}
