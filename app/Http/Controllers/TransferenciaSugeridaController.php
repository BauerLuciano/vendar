<?php

namespace App\Http\Controllers;

use App\Models\TransferenciaSugerida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferenciaSugeridaController extends Controller
{
    /**
     * Devuelve la lista para que Vue la muestre en la tabla.
     */
    public function index()
    {
        // Traemos las sugerencias con los nombres de las sucursales y productos
        $sugerencias = TransferenciaSugerida::with(['origen', 'destino', 'producto'])
            ->where('estado', 'pendiente')
            ->get();

        return inertia('Transferencias/Index', [
            'sugerencias' => $sugerencias
        ]);
    }

    /**
     * Aprueba la transferencia y mueve el stock.
     */
    public function aprobar(TransferenciaSugerida $transferencia)
    {
        // Verificamos que siga pendiente
        if ($transferencia->estado !== 'pendiente') {
            return redirect()->back()->with('error', 'Esta transferencia ya fue procesada.');
        }

        DB::transaction(function () use ($transferencia) {
            
            // 1. Descontamos stock del origen
            DB::table('branch_producto')
                ->where('branch_id', $transferencia->origen_id)
                ->where('producto_id', $transferencia->producto_id)
                ->decrement('cantidad_fisica', $transferencia->cantidad);

            // 2. Sumamos stock al destino (usamos updateOrInsert por si la sucursal nunca tuvo este producto)
            $existeEnDestino = DB::table('branch_producto')
                ->where('branch_id', $transferencia->destino_id)
                ->where('producto_id', $transferencia->producto_id)
                ->exists();

            if ($existeEnDestino) {
                DB::table('branch_producto')
                    ->where('branch_id', $transferencia->destino_id)
                    ->where('producto_id', $transferencia->producto_id)
                    ->increment('cantidad_fisica', $transferencia->cantidad);
            } else {
                DB::table('branch_producto')->insert([
                    'branch_id' => $transferencia->destino_id,
                    'producto_id' => $transferencia->producto_id,
                    'cantidad_fisica' => $transferencia->cantidad,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 3. Marcamos la sugerencia como aprobada
            $transferencia->update(['estado' => 'aprobada']);
        });

        return redirect()->back()->with('success', 'Transferencia aprobada y stock actualizado.');
    }
}