<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\SucursalScopeService;

class ReporteController extends Controller
{
    public function __construct(private SucursalScopeService $scope) {}

    public function index(Request $request)
    {
        $sucursalesIds = $this->scope->esAdminGlobal()
            ? $this->scope->obtenerSucursalesDelComercioIds()
            : $this->scope->obtenerSucursalesPermitidasIds();

        $fechaDesde = $request->input('fecha_desde', now()->startOfDay()->toDateString());
        $fechaHasta = $request->input('fecha_hasta', now()->endOfDay()->toDateString());

        $resumen = $this->calcularResumen($sucursalesIds, $fechaDesde, $fechaHasta);
        $metodosPago = $this->calcularMetodosPago($sucursalesIds, $fechaDesde, $fechaHasta);
        $topProductos = $this->obtenerTopProductos($sucursalesIds, $fechaDesde, $fechaHasta);
        $ventasRecientes = $this->obtenerVentasRecientes($sucursalesIds, $fechaDesde, $fechaHasta);

        return Inertia::render('Reportes/Index', [
            'resumen' => $resumen,
            'metodos_pago' => $metodosPago,
            'top_productos' => $topProductos,
            'ventas_recientes' => $ventasRecientes,
            'filtros' => [
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
            ],
            'sucursales' => $this->scope->obtenerSucursalesPermitidas()
                ->select('id', 'nombre')
                ->get(),
        ]);
    }

