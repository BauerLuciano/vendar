<?php

namespace App\Http\Controllers;

use App\Models\IngresoMercaderia;
use App\Models\IngresoDetalle;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\Lote; // 🔥 AGREGAMOS EL MODELO LOTE
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class IngresoMercaderiaController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $comercioId = $user->branch?->comercio_id;
        $sucursalIds = $comercioId
            ? Sucursal::where('comercio_id', $comercioId)->pluck('id')
            : collect();

        $search = $request->input('search');
        $proveedor_id = $request->input('proveedor_id', 'all');
        $sucursal_id = $request->input('sucursal_id', 'all');
        $fecha_desde = $request->input('fecha_desde');
        $fecha_hasta = $request->input('fecha_hasta');

        $ingresos = IngresoMercaderia::with(['proveedor', 'sucursal', 'detalles.producto', 'usuario'])
            ->when($sucursalIds->isNotEmpty(), fn ($q) => $q->whereIn('sucursal_id', $sucursalIds))
            ->when($search, function ($q, $search) {
                $q->where('numero_remito', 'LIKE', "%{$search}%");
            })
            ->when($proveedor_id !== 'all', function ($q) use ($proveedor_id) {
                $q->where('proveedor_id', $proveedor_id);
            })
            ->when($sucursal_id !== 'all', function ($q) use ($sucursal_id) {
                $q->where('sucursal_id', $sucursal_id);
            })
            ->when($fecha_desde, function ($q, $fecha_desde) {
                $q->whereDate('fecha_ingreso', '>=', $fecha_desde);
            })
            ->when($fecha_hasta, function ($q, $fecha_hasta) {
                $q->whereDate('fecha_ingreso', '<=', $fecha_hasta);
            })
            ->orderBy('fecha_ingreso', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Ingresos/Index', [
            'ingresos' => $ingresos,
            'productos' => Producto::where('estado', true)
                ->when($sucursalIds->isNotEmpty(), fn ($q) => $q->whereHas('sucursales', fn ($sq) => $sq->whereIn('sucursales.id', $sucursalIds)))
                ->get(),
            'proveedores' => Proveedor::deComercio($comercioId)->where('estado', true)->get(),
            'sucursales' => $sucursalIds->isNotEmpty()
                ? Sucursal::whereIn('id', $sucursalIds)->where('estado', true)->get()
                : Sucursal::where('estado', true)->get(),
            'filtros' => $request->only(['search', 'proveedor_id', 'sucursal_id', 'fecha_desde', 'fecha_hasta'])
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
            'items.*.cantidad' => 'required|numeric|min:1',
            'items.*.costo' => 'required|numeric|min:0',
            'items.*.fecha_vencimiento' => 'nullable|date',
        ]);

        $user = auth()->user();
        $comercioId = $user->branch?->comercio_id;
        $sucursalIds = $comercioId
            ? Sucursal::where('comercio_id', $comercioId)->pluck('id')
            : collect();
        if ($sucursalIds->isNotEmpty() && !$sucursalIds->contains($request->sucursal_id)) {
            return redirect()->back()->withErrors(['error' => 'La sucursal seleccionada no pertenece a tu comercio.']);
        }

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
                IngresoDetalle::create([
                    'ingreso_mercaderia_id' => $ingreso->id,
                    'producto_id' => $item['producto_id'],
                    'cantidad_recibida' => $item['cantidad'],
                    'costo_unitario' => $item['costo'],
                    'fecha_vencimiento' => $item['fecha_vencimiento'] ?? null,
                ]);

                // 🔥 CREACIÓN DEL LOTE (Si el encargado le puso fecha)
                if (!empty($item['fecha_vencimiento'])) {
                    Lote::create([
                        'producto_id' => $item['producto_id'],
                        'sucursal_id' => $request->sucursal_id,
                        'fecha_vencimiento' => $item['fecha_vencimiento'],
                        'stock_inicial' => $item['cantidad'],
                        'stock_actual' => $item['cantidad'],
                        'estado_liquidacion' => false,
                    ]);
                }

                $producto = Producto::when($sucursalIds->isNotEmpty(), fn ($q) => $q->whereHas('sucursales', fn ($sq) => $sq->whereIn('sucursales.id', $sucursalIds)))
                    ->findOrFail($item['producto_id']);
                $costoNuevo = $item['costo'];
                $costoAnterior = $producto->precio_costo;

                // DETECTAR INFLACIÓN Y RECALCULAR PRECIO DE VENTA
                if ($costoNuevo > $costoAnterior) {
                    $margen = $producto->porcentaje_ganancia 
                        ? ($producto->porcentaje_ganancia / 100) 
                        : (($producto->precio_venta / $costoAnterior) - 1);

                    $nuevoPrecioVenta = $costoNuevo * (1 + $margen);

                    $alertasInflacion[] = [
                        'producto' => $producto->nombre,
                        'costo_viejo' => $costoAnterior,
                        'costo_nuevo' => $costoNuevo,
                        'precio_viejo' => $producto->precio_venta,
                        'precio_nuevo' => round($nuevoPrecioVenta, 2),
                        'porcentaje' => round($margen * 100, 2)
                    ];

                    $producto->update([
                        'precio_costo' => $costoNuevo,
                        'precio_venta' => round($nuevoPrecioVenta, 2)
                    ]);
                }

                // Actualización de Stock (Igual que antes)
                $pivot = $producto->sucursales()->where('sucursal_id', $request->sucursal_id)->first();
                
                if ($pivot) {
                    $nuevaCantidad = $pivot->pivot->cantidad_fisica + $item['cantidad'];
                    $producto->sucursales()->updateExistingPivot($request->sucursal_id, [
                        'cantidad_fisica' => $nuevaCantidad
                    ]);
                } else {
                    $producto->sucursales()->attach($request->sucursal_id, [
                        'cantidad_fisica' => $item['cantidad'],
                        'cantidad_reservada' => 0
                    ]);
                }
            }
        });

        return redirect()->back()->with([
            'success' => 'Ingreso procesado y stock actualizado.',
            'alertas_inflacion' => $alertasInflacion
        ]);
    }
}