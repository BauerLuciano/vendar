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
        // Buscamos los pedidos con sus ítems y los ordenamos por fecha (los más nuevos primero)
        $pedidos = PedidoWeb::with(['items.producto'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Pedidos/Index', [
            'pedidos' => $pedidos
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