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
        // 1. EL MOTOR PREVENTIVO: Detectar bajo stock y armar sugerencias
        // =================================================================
        
        // Unimos producto_sucursal con productos para saber el stock_minimo de cada uno
        $necesitados = DB::table('producto_sucursal')
            ->join('productos', 'productos.id', '=', 'producto_sucursal.producto_id')
            ->select('producto_sucursal.*', 'productos.stock_minimo')
            ->whereRaw('producto_sucursal.cantidad_fisica < productos.stock_minimo')
            ->get();

        foreach ($necesitados as $necesitado) {
            // ¿Cuánto falta para rellenar hasta el stock mínimo?
            // Ej: Mínimo es 10, tengo 3 -> Faltan 7.
            $cantidadFaltante = $necesitado->stock_minimo - $necesitado->cantidad_fisica;

            if ($cantidadFaltante > 0) {
                $salvador = DB::table('producto_sucursal')
                    ->join('productos', 'productos.id', '=', 'producto_sucursal.producto_id')
                    ->where('producto_sucursal.producto_id', $necesitado->producto_id)
                    ->where('producto_sucursal.sucursal_id', '!=', $necesitado->sucursal_id)
                    ->whereRaw('producto_sucursal.cantidad_fisica >= (CAST(? AS NUMERIC) + CAST(productos.stock_minimo AS NUMERIC))', [$cantidadFaltante])
                    ->select('producto_sucursal.sucursal_id')
                    ->first();

                if ($salvador) {
                    // Creamos la sugerencia (si no existe ya pendiente)
                    TransferenciaSugerida::firstOrCreate([
                        'origen_id' => $salvador->sucursal_id,
                        'destino_id' => $necesitado->sucursal_id,
                        'producto_id' => $necesitado->producto_id,
                        'estado' => 'pendiente'
                    ], [
                        'cantidad' => $cantidadFaltante
                    ]);
                }
            }
        }

        // =================================================================
        // 2. LA LECTURA: Mostrar lo que armó el motor + El historial
        // =================================================================
        
        // Traemos las sugerencias pendientes para la primera pestaña
        $sugerencias = TransferenciaSugerida::with(['origen', 'destino', 'producto'])
            ->where('estado', 'pendiente')
            ->get();

        // NUEVO: Traemos las aprobadas para la segunda pestaña (Historial)
        // Ordenamos por updated_at desc para ver las últimas que aprobaste arriba de todo
        $historial = TransferenciaSugerida::with(['origen', 'destino', 'producto'])
            ->where('estado', 'aprobada')
            ->orderBy('updated_at', 'desc')
            ->take(50) // Limitamos a 50 para que la carga sea rápida
            ->get();

        return inertia('Transferencias/Index', [
            'sugerencias' => $sugerencias,
            'historial'   => $historial // Mandamos el historial al Vue
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