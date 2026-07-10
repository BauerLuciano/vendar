<?php

namespace App\Jobs;

use App\Models\PedidoWeb;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpirarPedidosPendientes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
    }

    public function handle(): void
    {
        $pedidosExpirados = PedidoWeb::where('estado_pedido', 'nuevo')
            ->where('estado_pago', 'pendiente')
            ->where('created_at', '<', now()->subMinutes(30))
            ->get();

        if ($pedidosExpirados->isEmpty()) {
            return;
        }

        Log::info("ExpirarPedidosPendientes: {$pedidosExpirados->count()} pedidos a expirar");

        foreach ($pedidosExpirados as $pedido) {
            DB::transaction(function () use ($pedido) {
                $items = $pedido->items()->get();

                foreach ($items as $item) {
                    $stockRow = DB::table('producto_sucursal')
                        ->where('sucursal_id', $pedido->sucursal_id)
                        ->where('producto_id', $item->producto_id)
                        ->lockForUpdate()
                        ->first();

                    if ($stockRow && $stockRow->cantidad_reservada >= $item->cantidad) {
                        DB::table('producto_sucursal')
                            ->where('sucursal_id', $pedido->sucursal_id)
                            ->where('producto_id', $item->producto_id)
                            ->decrement('cantidad_reservada', $item->cantidad);
                    } elseif ($stockRow) {
                        DB::table('producto_sucursal')
                            ->where('sucursal_id', $pedido->sucursal_id)
                            ->where('producto_id', $item->producto_id)
                            ->update(['cantidad_reservada' => 0]);

                        Log::warning("ExpirarPedidosPendientes: reserva inconsistente corregida", [
                            'pedido_id' => $pedido->id,
                            'producto_id' => $item->producto_id,
                            'reservada' => $stockRow->cantidad_reservada,
                            'a_liberar' => $item->cantidad,
                        ]);
                    }
                }

                $pedido->update([
                    'estado_pedido' => 'cancelado',
                    'notas' => trim(($pedido->notas ?? '') . "\n[EXPIRADO] Pedido cancelado automáticamente por falta de pago after 30 minutos."),
                ]);

                activity()
                    ->performedOn($pedido)
                    ->causedByAnonymous()
                    ->withProperties([
                        'accion' => 'expiracion_automatica',
                        'items_liberados' => $items->count(),
                    ])
                    ->log('pedido_expirado_por_falta_pago');

                Log::info("ExpirarPedidosPendientes: pedido #{$pedido->id} expirado y stock liberado");
            });
        }
    }
}
