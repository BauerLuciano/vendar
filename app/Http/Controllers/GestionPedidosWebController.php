<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PedidoWeb;
use Inertia\Inertia;

class GestionPedidosWebController extends Controller
{
    // Mostrar el panel con todos los pedidos
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

    // Cambiar estado de preparación (Nuevo -> En Preparación -> Enviado/Entregado)
    public function updateEstado(Request $request, $id)
    {
        $request->validate(['estado_pedido' => 'required|string']);
        
        $pedido = PedidoWeb::findOrFail($id);
        $pedido->update(['estado_pedido' => $request->estado_pedido]);

        return redirect()->back();
    }

    // Cambiar estado del pago (Pendiente -> Pagado)
    public function updatePago(Request $request, $id)
    {
        $request->validate(['estado_pago' => 'required|string']);
        
        $pedido = PedidoWeb::findOrFail($id);
        $pedido->update(['estado_pago' => $request->estado_pago]);

        return redirect()->back();
    }
}