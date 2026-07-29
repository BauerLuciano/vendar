<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PedidoWeb;
use App\Services\SucursalScopeService;
use App\Services\LoteService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GestionPedidosWebController extends Controller
{
    public function __construct(
        private readonly SucursalScopeService $scope,
        private readonly LoteService $lotes
    ) {}

    /**
     * Valida que un pedido pertenezca a una sucursal permitida por el usuario.
     */
    private function autorizarPedido(PedidoWeb $pedido): void
    {
        if ($this->scope->puedeAccederSucursal((int) $pedido->sucursal_id)) {
            return;
        }

        abort(403, 'No tenés acceso a este pedido.');
    }

    public function index()
    {
        $query = PedidoWeb::with(['items.producto', 'sucursal']);
        $this->scope->aplicarFiltroSucursal($query);

        $pedidos = $query->orderBy('created_at', 'desc')->get();

        return Inertia::render('Pedidos/Index', [
            'pedidos' => $pedidos,
            'sucursal' => auth()->user()->branch,
        ]);
    }

    public function updateEstado(Request $request, $id)
    {
        $request->validate([
            'estado_pedido' => 'required|in:nuevo,preparando,en_camino,entregado,cancelado',
        ]);

        $pedido = PedidoWeb::lockForUpdate()->with('items')->findOrFail($id);
        $this->autorizarPedido($pedido);

        $esJefe = $this->scope->esJefe();
        $estadoActual = $pedido->estado_pedido;
        $nuevoEstado = $request->estado_pedido;

        if ($estadoActual === $nuevoEstado) {
            return redirect()->back();
        }

        $esForward = in_array($nuevoEstado, $pedido->nextStates());
        $esCancel = $nuevoEstado === 'cancelado';

        if (!$esForward && !$esCancel && !$esJefe) {
            return redirect()->back()->with('error', 'Transición de estado no permitida.');
        }

        return DB::transaction(function () use ($pedido, $nuevoEstado, $estadoActual, $esCancel) {
            if ($nuevoEstado === 'entregado') {
                $lockedStock = [];
                foreach ($pedido->items as $item) {
                    $ps = DB::table('producto_sucursal')
                        ->where('sucursal_id', $pedido->sucursal_id)
                        ->where('producto_id', $item->producto_id)
                        ->lockForUpdate()
                        ->first();
                    $lockedStock[$item->producto_id] = $ps;
                }

                foreach ($pedido->items as $item) {
                    $ps = $lockedStock[$item->producto_id];
                    if (!$ps) {
                        throw new \Exception("El producto ID {$item->producto_id} no existe en la sucursal.");
                    }
                    if ((float) $ps->cantidad_reservada < (float) $item->cantidad) {
                        throw new \Exception("Stock reservado insuficiente para el producto ID {$item->producto_id}.");
                    }
                    if ((float) $ps->cantidad_fisica < (float) $item->cantidad) {
                        throw new \Exception("Stock físico insuficiente para el producto ID {$item->producto_id}.");
                    }

                    $reservadaAnterior = (float) $ps->cantidad_reservada;
                    $fisicaAnterior    = (float) $ps->cantidad_fisica;

                    DB::table('producto_sucursal')
                        ->where('sucursal_id', $pedido->sucursal_id)
                        ->where('producto_id', $item->producto_id)
                        ->update([
                            'cantidad_reservada' => DB::raw("cantidad_reservada - " . (float) $item->cantidad),
                            'cantidad_fisica'    => DB::raw("cantidad_fisica - " . (float) $item->cantidad),
                            'updated_at'         => now(),
                        ]);

                    $psDespues = DB::table('producto_sucursal')
                        ->where('sucursal_id', $pedido->sucursal_id)
                        ->where('producto_id', $item->producto_id)
                        ->first();

                    $consumidos = $this->lotes->consumirFifo(
                        (int) $item->producto_id,
                        (int) $pedido->sucursal_id,
                        (float) $item->cantidad
                    );

                    foreach ($consumidos as $consumo) {
                        DB::table('pedido_web_items_lotes')->insert([
                            'pedido_web_item_id' => $item->id,
                            'lote_id'            => $consumo['lote_id'],
                            'cantidad'           => $consumo['cantidad'],
                            'created_at'         => now(),
                            'updated_at'         => now(),
                        ]);
                    }

                    DB::table('movimientos_stock')->insert([
                        'producto_id'         => $item->producto_id,
                        'sucursal_id'         => $pedido->sucursal_id,
                        'user_id'             => auth()->id(),
                        'tipo_movimiento'     => 'Entrega Pedido Web',
                        'cantidad_anterior'   => $fisicaAnterior,
                        'cantidad_movimiento' => -(float) $item->cantidad,
                        'cantidad_actual'     => (float) $psDespues->cantidad_fisica,
                        'motivo'              => "Entrega del pedido web #{$pedido->id}",
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);
                }
            } elseif ($esCancel) {
                if ($estadoActual === 'entregado') {
                    foreach ($pedido->items as $item) {
                        $ps = DB::table('producto_sucursal')
                            ->where('sucursal_id', $pedido->sucursal_id)
                            ->where('producto_id', $item->producto_id)
                            ->lockForUpdate()
                            ->first();

                        $fisicaAnterior = $ps ? (float) $ps->cantidad_fisica : 0;

                        DB::table('producto_sucursal')
                            ->where('sucursal_id', $pedido->sucursal_id)
                            ->where('producto_id', $item->producto_id)
                            ->update([
                                'cantidad_fisica' => DB::raw("cantidad_fisica + " . (float) $item->cantidad),
                                'updated_at'     => now(),
                            ]);

                        $consumidos = DB::table('pedido_web_items_lotes')
                            ->where('pedido_web_item_id', $item->id)
                            ->get()
                            ->map(fn ($row) => ['lote_id' => (int) $row->lote_id, 'cantidad' => (float) $row->cantidad])
                            ->toArray();

                        if (!empty($consumidos)) {
                            $this->lotes->restaurarLotes($consumidos);
                            DB::table('pedido_web_items_lotes')
                                ->where('pedido_web_item_id', $item->id)
                                ->delete();
                        }

                        $psDespues = DB::table('producto_sucursal')
                            ->where('sucursal_id', $pedido->sucursal_id)
                            ->where('producto_id', $item->producto_id)
                            ->first();

                        DB::table('movimientos_stock')->insert([
                            'producto_id'         => $item->producto_id,
                            'sucursal_id'         => $pedido->sucursal_id,
                            'user_id'             => auth()->id(),
                            'tipo_movimiento'     => 'Devolución Pedido Web',
                            'cantidad_anterior'   => $fisicaAnterior,
                            'cantidad_movimiento' => (float) $item->cantidad,
                            'cantidad_actual'     => (float) $psDespues->cantidad_fisica,
                            'motivo'              => "Devolución por cancelación post-entrega del pedido web #{$pedido->id}",
                            'created_at'          => now(),
                            'updated_at'          => now(),
                        ]);
                    }
                } else {
                    foreach ($pedido->items as $item) {
                        $ps = DB::table('producto_sucursal')
                            ->where('sucursal_id', $pedido->sucursal_id)
                            ->where('producto_id', $item->producto_id)
                            ->lockForUpdate()
                            ->first();
                        $reservadaAnterior = $ps ? (float) $ps->cantidad_reservada : 0;
                        if ($ps && $ps->cantidad_reservada >= $item->cantidad) {
                            DB::table('producto_sucursal')
                                ->where('sucursal_id', $pedido->sucursal_id)
                                ->where('producto_id', $item->producto_id)
                                ->decrement('cantidad_reservada', $item->cantidad);
                        } elseif ($ps) {
                            DB::table('producto_sucursal')
                                ->where('sucursal_id', $pedido->sucursal_id)
                                ->where('producto_id', $item->producto_id)
                                ->update(['cantidad_reservada' => 0, 'updated_at' => now()]);
                        }

                        DB::table('movimientos_stock')->insert([
                            'producto_id'         => $item->producto_id,
                            'sucursal_id'         => $pedido->sucursal_id,
                            'user_id'             => auth()->id(),
                            'tipo_movimiento'     => 'Liberación Reserva Web',
                            'cantidad_anterior'   => $reservadaAnterior,
                            'cantidad_movimiento' => (float) $item->cantidad,
                            'cantidad_actual'     => $ps ? (float) $ps->cantidad_fisica : 0,
                            'motivo'              => "Cancelación pre-entrega del pedido web #{$pedido->id}",
                            'created_at'          => now(),
                            'updated_at'          => now(),
                        ]);
                    }
                }
            }

            $pedido->update(['estado_pedido' => $nuevoEstado]);
            return redirect()->back();
        });
    }

    public function updatePago(Request $request, $id)
    {
        $request->validate([
            'estado_pago' => 'required|in:pendiente,pagado,reembolsado',
        ]);

        return DB::transaction(function () use ($request, $id) {
            $pedido = PedidoWeb::lockForUpdate()->with('items')->findOrFail($id);
            $this->autorizarPedido($pedido);

            $estadoPagoAnterior = $pedido->estado_pago;
            $pedido->estado_pago = $request->estado_pago;
            $pedido->save();

            if ($request->estado_pago === 'reembolsado' && $estadoPagoAnterior === 'pagado') {
                $this->liberarStockReservado($pedido);
            }

            return redirect()->back();
        });
    }

    private function liberarStockReservado(PedidoWeb $pedido): void
    {
        if (in_array($pedido->estado_pedido, ['entregado', 'cancelado'])) {
            return;
        }

        foreach ($pedido->items as $item) {
            $ps = DB::table('producto_sucursal')
                ->where('sucursal_id', $pedido->sucursal_id)
                ->where('producto_id', $item->producto_id)
                ->lockForUpdate()
                ->first();

            $reservadaAnterior = $ps ? (float) $ps->cantidad_reservada : 0;

            if ($ps && $ps->cantidad_reservada >= $item->cantidad) {
                DB::table('producto_sucursal')
                    ->where('sucursal_id', $pedido->sucursal_id)
                    ->where('producto_id', $item->producto_id)
                    ->decrement('cantidad_reservada', $item->cantidad);
            } elseif ($ps) {
                DB::table('producto_sucursal')
                    ->where('sucursal_id', $pedido->sucursal_id)
                    ->where('producto_id', $item->producto_id)
                    ->update(['cantidad_reservada' => 0]);
            }

            DB::table('movimientos_stock')->insert([
                'producto_id'         => $item->producto_id,
                'sucursal_id'         => $pedido->sucursal_id,
                'user_id'             => auth()->id(),
                'tipo_movimiento'     => 'Liberación Reserva Web',
                'cantidad_anterior'   => $reservadaAnterior,
                'cantidad_movimiento' => (float) $item->cantidad,
                'cantidad_actual'     => $ps ? (float) $ps->cantidad_fisica : 0,
                'motivo'              => "Reembolso del pedido web #{$pedido->id}",
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }
    }
}
