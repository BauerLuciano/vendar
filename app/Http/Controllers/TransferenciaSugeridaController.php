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
        // =================================================================
        // 1. EL MOTOR: Detectar negativos y armar sugerencias
        // =================================================================
        $negativos = DB::table('producto_sucursal')->where('cantidad_fisica', '<', 0)->get();

        foreach ($negativos as $negativo) {
            $cantidadFaltante = abs($negativo->cantidad_fisica);

            // Buscamos a la sucursal "Salvadora"
            $salvador = DB::table('producto_sucursal')
                ->where('producto_id', $negativo->producto_id)
                ->where('sucursal_id', '!=', $negativo->sucursal_id)
                ->where('cantidad_fisica', '>=', $cantidadFaltante)
                ->first();

            if ($salvador) {
                // Creamos la sugerencia (si no existe ya)
                TransferenciaSugerida::firstOrCreate([
                    'origen_id' => $salvador->sucursal_id,
                    'destino_id' => $negativo->sucursal_id,
                    'producto_id' => $negativo->producto_id,
                    'estado' => 'pendiente'
                ], [
                    'cantidad' => $cantidadFaltante
                ]);
            }
        }

        // =================================================================
        // 2. LA LECTURA: Mostrar lo que armó el motor
        // =================================================================
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
        if ($transferencia->estado !== 'pendiente') {
            return redirect()->back()->with('error', 'Esta transferencia ya fue procesada.');
        }

        DB::transaction(function () use ($transferencia) {
            $userId = auth()->id();

            // --- 1. SUCURSAL ORIGEN (Resta stock) ---
            $stockOrigen = DB::table('producto_sucursal')
                ->where('sucursal_id', $transferencia->origen_id)
                ->where('producto_id', $transferencia->producto_id)
                ->first();

            $cantAntOrigen = $stockOrigen ? $stockOrigen->cantidad_fisica : 0;
            $nuevaCantOrigen = $cantAntOrigen - $transferencia->cantidad;

            DB::table('producto_sucursal')
                ->where('producto_id', $transferencia->producto_id)
                ->where('sucursal_id', $transferencia->origen_id)
                ->update(['cantidad_fisica' => $nuevaCantOrigen]);

            // Auditoría Origen
            DB::table('movimientos_stock')->insert([
                'producto_id' => $transferencia->producto_id,
                'sucursal_id' => $transferencia->origen_id,
                'user_id' => $userId,
                'tipo_movimiento' => 'Transferencia Enviada',
                'cantidad_anterior' => $cantAntOrigen,
                'cantidad_movimiento' => -$transferencia->cantidad,
                'cantidad_actual' => $nuevaCantOrigen,
                'motivo' => "Envío a sucursal destino ID: {$transferencia->destino_id}",
                'created_at' => now(), 'updated_at' => now()
            ]);

            // --- 2. SUCURSAL DESTINO (Suma stock) ---
            $stockDestino = DB::table('producto_sucursal')
                ->where('sucursal_id', $transferencia->destino_id)
                ->where('producto_id', $transferencia->producto_id)
                ->first();

            $cantAntDestino = $stockDestino ? $stockDestino->cantidad_fisica : 0;
            $nuevaCantDestino = $cantAntDestino + $transferencia->cantidad;

            DB::table('producto_sucursal')->updateOrInsert(
                ['producto_id' => $transferencia->producto_id, 'sucursal_id' => $transferencia->destino_id],
                ['cantidad_fisica' => $nuevaCantDestino, 'cantidad_reservada' => 0]
            );

            // Auditoría Destino
            DB::table('movimientos_stock')->insert([
                'producto_id' => $transferencia->producto_id,
                'sucursal_id' => $transferencia->destino_id,
                'user_id' => $userId,
                'tipo_movimiento' => 'Transferencia Recibida',
                'cantidad_anterior' => $cantAntDestino,
                'cantidad_movimiento' => $transferencia->cantidad,
                'cantidad_actual' => $nuevaCantDestino,
                'motivo' => "Recepción desde sucursal origen ID: {$transferencia->origen_id}",
                'created_at' => now(), 'updated_at' => now()
            ]);

            // 3. Finalizar sugerencia
            $transferencia->update(['estado' => 'aprobada']);
        });

        return redirect()->back()->with('success', 'Transferencia procesada correctamente.');
    }
}