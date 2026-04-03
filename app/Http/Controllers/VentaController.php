<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Producto;
use App\Models\Consumidor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class VentaController extends Controller
{
    /**
     * Muestra la pantalla del POS
     */
    public function index()
    {
        // Verificamos que el usuario tenga sucursal para evitar errores de stock
        $branchId = auth()->user()->branch_id;

        return Inertia::render('Pos/Index', [
            'productos' => Producto::where('estado', true)
                ->select('id', 'nombre', 'sku', 'precio_venta', 'imagen') // Incluimos imagen
                ->with(['branch_productos' => function($q) use ($branchId) {
                    $q->where('branch_id', $branchId);
                }])
                ->get(),
            'clientes' => Consumidor::all(),
        ]);
    }

    /**
     * Procesa la venta final
     */
    public function store(Request $request)
    {
        $request->validate([
            'cliente_id' => 'required',
            'items' => 'required|array|min:1',
            'metodo_pago' => 'required|string',
            'total_venta' => 'required|numeric',
        ]);

        return DB::transaction(function () use ($request) {
            // 1. Crear la Cabecera de la Venta
            $venta = Venta::create([
                'user_id' => auth()->id(),
                'consumidor_id' => $request->cliente_id,
                'branch_id' => auth()->user()->branch_id,
                'total' => $request->total_venta,
                'metodo_pago' => $request->metodo_pago,
                'fecha' => now(),
            ]);

            foreach ($request->items as $item) {
                // 2. Crear el Detalle
                VentaDetalle::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $item['id'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_venta'],
                    'subtotal' => $item['cantidad'] * $item['precio_venta'],
                ]);

                // 3. Descontar Stock de la Sucursal Actual
                DB::table('branch_producto')
                    ->where('branch_id', auth()->user()->branch_id)
                    ->where('producto_id', $item['id'])
                    ->decrement('cantidad_fisica', $item['cantidad']);
            }

            return redirect()->route('pos.index')->with('success', 'Venta realizada con éxito.');
        });
    }
}