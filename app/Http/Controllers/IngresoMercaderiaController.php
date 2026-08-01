<?php

namespace App\Http\Controllers;

use App\Models\IngresoMercaderia;
use App\Models\IngresoDetalle;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\LoteService;
use App\Services\SucursalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class IngresoMercaderiaController extends Controller
{
    public function __construct(
        private readonly SucursalScopeService $scope
    ) {}

    public function index(Request $request)
    {
        $comercioId = $this->scope->obtenerComercioId();

        $search = $request->input('search');
        $proveedor_id = $request->input('proveedor_id', 'all');
        $sucursal_id = $request->input('sucursal_id', 'all');
        $fecha_desde = $request->input('fecha_desde');
        $fecha_hasta = $request->input('fecha_hasta');

        $query = IngresoMercaderia::with(['proveedor', 'sucursal', 'detalles.producto', 'usuario']);
        $this->scope->aplicarFiltroSucursal($query);

        $ingresos = $query
            ->when($search, fn ($q, $search) => $q->where('numero_remito', 'LIKE', "%{$search}%"))
            ->when($proveedor_id !== 'all', fn ($q) => $q->where('proveedor_id', $proveedor_id))
            ->when($sucursal_id !== 'all', fn ($q) => $q->where('sucursal_id', $sucursal_id))
            ->when($fecha_desde, fn ($q, $v) => $q->whereDate('fecha_ingreso', '>=', $v))
            ->when($fecha_hasta, fn ($q, $v) => $q->whereDate('fecha_ingreso', '<=', $v))
            ->orderBy('fecha_ingreso', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $sucursalesDropdown = $this->scope->obtenerSucursalesPermitidas()
            ->where('estado', true);

        return Inertia::render('Ingresos/Index', [
            'ingresos' => $ingresos,
            'productos' => Producto::where('estado', true)->get(),
            'proveedores' => Proveedor::deComercio($comercioId)->where('estado', true)->get(),
            'sucursales' => $sucursalesDropdown,
            'filtros' => $request->only(['search', 'proveedor_id', 'sucursal_id', 'fecha_desde', 'fecha_hasta']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sucursal_id'              => 'required|exists:sucursales,id',
            'fecha_ingreso'            => 'required|date',
            'numero_remito'            => 'nullable|string',
            'proveedor_id'             => 'nullable|exists:proveedores,id',
            'items'                    => 'required|array|min:1',
            'items.*.producto_id'      => 'required|exists:productos,id',
            'items.*.cantidad'         => 'required|numeric|min:1',
            'items.*.costo'            => 'nullable|numeric|min:0',
            'items.*.fecha_vencimiento' => 'nullable|date',
        ]);

        if (!$this->scope->puedeAccederSucursal((int) $request->sucursal_id)) {
            return redirect()->back()->withErrors([
                'sucursal_id' => 'No tenés acceso a la sucursal seleccionada.',
            ]);
        }

        $sucursalId = (int) $request->sucursal_id;

        $alertasInflacion = [];
        $totalCosto = 0;

        DB::transaction(function () use ($request, $sucursalId, &$alertasInflacion, &$totalCosto) {
            $ingreso = IngresoMercaderia::create([
                'sucursal_id'   => $sucursalId,
                'proveedor_id'  => $request->proveedor_id !== '' ? $request->proveedor_id : null,
                'user_id'       => auth()->id(),
                'fecha_ingreso' => $request->fecha_ingreso,
                'numero_remito' => $request->numero_remito,
                'total_costo'   => 0,
            ]);

            $loteService = app(LoteService::class);

            foreach ($request->items as $item) {
                IngresoDetalle::create([
                    'ingreso_mercaderia_id' => $ingreso->id,
                    'producto_id'           => $item['producto_id'],
                    'cantidad_recibida'     => $item['cantidad'],
                    'costo_unitario'        => $item['costo'],
                    'fecha_vencimiento'     => $item['fecha_vencimiento'] ?? null,
                ]);

                if (!empty($item['fecha_vencimiento'])) {
                    $loteService->upsert(
                        (int) $item['producto_id'],
                        $sucursalId,
                        $item['fecha_vencimiento'],
                        (float) $item['cantidad']
                    );
                }

                $producto = Producto::findOrFail($item['producto_id']);

                $costoNuevo = $item['costo'] ? (float) $item['costo'] : 0;
                if ($costoNuevo <= 0) {
                    $costoNuevo = (float) $producto->precio_costo;
                }
                $costoAnterior = $producto->precio_costo;
                $precioVentaAnterior = $producto->precio_venta;

                $totalCosto += $item['cantidad'] * $costoNuevo;

                $stockLocked = DB::table('producto_sucursal')
                    ->where('producto_id', $item['producto_id'])
                    ->where('sucursal_id', $sucursalId)
                    ->lockForUpdate()
                    ->first();

                $cantidadAnterior = $stockLocked ? (float) $stockLocked->cantidad_fisica : 0;
                $stockTotal = $cantidadAnterior + $item['cantidad'];

                $nuevoPPP = $stockTotal > 0
                    ? round(($cantidadAnterior * $costoAnterior + $item['cantidad'] * $costoNuevo) / $stockTotal, 2)
                    : $costoNuevo;

                $nuevoPrecioVenta = $precioVentaAnterior;

                if ($costoNuevo > $costoAnterior) {
                    $margen = $producto->porcentaje_ganancia
                        ? ($producto->porcentaje_ganancia / 100)
                        : (($precioVentaAnterior / max($costoAnterior, 0.01)) - 1);

                    $nuevoPrecioVenta = round($nuevoPPP * (1 + max($margen, 0)), 2);

                    $alertasInflacion[] = [
                        'producto'     => $producto->nombre,
                        'costo_viejo'  => $costoAnterior,
                        'costo_nuevo'  => $costoNuevo,
                        'precio_viejo' => $precioVentaAnterior,
                        'precio_nuevo' => $nuevoPrecioVenta,
                        'porcentaje'   => round(($margen ?? 0) * 100, 2),
                    ];
                }

                if ($nuevoPPP != $costoAnterior) {
                    $producto->update([
                        'precio_costo' => $nuevoPPP,
                        'precio_venta' => $nuevoPrecioVenta,
                        'precio_venta_actualizado_en' => now(),
                    ]);

                    DB::table('historico_costos')->insert([
                        'producto_id'           => $item['producto_id'],
                        'costo_anterior'        => $costoAnterior,
                        'costo_nuevo'           => $nuevoPPP,
                        'precio_venta_anterior' => $precioVentaAnterior,
                        'precio_venta_nuevo'    => $nuevoPrecioVenta,
                        'user_id'               => auth()->id(),
                        'origen_tipo'           => 'Ingreso Manual',
                        'origen_id'             => $ingreso->id,
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ]);
                }

                $nuevaCantidad = $cantidadAnterior + $item['cantidad'];

                if ($stockLocked) {
                    DB::table('producto_sucursal')
                        ->where('producto_id', $item['producto_id'])
                        ->where('sucursal_id', $sucursalId)
                        ->update([
                            'cantidad_fisica' => DB::raw("cantidad_fisica + " . (float) $item['cantidad']),
                        ]);
                } else {
                    $producto->sucursales()->attach($sucursalId, [
                        'cantidad_fisica'    => $nuevaCantidad,
                        'cantidad_reservada' => 0,
                    ]);
                }

                DB::table('movimientos_stock')->insert([
                    'producto_id'         => $item['producto_id'],
                    'sucursal_id'         => $sucursalId,
                    'user_id'             => auth()->id(),
                    'tipo_movimiento'     => 'Ingreso Manual',
                    'cantidad_anterior'   => $cantidadAnterior,
                    'cantidad_movimiento' => $item['cantidad'],
                    'cantidad_actual'     => $nuevaCantidad,
                    'motivo'              => $request->numero_remito ? "Remito: {$request->numero_remito}" : null,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }

            $ingreso->update(['total_costo' => $totalCosto]);
        });

        return redirect()->back()->with([
            'success' => 'Ingreso procesado y stock actualizado.',
            'alertas_inflacion' => $alertasInflacion,
        ]);
    }
}
