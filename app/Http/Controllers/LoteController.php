<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use App\Models\Sucursal;
use Illuminate\Http\Request;
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

        $lotes = Lote::with(['producto', 'sucursal'])
            ->where('stock_actual', '>', 0)
            ->whereIn('sucursal_id', $sucursalIds)
            ->when($filtroSucursal, function ($q) use ($filtroSucursal) {
                $q->where('sucursal_id', $filtroSucursal);
            }, function ($q) use ($esJefe, $sucursalActiva) {
                if (!$esJefe) {
                    $q->where('sucursal_id', $sucursalActiva);
                }
            })
            ->orderBy('fecha_vencimiento', 'asc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Lotes/Index', [
            'lotes' => $lotes,
            'sucursales' => $sucursales,
            'filtroSucursal' => $filtroSucursal,
        ]);
    }
}