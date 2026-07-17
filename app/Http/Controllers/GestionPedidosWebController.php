<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PedidoWeb;
use App\Services\SucursalScopeService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GestionPedidosWebController extends Controller
{
    public function __construct(
        private readonly SucursalScopeService $scope
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
                foreach ($pedido->items as $item) {
                    DB::table('producto_sucursal')
                        ->where('sucursal_id', $pedido->sucursal_id)
                        ->where('producto_id', $item->producto_id)
                        ->decrement('cantidad_reservada', $item->cantidad);
                    DB::table('producto_sucursal')
                        ->where('sucursal_id', $pedido->sucursal_id)
                        ->where('producto_id', $item->producto_id)
                        ->decrement('cantidad_fisica', $item->cantidad);
                }
            } elseif ($esCancel) {
                if ($estadoActual === 'entregado') {
                    foreach ($pedido->items as $item) {
                        DB::table('producto_sucursal')
                            ->where('sucursal_id', $pedido->sucursal_id)
                            ->where('producto_id', $item->producto_id)
                            ->increment('cantidad_fisica', $item->cantidad);
                    }
                } else {
                    foreach ($pedido->items as $item) {
                        $ps = DB::table('producto_sucursal')
                            ->where('sucursal_id', $pedido->sucursal_id)
                            ->where('producto_id', $item->producto_id)
                            ->lockForUpdate()
                            ->first();
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
        }
    }
}
