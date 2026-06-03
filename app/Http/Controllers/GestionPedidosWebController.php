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

        if (!$esJefe && $user->branch_id) {
            $query->where('sucursal_id', $user->branch_id);
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

            $forwardMap = [
                'nuevo'      => 'preparando',
                'preparando' => 'en_camino',
                'en_camino'  => 'entregado',
            ];

            $esForward = ($forwardMap[$estadoActual] ?? null) === $nuevoEstado;
            $esCancel = $nuevoEstado === 'cancelado';

            if (!$esForward && !$esCancel && !$esJefe) {
                return redirect()->back()->with('error', 'Transición de estado no permitida.');
            }

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
                        DB::table('producto_sucursal')
                            ->where('sucursal_id', $pedido->sucursal_id)
                            ->where('producto_id', $item->producto_id)
                            ->decrement('cantidad_reservada', $item->cantidad);
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

        $pedido = PedidoWeb::where('id', $id)
            ->when($sucursalIds->isNotEmpty(), fn ($q) => $q->whereIn('sucursal_id', $sucursalIds))
            ->firstOrFail();
        $pedido->estado_pago = $request->estado_pago;
        $pedido->save();

        return redirect()->back();
    }
}