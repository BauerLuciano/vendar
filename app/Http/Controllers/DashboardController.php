<?php

namespace App\Http\Controllers;

use App\Models\CuentaCorriente;
use App\Models\PedidoWeb;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $user->load('branch');
        $esJefe = $user->hasRole(['SuperAdmin', 'Administrador Global']);

        $comercioId = $user->branch?->comercio_id;
        $sucursalIds = $comercioId
            ? Sucursal::where('comercio_id', $comercioId)->pluck('id')
            : collect();

        // 1. Deuda Total (global, sin filtro)
        $deudaTotal = CuentaCorriente::sum('saldo_deudor') ?? 0;

        // 2. Ventas de Hoy (filtradas por sucursal)
        $ventasHoyQuery = DB::table('ventas')
            ->join('turno_cajas', 'ventas.turno_caja_id', '=', 'turno_cajas.id')
            ->whereDate('ventas.created_at', Carbon::today());

        if (!$esJefe && $user->branch_id) {
            $ventasHoyQuery->where('turno_cajas.sucursal_id', $user->branch_id);
        } elseif ($sucursalIds->isNotEmpty()) {
            $ventasHoyQuery->whereIn('turno_cajas.sucursal_id', $sucursalIds);
        }

        $ventasHoy = $ventasHoyQuery->sum('ventas.total') ?? 0;

        // 3. Cajas Activas (filtradas por sucursal)
        $cajasActivasQuery = DB::table('turno_cajas')
            ->whereNull('monto_cierre');

        if (!$esJefe && $user->branch_id) {
            $cajasActivasQuery->where('sucursal_id', $user->branch_id);
        } elseif ($sucursalIds->isNotEmpty()) {
            $cajasActivasQuery->whereIn('sucursal_id', $sucursalIds);
        }

        $cajasActivas = $cajasActivasQuery->count();

        // 4. Productos Bajo Stock
        $queryStock = DB::table('productos')
            ->join('producto_sucursal', 'productos.id', '=', 'producto_sucursal.producto_id')
            ->join('sucursales', 'sucursales.id', '=', 'producto_sucursal.sucursal_id')
            ->select(
                'productos.nombre as producto',
                'productos.stock_minimo',
                'productos.unidad_medida',
                'producto_sucursal.cantidad_fisica as cantidad_fisica',
                'sucursales.nombre as sucursal'
            )
            ->where('productos.estado', true)
            ->whereRaw('producto_sucursal.cantidad_fisica <= productos.stock_minimo');

        if (!$esJefe && $user->branch_id) {
            $queryStock->where('producto_sucursal.sucursal_id', $user->branch_id);
        } elseif ($sucursalIds->isNotEmpty()) {
            $queryStock->whereIn('producto_sucursal.sucursal_id', $sucursalIds);
        }

        $productosBajoStock = $queryStock->get();

        // 5. Pedidos Web Pendientes
        $pedidosQuery = PedidoWeb::whereIn('estado_pedido', ['nuevo', 'preparando', 'en_camino']);
        if (!$esJefe && $user->branch_id) {
            $pedidosQuery->where('sucursal_id', $user->branch_id);
        } elseif ($sucursalIds->isNotEmpty()) {
            $pedidosQuery->where('comercio_id', $comercioId);
        }
        $pedidosWebPendientes = $pedidosQuery->count();

        return Inertia::render('Dashboard', [
            'deudaTotal' => (float) $deudaTotal,
            'ventasHoy' => (float) $ventasHoy,
            'cajasActivas' => $cajasActivas,
            'productosBajoStock' => $productosBajoStock,
            'pedidosWebPendientes' => $pedidosWebPendientes,
            'esJefe' => $esJefe,
            'sucursalUsuario' => $user->branch ? $user->branch->nombre : 'Sede Central'
        ]);
    }
}