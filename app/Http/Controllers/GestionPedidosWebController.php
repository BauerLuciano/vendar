<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PedidoWeb;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GestionPedidosWebController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $esJefe = $user->hasRole(['SuperAdmin', 'Administrador Global']);
        $sucursalIds = $user->branch?->comercio_id
            ? \App\Models\Sucursal::where('comercio_id', $user->branch->comercio_id)->pluck('id')
            : collect();

        $query = PedidoWeb::with(['items.producto', 'sucursal']);

        if (!$esJefe) {
            $sucursalId = session('sucursal_activa_id', $user->branch_id);
            if ($sucursalId) {
                $query->where('sucursal_id', $sucursalId);
            }
        } elseif ($sucursalIds->isNotEmpty()) {
            $query->whereIn('sucursal_id', $sucursalIds);
        }

        $pedidos = $query->orderBy('created_at', 'desc')->get();

        return Inertia::render('Pedidos/Index', [
            'pedidos' => $pedidos,
            'sucursal' => $user->branch,
        ]);
    }

    public function updateEstado(Request $request, $id)
    {
        $request->validate(['estado_pedido' => 'required|in:nuevo,preparando,en_camino,entregado,cancelado']);

        $user = auth()->user();
        $esJefe = $user->hasRole(['SuperAdmin', 'Administrador Global']);

        return DB::transaction(function () use ($request, $id, $esJefe, $user) {
            $sucursalIds = $user->branch?->comercio_id
                ? \App\Models\Sucursal::where('comercio_id', $user->branch->comercio_id)->pluck('id')
                : collect();

            $pedido = PedidoWeb::lockForUpdate()->with('items')
                ->when($sucursalIds->isNotEmpty(), fn ($q) => $q->whereIn('sucursal_id', $sucursalIds))
                ->findOrFail($id);
            $estadoActual = $pedido->estado_pedido;
            $nuevoEstado = $request->estado_pedido;

            if ($estadoActual === $nuevoEstado) return redirect()->back();

            $esForward = in_array($nuevoEstado, $pedido->nextStates());
            $esCancel = $nuevoEstado === 'cancelado';

            if (!$esForward && !$esCancel && !$esJefe) {
                return redirect()->back()->with('error', 'Transición de estado no permitida.');
            }

            if ($nuevoEstado === 'entregado') {
                foreach ($pedido->items as $item) {
                    $ps = DB::table('producto_sucursal')
                        ->where('sucursal_id', $pedido->sucursal_id)
                        ->where('producto_id', $item->producto_id)
                        ->lockForUpdate()
                        ->first();
                    $cantidadAnterior = $ps ? $ps->cantidad_fisica : 0;

                    DB::table('producto_sucursal')
                        ->where('sucursal_id', $pedido->sucursal_id)
                        ->where('producto_id', $item->producto_id)
                        ->decrement('cantidad_reservada', $item->cantidad);
                    DB::table('producto_sucursal')
                        ->where('sucursal_id', $pedido->sucursal_id)
                        ->where('producto_id', $item->producto_id)
                        ->decrement('cantidad_fisica', $item->cantidad);

                    DB::table('movimientos_stock')->insert([
                        'producto_id'       => $item->producto_id,
                        'sucursal_id'       => $pedido->sucursal_id,
                        'user_id'           => auth()->id(),
                        'tipo_movimiento'   => 'Pedido Web Entregado',
                        'cantidad_anterior' => $cantidadAnterior,
                        'cantidad_movimiento' => -$item->cantidad,
                        'cantidad_actual'   => $cantidadAnterior - $item->cantidad,
                        'motivo'            => "Pedido web #{$pedido->id} entregado",
                        'created_at'        => now(),
                        'updated_at'        => now(),
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
                        $cantidadAnterior = $ps ? $ps->cantidad_fisica : 0;

                        DB::table('producto_sucursal')
                            ->where('sucursal_id', $pedido->sucursal_id)
                            ->where('producto_id', $item->producto_id)
                            ->increment('cantidad_fisica', $item->cantidad);

                        DB::table('movimientos_stock')->insert([
                            'producto_id'       => $item->producto_id,
                            'sucursal_id'       => $pedido->sucursal_id,
                            'user_id'           => auth()->id(),
                            'tipo_movimiento'   => 'Cancelación Pedido Web',
                            'cantidad_anterior' => $cantidadAnterior,
                            'cantidad_movimiento' => $item->cantidad,
                            'cantidad_actual'   => $cantidadAnterior + $item->cantidad,
                            'motivo'            => "Pedido web #{$pedido->id} cancelado después de entrega",
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ]);
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
        $request->validate(['estado_pago' => 'required|in:pendiente,pagado,reembolsado']);

        $user = auth()->user();
        $sucursalIds = $user->branch?->comercio_id
            ? \App\Models\Sucursal::where('comercio_id', $user->branch->comercio_id)->pluck('id')
            : collect();

        return DB::transaction(function () use ($request, $id, $sucursalIds) {
            $pedido = PedidoWeb::lockForUpdate()->with('items')
                ->when($sucursalIds->isNotEmpty(), fn ($q) => $q->whereIn('sucursal_id', $sucursalIds))
                ->findOrFail($id);

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

            DB::table('movimientos_stock')->insert([
                'producto_id'       => $item->producto_id,
                'sucursal_id'       => $pedido->sucursal_id,
                'user_id'           => auth()->id(),
                'tipo_movimiento'   => 'Liberación Reserva',
                'cantidad_anterior' => $ps ? $ps->cantidad_fisica : 0,
                'cantidad_movimiento' => 0,
                'cantidad_actual'   => $ps ? $ps->cantidad_fisica : 0,
                'motivo'            => "Reembolso - Pedido web #{$pedido->id}",
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }
    }
}