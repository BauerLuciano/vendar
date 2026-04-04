<?php

namespace App\Http\Controllers;

use App\Models\IngresoMercaderia;
use App\Models\IngresoDetalle;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class IngresoMercaderiaController extends Controller
{
    public function index()
    {
        return Inertia::render('Ingresos/Index', [
            // Para la tabla (Historial)
            'ingresos' => IngresoMercaderia::with(['proveedor', 'sucursal', 'detalles.producto','usuario'])
                ->orderBy('fecha_ingreso', 'desc')
                ->orderBy('id', 'desc')
                ->get(),
            // Para el Modal de carga
            'productos' => Producto::where('estado', true)->get(),
            'proveedores' => Proveedor::where('estado', true)->get(),
            'sucursales' => Sucursal::where('estado', true)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sucursal_id' => 'required|exists:sucursales,id',
            'fecha_ingreso' => 'required|date',
            'numero_remito' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.producto_id' => 'required|exists:productos,id',
            'items.*.cantidad' => 'required|integer|min:1',
            'items.*.costo' => 'required|numeric|min:0',
        ]);

        $alertasInflacion = [];

        DB::transaction(function () use ($request, &$alertasInflacion) {
            $ingreso = IngresoMercaderia::create([
                'sucursal_id' => $request->sucursal_id,
                'proveedor_id' => $request->proveedor_id,
                'user_id' => auth()->id(),
                'fecha_ingreso' => $request->fecha_ingreso,
                'numero_remito' => $request->numero_remito,
                'total_costo' => collect($request->items)->sum(fn($i) => $i['cantidad'] * $i['costo']),
            ]);

            foreach ($request->items as $item) {
                // 1. Guardar el detalle del ingreso
                IngresoDetalle::create([
                    'ingreso_mercaderia_id' => $ingreso->id,
                    'producto_id' => $item['producto_id'],
                    'cantidad_recibida' => $item['cantidad'],
                    'costo_unitario' => $item['costo'],
                ]);

                // 2. Revisar si hay inflación
                $producto = Producto::find($item['producto_id']);
                if ($item['costo'] > $producto->precio_costo) {
                    $alertasInflacion[] = [
                        'producto' => $producto->nombre,
                        'costo_viejo' => $producto->precio_costo,
                        'costo_nuevo' => $item['costo'],
                    ];
                    $producto->update(['precio_costo' => $item['costo']]);
                }

                // 3. ACTUALIZACIÓN DE STOCK (EL FIX ESTÁ ACÁ)
                $pivot = $producto->sucursales()->where('sucursal_id', $request->sucursal_id)->first();
                
                if ($pivot) {
                    // El producto ya estaba en la sucursal, le sumamos el stock
                    $nuevaCantidad = $pivot->pivot->cantidad_fisica + $item['cantidad'];
                    $producto->sucursales()->updateExistingPivot($request->sucursal_id, [
                        'cantidad_fisica' => $nuevaCantidad
                    ]);
                } else {
                    // Es la primera vez que entra este producto a esta sucursal
                    $producto->sucursales()->attach($request->sucursal_id, [
                        'cantidad_fisica' => $item['cantidad'],
                        'cantidad_reservada' => 0
                    ]);
                }
            }
        });

        return redirect()->back()->with([
            'success' => 'Ingreso procesado con éxito.',
            'alertas_inflacion' => $alertasInflacion
        ]);
    }
}