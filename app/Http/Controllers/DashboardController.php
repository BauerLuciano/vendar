<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\CuentaCorriente;
use App\Models\DetalleVenta;
use App\Models\PedidoWeb;
use App\Models\Sucursal;
use App\Models\TurnoCaja;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Services\OnboardingService;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $user->load('branch');
        $esJefe = $user->hasRole(['SuperAdmin', 'Administrador Global']);

        $sucursalActivaId = (int) session('sucursal_activa_id', $user->branch_id);

        $comercioId = $user->branch?->comercio_id;
        $sucursalIds = $comercioId
            ? Sucursal::where('comercio_id', $comercioId)->pluck('id')
            : collect();

        $fechaDesde = $request->input('desde', Carbon::today()->subDays(6)->format('Y-m-d'));
        $fechaHasta = $request->input('hasta', Carbon::today()->format('Y-m-d'));

        // 1. Deuda Total
        $deudaTotal = CuentaCorriente::when($comercioId, function ($q) use ($comercioId) {
            $q->whereHas('consumidor', fn($sub) => $sub->where('comercio_id', $comercioId));
        })->sum('saldo_deudor') ?? 0;

        // 2. Ventas de Hoy
        $ventasHoyQuery = Venta::join('turno_cajas', 'ventas.turno_caja_id', '=', 'turno_cajas.id')
            ->whereDate('ventas.created_at', Carbon::today());
        $this->scopeSucursal($ventasHoyQuery, $esJefe, $user, $sucursalIds, 'turno_cajas.sucursal_id');
        $ventasHoy = (float) ($ventasHoyQuery->sum('ventas.total') ?? 0);

        // 3. Ventas del período filtrado
        $ventasPeriodoQuery = Venta::join('turno_cajas', 'ventas.turno_caja_id', '=', 'turno_cajas.id');
        $this->scopeSucursal($ventasPeriodoQuery, $esJefe, $user, $sucursalIds, 'turno_cajas.sucursal_id');
        $ventasPeriodo = (float) ($ventasPeriodoQuery
            ->whereBetween('ventas.created_at', [$fechaDesde . ' 00:00:00', $fechaHasta . ' 23:59:59'])
            ->sum('ventas.total') ?? 0);

        // 4. Cajas activas (lista completa)
        $cajasQuery = TurnoCaja::with(['cajero:id,name', 'caja', 'sucursal:id,nombre'])
            ->whereNull('monto_cierre');

        if (!$esJefe) {
            // Cajero ve solo SU turno activo (sin importar sucursal)
            $cajasQuery->where('user_id', $user->id);
        } elseif ($sucursalIds->isNotEmpty()) {
            // Jefe ve todas las cajas activas del comercio
            $cajasQuery->whereIn('sucursal_id', $sucursalIds);
        }

        $cajasActivasLista = $cajasQuery->get()->map(function ($t) {
            $facturado = (float) Venta::where('turno_caja_id', $t->id)->sum('total');
            return [
                'id'             => $t->id,
                'cajero'         => $t->cajero?->name ?? '—',
                'caja'           => $t->caja?->nombre ?? '—',
                'sucursal'       => $t->sucursal?->nombre ?? '—',
                'monto_apertura' => (float) ($t->monto_apertura ?? 0),
                'fecha_apertura' => $t->fecha_apertura?->format('H:i') ?? '—',
                'facturado'      => $facturado,
            ];
        });

        $cajasActivas = $cajasActivasLista->count();

        // 5. Productos Bajo Stock
        $productosBajoStock = DB::table('productos')
            ->join('producto_sucursal', 'productos.id', '=', 'producto_sucursal.producto_id')
            ->join('sucursales', 'sucursales.id', '=', 'producto_sucursal.sucursal_id')
            ->select(
                'productos.nombre as producto',
                'productos.stock_minimo',
                'productos.unidad_medida',
                'producto_sucursal.cantidad_fisica as cantidad_fisica',
                'sucursales.nombre as sucursal',
                'sucursales.id as sucursal_id'
            )
            ->where('productos.estado', true)
            ->whereRaw('producto_sucursal.cantidad_fisica <= productos.stock_minimo');

        if (!$esJefe && $sucursalActivaId) {
            $productosBajoStock->where('producto_sucursal.sucursal_id', $sucursalActivaId);
        } elseif ($sucursalIds->isNotEmpty()) {
            $productosBajoStock->whereIn('producto_sucursal.sucursal_id', $sucursalIds);
        }

        $productosBajoStock = $productosBajoStock->get();

        // 6. Pedidos Web Pendientes
        $pedidosQuery = PedidoWeb::with('sucursal:id,nombre')
            ->whereIn('estado_pedido', ['nuevo', 'preparando', 'en_camino']);

        if (!$esJefe && $sucursalActivaId) {
            $pedidosQuery->where('sucursal_id', $sucursalActivaId);
        } elseif ($sucursalIds->isNotEmpty()) {
            $pedidosQuery->where('comercio_id', $comercioId);
        }

        $pedidosRecientes = $pedidosQuery->latest()->take(10)->get()->map(fn($p) => [
            'id'             => $p->id,
            'cliente'        => $p->cliente_nombre,
            'total'          => (float) $p->total,
            'estado'         => $p->estado_pedido,
            'estado_display' => $p->estado_display,
            'tipo_entrega'   => $p->tipo_entrega,
            'sucursal'       => $p->sucursal?->nombre,
            'desde'          => $p->created_at->diffForHumans(),
        ]);

        $pedidosWebPendientes = $pedidosQuery->count();

        // 7. Top productos del período filtrado
        $topProductosQuery = DetalleVenta::select(
            'productos.nombre',
            'productos.id as producto_id',
            DB::raw('SUM(detalle_ventas.cantidad) as cantidad'),
            DB::raw('SUM(detalle_ventas.subtotal) as total')
        )
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
            ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('turno_cajas', 'ventas.turno_caja_id', '=', 'turno_cajas.id')
            ->whereBetween('ventas.created_at', [$fechaDesde . ' 00:00:00', $fechaHasta . ' 23:59:59'])
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc('cantidad');

        $this->scopeSucursal($topProductosQuery, $esJefe, $user, $sucursalIds, 'turno_cajas.sucursal_id');

        $topProductos = $topProductosQuery->take(5)->get()->map(fn($p) => [
            'nombre'   => $p->nombre,
            'cantidad' => (int) $p->cantidad,
            'total'    => (float) $p->total,
        ]);

        // 8. Ventas por día (período filtrado)
        $ventasPorDiaQuery = Venta::join('turno_cajas', 'ventas.turno_caja_id', '=', 'turno_cajas.id')
            ->whereBetween('ventas.created_at', [$fechaDesde . ' 00:00:00', $fechaHasta . ' 23:59:59'])
            ->select(DB::raw('DATE(ventas.created_at) as fecha'), DB::raw('COALESCE(SUM(ventas.total), 0) as total'))
            ->groupBy('fecha')
            ->orderBy('fecha');

        $this->scopeSucursal($ventasPorDiaQuery, $esJefe, $user, $sucursalIds, 'turno_cajas.sucursal_id');

        $ventasAgrupadas = $ventasPorDiaQuery->get()->keyBy('fecha');

        $ventasPorDia = collect();
        $inicio = Carbon::parse($fechaDesde);
        $fin = Carbon::parse($fechaHasta);
        for ($d = $inicio->copy(); $d->lte($fin); $d->addDay()) {
            $f = $d->format('Y-m-d');
            $ventasPorDia->push([
                'fecha' => $f,
                'dia'   => ucfirst($d->isoFormat('dddd')),
                'total' => (float) ($ventasAgrupadas[$f]->total ?? 0),
            ]);
        }

        $onboardingService = new OnboardingService();
        $estadoOnboarding = $onboardingService->estado();

        return Inertia::render('Dashboard', [
            'deudaTotal'          => (float) $deudaTotal,
            'ventasHoy'           => $ventasHoy,
            'ventasPeriodo'       => $ventasPeriodo,
            'cajasActivas'        => $cajasActivas,
            'cajasActivasLista'   => $cajasActivasLista,
            'productosBajoStock'  => $productosBajoStock,
            'pedidosWebPendientes'=> $pedidosWebPendientes,
            'pedidosRecientes'    => $pedidosRecientes,
            'topProductos'        => $topProductos,
            'ventasPorDia'        => $ventasPorDia,
            'fechaDesde'          => $fechaDesde,
            'fechaHasta'          => $fechaHasta,
            'esJefe'              => $esJefe,
            'sucursalUsuario'     => Sucursal::find($sucursalActivaId)?->nombre ?? ($user->branch?->nombre ?? 'Sede Central'),
            'estadoOnboarding'    => $estadoOnboarding,
        ]);
    }

    public function descargarPDF(Request $request)
    {
        $user = auth()->user();
        $user->load('branch');
        $esJefe = $user->hasRole(['SuperAdmin', 'Administrador Global']);

        $sucursalActivaId = (int) session('sucursal_activa_id', $user->branch_id);

        $comercioId = $user->branch?->comercio_id;
        $sucursalIds = $comercioId
            ? Sucursal::where('comercio_id', $comercioId)->pluck('id')
            : collect();

        $fechaDesde = $request->input('desde', Carbon::today()->subDays(6)->format('Y-m-d'));
        $fechaHasta = $request->input('hasta', Carbon::today()->format('Y-m-d'));

        $config = Configuracion::pluck('valor', 'clave')->toArray();

        $logoBase64 = null;
        if (!empty($config['logo_empresa'])) {
            $pathLogo = storage_path('app/public/' . $config['logo_empresa']);
            if (file_exists($pathLogo) && is_file($pathLogo)) {
                $logoBase64 = 'data:image/' . pathinfo($pathLogo, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($pathLogo));
            }
        }

        // Recolectar datos igual que index()
        $deudaTotal = CuentaCorriente::when($comercioId, function ($q) use ($comercioId) {
            $q->whereHas('consumidor', fn($sub) => $sub->where('comercio_id', $comercioId));
        })->sum('saldo_deudor') ?? 0;

        $ventasPeriodoQuery = Venta::join('turno_cajas', 'ventas.turno_caja_id', '=', 'turno_cajas.id');
        $this->scopeSucursal($ventasPeriodoQuery, $esJefe, $user, $sucursalIds, 'turno_cajas.sucursal_id');
        $ventasPeriodo = (float) ($ventasPeriodoQuery
            ->whereBetween('ventas.created_at', [$fechaDesde . ' 00:00:00', $fechaHasta . ' 23:59:59'])
            ->sum('ventas.total') ?? 0);

        $cajasCount = TurnoCaja::whereNull('monto_cierre')
            ->when(!$esJefe, fn($q) => $q->where('user_id', $user->id))
            ->when($sucursalIds->isNotEmpty() && $esJefe, fn($q) => $q->whereIn('sucursal_id', $sucursalIds))
            ->count();

        $pedidosQuery = PedidoWeb::whereIn('estado_pedido', ['nuevo', 'preparando', 'en_camino']);
        if (!$esJefe && $sucursalActivaId) {
            $pedidosQuery->where('sucursal_id', $sucursalActivaId);
        } elseif ($sucursalIds->isNotEmpty()) {
            $pedidosQuery->where('comercio_id', $comercioId);
        }
        $pedidosPendientes = $pedidosQuery->count();

        $pedidosLista = $pedidosQuery->latest()->take(10)->get()->map(fn($p) => [
            'cliente'        => $p->cliente_nombre,
            'total'          => (float) $p->total,
            'estado'         => $p->estado_pedido,
            'estado_display' => $p->estado_display,
            'tipo_entrega'   => $p->tipo_entrega,
            'fecha'          => $p->created_at->format('d/m/Y H:i'),
        ]);

        $topProductosQuery = DetalleVenta::select('productos.nombre', DB::raw('SUM(detalle_ventas.cantidad) as cantidad'), DB::raw('SUM(detalle_ventas.subtotal) as total'))
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
            ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('turno_cajas', 'ventas.turno_caja_id', '=', 'turno_cajas.id')
            ->whereBetween('ventas.created_at', [$fechaDesde . ' 00:00:00', $fechaHasta . ' 23:59:59'])
            ->groupBy('productos.id', 'productos.nombre')->orderByDesc('cantidad');
        $this->scopeSucursal($topProductosQuery, $esJefe, $user, $sucursalIds, 'turno_cajas.sucursal_id');
        $topProductos = $topProductosQuery->take(10)->get();

        $bajoStock = DB::table('productos')
            ->join('producto_sucursal', 'productos.id', '=', 'producto_sucursal.producto_id')
            ->join('sucursales', 'sucursales.id', '=', 'producto_sucursal.sucursal_id')
            ->select('productos.nombre as producto', 'productos.stock_minimo', 'producto_sucursal.cantidad_fisica as cantidad_fisica', 'sucursales.nombre as sucursal')
            ->where('productos.estado', true)->whereRaw('producto_sucursal.cantidad_fisica <= productos.stock_minimo');
        if (!$esJefe && $sucursalActivaId) {
            $bajoStock->where('producto_sucursal.sucursal_id', $sucursalActivaId);
        } elseif ($sucursalIds->isNotEmpty()) {
            $bajoStock->whereIn('producto_sucursal.sucursal_id', $sucursalIds);
        }
        $bajoStock = $bajoStock->get();

        $ventasPorDiaQuery = Venta::join('turno_cajas', 'ventas.turno_caja_id', '=', 'turno_cajas.id')
            ->whereBetween('ventas.created_at', [$fechaDesde . ' 00:00:00', $fechaHasta . ' 23:59:59'])
            ->select(DB::raw('DATE(ventas.created_at) as fecha'), DB::raw('COALESCE(SUM(ventas.total), 0) as total'))
            ->groupBy('fecha')->orderBy('fecha');
        $this->scopeSucursal($ventasPorDiaQuery, $esJefe, $user, $sucursalIds, 'turno_cajas.sucursal_id');
        $ventasAgrupadas = $ventasPorDiaQuery->get()->keyBy('fecha');

        $ventasPorDia = collect();
        $inicio = Carbon::parse($fechaDesde);
        $fin = Carbon::parse($fechaHasta);
        for ($d = $inicio->copy(); $d->lte($fin); $d->addDay()) {
            $f = $d->format('Y-m-d');
            $ventasPorDia->push([
                'fecha' => $f,
                'dia'   => ucfirst($d->isoFormat('dddd')),
                'total' => (float) ($ventasAgrupadas[$f]->total ?? 0),
            ]);
        }

        $pdf = Pdf::loadView('pdf.dashboard', [
            'config'          => $config,
            'logo'            => $logoBase64,
            'fechaDesde'      => $fechaDesde,
            'fechaHasta'      => $fechaHasta,
            'usuario'         => $user->name,
            'fecha'           => now()->format('d/m/Y'),
            'hora'            => now()->format('H:i'),
            'sucursal'        => Sucursal::find($sucursalActivaId)?->nombre ?? ($user->branch?->nombre ?? 'Todas'),
            'ventasPeriodo'   => $ventasPeriodo,
            'deudaTotal'      => $deudaTotal,
            'cajasActivas'    => $cajasCount,
            'pedidosPendientes' => $pedidosPendientes,
            'pedidosLista'    => $pedidosLista,
            'topProductos'    => $topProductos,
            'bajoStock'       => $bajoStock,
            'ventasPorDia'    => $ventasPorDia,
        ]);

        $pdf->setPaper('A4', 'portrait');
        return $pdf->download('Dashboard_' . now()->format('Y-m-d') . '.pdf');
    }

    private function scopeSucursal($query, $esJefe, $user, $sucursalIds, $columna = 'sucursal_id')
    {
        $sucursalActivaId = (int) session('sucursal_activa_id', $user->branch_id);
        if (!$esJefe && $sucursalActivaId) {
            $query->where($columna, $sucursalActivaId);
        } elseif ($sucursalIds->isNotEmpty()) {
            $query->whereIn($columna, $sucursalIds);
        }
    }
}