    public function rotacion(Request $request)
    {
        $sucursalesIds = $this->scope->esAdminGlobal()
            ? $this->scope->obtenerSucursalesDelComercioIds()
            : $this->scope->obtenerSucursalesPermitidasIds();

        if (empty($sucursalesIds)) {
            return response()->json(['data' => [], 'total_valor' => 0]);
        }

        $diasMin = max(1, (int) $request->input('dias', 30));
        $sucursalFiltro = $request->input('sucursal_id');
        $sucursalesQuery = $sucursalFiltro
            ? array_intersect($sucursalesIds, [$sucursalFiltro])
            : $sucursalesIds;

        if (empty($sucursalesQuery)) {
            return response()->json(['data' => [], 'total_valor' => 0]);
        }

        $productos = DB::table('productos')
            ->join('producto_sucursal', 'productos.id', '=', 'producto_sucursal.producto_id')
            ->leftJoin('detalle_ventas', 'productos.id', '=', 'detalle_ventas.producto_id')
            ->leftJoin('ventas', function ($join) {
                $join->on('detalle_ventas.venta_id', '=', 'ventas.id')
                    ->where('ventas.estado', 'Completada');
            })
            ->whereIn('producto_sucursal.sucursal_id', $sucursalesQuery)
            ->where('productos.estado', true)
            ->where('producto_sucursal.cantidad_fisica', '>', 0)
            ->selectRaw('
                productos.id,
                productos.nombre,
                productos.precio_costo,
                productos.precio_venta,
                producto_sucursal.cantidad_fisica,
                producto_sucursal.sucursal_id,
                ROUND(producto_sucursal.cantidad_fisica * productos.precio_costo, 2) as valor_inmovilizado,
                MAX(ventas.created_at) as ultima_venta
            ')
            ->groupBy(
                'productos.id', 'productos.nombre', 'productos.precio_costo',
                'productos.precio_venta', 'producto_sucursal.cantidad_fisica',
                'producto_sucursal.sucursal_id'
            )
            ->get()
            ->map(function ($p) {
                $ultimaVenta = $p->ultima_venta ? \Carbon\Carbon::parse($p->ultima_venta) : null;
                $dias = $ultimaVenta ? now()->diffInDays($ultimaVenta) : null;

                return [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'precio_costo' => (float) $p->precio_costo,
                    'precio_venta' => (float) $p->precio_venta,
                    'stock' => (float) $p->cantidad_fisica,
                    'valor_inmovilizado' => (float) $p->valor_inmovilizado,
                    'ultima_venta' => $ultimaVenta?->toDateString(),
                    'dias_sin_venta' => $dias ?? 9999,
                    'sucursal_id' => $p->sucursal_id,
                ];
            })
            ->filter(fn ($p) => $p['dias_sin_venta'] >= $diasMin)
            ->sortByDesc('dias_sin_venta')
            ->values()
            ->toArray();

        return response()->json([
            'data' => $productos,
            'total_valor' => array_sum(array_column($productos, 'valor_inmovilizado')),
        ]);
    }

    public function pdf(Request $request)
    {
        $sucursalesIds = $this->scope->esAdminGlobal()
            ? $this->scope->obtenerSucursalesDelComercioIds()
            : $this->scope->obtenerSucursalesPermitidasIds();

        $fechaDesde = $request->input('fecha_desde', now()->startOfDay()->toDateString());
        $fechaHasta = $request->input('fecha_hasta', now()->endOfDay()->toDateString());

        $resumen = $this->calcularResumen($sucursalesIds, $fechaDesde, $fechaHasta);
        $metodosPago = $this->calcularMetodosPago($sucursalesIds, $fechaDesde, $fechaHasta);
        $topProductos = $this->obtenerTopProductos($sucursalesIds, $fechaDesde, $fechaHasta);

        $nombreEmpresa = DB::table('configuraciones')
            ->where('clave', 'nombre_empresa')
            ->value('valor') ?? 'VendAR';

        $pdf = Pdf::loadView('pdf.reporte-ventas', [
            'nombreEmpresa' => $nombreEmpresa,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'resumen' => $resumen,
            'metodosPago' => $metodosPago,
            'topProductos' => $topProductos,
            'fechaGeneracion' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download("reporte-ventas_{$fechaDesde}_{$fechaHasta}.pdf");
    }

    private function calcularResumen(array $sucursalesIds, string $desde, string $hasta): array
    {
        if (empty($sucursalesIds)) {
            return ['total_ventas' => 0, 'cantidad_ventas' => 0, 'ticket_promedio' => 0];
        }

        $data = DB::table('ventas')
            ->join('turno_cajas', 'ventas.turno_caja_id', '=', 'turno_cajas.id')
            ->whereIn('turno_cajas.sucursal_id', $sucursalesIds)
            ->where('ventas.estado', 'Completada')
            ->whereDate('ventas.created_at', '>=', $desde)
            ->whereDate('ventas.created_at', '<=', $hasta)
            ->selectRaw('COALESCE(SUM(ventas.total), 0) as total_ventas')
            ->selectRaw('COUNT(*) as cantidad_ventas')
            ->first();

        $total = (float) ($data->total_ventas ?? 0);
        $cantidad = (int) ($data->cantidad_ventas ?? 0);

        return [
            'total_ventas' => $total,
            'cantidad_ventas' => $cantidad,
            'ticket_promedio' => $cantidad > 0 ? round($total / $cantidad, 2) : 0,
        ];
    }

    private function labelMetodo(string $metodo): string
    {
        return match ($metodo) {
            'EFECTIVO' => 'Efectivo',
            'DEBITO' => 'Débito',
            'CREDITO' => 'Crédito',
            'TRANSFERENCIA' => 'Transferencia',
            'MERCADO_PAGO' => 'Mercado Pago',
            'CUENTA_CORRIENTE' => 'Cuenta Corriente',
            'MULTIPLE' => 'Pago Dividido',
            default => $metodo,
        };
    }

    private function calcularMetodosPago(array $sucursalesIds, string $desde, string $hasta): array
    {
        if (empty($sucursalesIds)) {
            return [];
        }

        $pagosSimples = DB::table('ventas')
            ->join('turno_cajas', 'ventas.turno_caja_id', '=', 'turno_cajas.id')
            ->whereIn('turno_cajas.sucursal_id', $sucursalesIds)
            ->where('ventas.estado', 'Completada')
            ->where('ventas.metodo_pago', '!=', 'MULTIPLE')
            ->whereDate('ventas.created_at', '>=', $desde)
            ->whereDate('ventas.created_at', '<=', $hasta)
            ->selectRaw('ventas.metodo_pago, COUNT(*) as cantidad, SUM(ventas.total) as total')
            ->groupBy('ventas.metodo_pago')
            ->get()
            ->keyBy('metodo_pago');

        $pagosMultiples = DB::table('ventas')
            ->join('turno_cajas', 'ventas.turno_caja_id', '=', 'turno_cajas.id')
            ->whereIn('turno_cajas.sucursal_id', $sucursalesIds)
            ->where('ventas.estado', 'Completada')
            ->where('ventas.metodo_pago', 'MULTIPLE')
            ->whereDate('ventas.created_at', '>=', $desde)
            ->whereDate('ventas.created_at', '<=', $hasta)
            ->get(['ventas.pagos']);

        $metodos = [];
        foreach ($pagosSimples as $metodo => $data) {
            $metodos[] = [
                'metodo' => $metodo,
                'label' => $this->labelMetodo($metodo),
                'cantidad' => (int) $data->cantidad,
                'total' => (float) $data->total,
            ];
        }

        $multiCantidad = 0;
        foreach ($pagosMultiples as $venta) {
            $pagos = is_string($venta->pagos) ? json_decode($venta->pagos, true) : $venta->pagos;
            if (is_array($pagos)) {
                foreach ($pagos as $pago) {
                    $metodo = $pago['metodo_pago'] ?? 'OTRO';
                    $monto = (float) ($pago['monto'] ?? 0);
                    $idx = array_search($metodo, array_column($metodos, 'metodo'));
                    if ($idx === false) {
                        $metodos[] = [
                            'metodo' => $metodo,
                            'label' => $this->labelMetodo($metodo),
                            'cantidad' => 0,
                            'total' => 0,
                        ];
                        $idx = count($metodos) - 1;
                    }
                    $metodos[$idx]['cantidad']++;
                    $metodos[$idx]['total'] += $monto;
                }
            }
            $multiCantidad++;
        }

        if ($multiCantidad > 0) {
            $metodos[] = [
                'metodo' => 'MULTIPLE',
                'label' => 'Pago Dividido',
                'cantidad' => $multiCantidad,
                'total' => 0,
            ];
        }

        return $metodos;
    }

    private function obtenerTopProductos(array $sucursalesIds, string $desde, string $hasta): array
    {
        if (empty($sucursalesIds)) {
            return [];
        }

        return DB::table('detalle_ventas')
            ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('turno_cajas', 'ventas.turno_caja_id', '=', 'turno_cajas.id')
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
            ->whereIn('turno_cajas.sucursal_id', $sucursalesIds)
            ->where('ventas.estado', 'Completada')
            ->whereDate('ventas.created_at', '>=', $desde)
            ->whereDate('ventas.created_at', '<=', $hasta)
            ->selectRaw('productos.id, productos.nombre, SUM(detalle_ventas.cantidad) as cantidad, SUM(detalle_ventas.subtotal) as total')
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc('cantidad')
            ->limit(20)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'cantidad' => (float) $p->cantidad,
                'total' => (float) $p->total,
            ])
            ->toArray();
    }

    private function obtenerVentasRecientes(array $sucursalesIds, string $desde, string $hasta): array
    {
        if (empty($sucursalesIds)) {
            return [];
        }

        return DB::table('ventas')
            ->join('turno_cajas', 'ventas.turno_caja_id', '=', 'turno_cajas.id')
            ->leftJoin('consumidores', 'ventas.consumidor_id', '=', 'consumidores.id')
            ->whereIn('turno_cajas.sucursal_id', $sucursalesIds)
            ->whereDate('ventas.created_at', '>=', $desde)
            ->whereDate('ventas.created_at', '<=', $hasta)
            ->selectRaw('ventas.id, ventas.total, ventas.metodo_pago, ventas.created_at, consumidores.nombre as cliente_nombre, consumidores.apellido as cliente_apellido')
            ->orderByDesc('ventas.created_at')
            ->limit(50)
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'total' => (float) $v->total,
                'metodo_pago' => $v->metodo_pago,
                'metodo_pago_label' => $this->labelMetodo($v->metodo_pago),
                'cliente' => $v->cliente_nombre ? trim($v->cliente_nombre . ' ' . $v->cliente_apellido) : 'Mostrador',
                'fecha' => $v->created_at,
            ])
            ->toArray();
    }
}
