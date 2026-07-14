<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class LoteController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $esJefe = $user->hasRole(['SuperAdmin', 'Administrador Global']);
        $comercioId = $user->branch?->comercio_id;

        $sucursalIds = $comercioId
            ? Sucursal::where('comercio_id', $comercioId)->pluck('id')
            : collect();

        $sucursales = Sucursal::whereIn('id', $sucursalIds)->orderBy('nombre')->get();

        $filtroSucursal = $request->input('sucursal_id');
        $sucursalActiva = session('sucursal_activa_id', $user->branch_id);

        // Filtros
        $busqueda = $request->input('search', '');
        $filtroEstado = $request->input('estado', 'todos');
        $filtroVencimiento = $request->input('vencimiento', 'todos');

        // Base query: sucursal + multi-tenant
        $baseQuery = function ($q) use ($sucursalIds, $filtroSucursal, $esJefe, $sucursalActiva) {
            $q->whereIn('sucursal_id', $sucursalIds);
            if ($filtroSucursal) {
                $q->where('sucursal_id', $filtroSucursal);
            } elseif (!$esJefe) {
                $q->where('sucursal_id', $sucursalActiva);
            }
        };

        $hoy = Carbon::now()->startOfDay();

        // Query principal
        $lotes = Lote::with(['producto', 'sucursal'])
            ->where('stock_actual', '>', 0)
            ->when(true, $baseQuery)
            // Búsqueda por nombre o código de barras
            ->when($busqueda, function ($q) use ($busqueda) {
                $q->whereHas('producto', function ($pq) use ($busqueda) {
                    $pq->where('nombre', 'ILIKE', "%{$busqueda}%")
                       ->orWhere('codigo_barras', 'ILIKE', "%{$busqueda}%");
                });
            })
            // Filtro por estado
            ->when($filtroEstado === 'activos', function ($q) use ($hoy) {
                $q->where('fecha_vencimiento', '>=', $hoy);
            })
            ->when($filtroEstado === 'vencidos', function ($q) use ($hoy) {
                $q->where('fecha_vencimiento', '<', $hoy);
            })
            ->when($filtroEstado === 'liquidacion', function ($q) {
                $q->where('estado_liquidacion', true);
            })
            ->when($filtroEstado === 'por_vencer', function ($q) use ($hoy) {
                $q->where('fecha_vencimiento', '>=', $hoy)
                  ->where('fecha_vencimiento', '<=', $hoy->copy()->addDays(30));
            })
            // Filtro por ventana de vencimiento
            ->when($filtroVencimiento === 'hoy', function ($q) use ($hoy) {
                $q->whereDate('fecha_vencimiento', $hoy);
            })
            ->when($filtroVencimiento === '7d', function ($q) use ($hoy) {
                $q->where('fecha_vencimiento', '>=', $hoy)
                  ->where('fecha_vencimiento', '<=', $hoy->copy()->addDays(7));
            })
            ->when($filtroVencimiento === '15d', function ($q) use ($hoy) {
                $q->where('fecha_vencimiento', '>=', $hoy)
                  ->where('fecha_vencimiento', '<=', $hoy->copy()->addDays(15));
            })
            ->when($filtroVencimiento === '30d', function ($q) use ($hoy) {
                $q->where('fecha_vencimiento', '>=', $hoy)
                  ->where('fecha_vencimiento', '<=', $hoy->copy()->addDays(30));
            })
            ->orderBy('fecha_vencimiento', 'asc')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Lotes/Index', [
            'lotes' => $lotes,
            'sucursales' => $sucursales,
            'filtros' => [
                'search' => $busqueda,
                'estado' => $filtroEstado,
                'vencimiento' => $filtroVencimiento,
                'sucursal_id' => $filtroSucursal,
            ],
        ]);
    }
}
