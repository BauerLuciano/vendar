<?php

namespace App\Http\Controllers;

use App\Models\CuentaCorriente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $user->load('branch'); // Cargamos la info de la sucursal del usuario
        $esJefe = $user->hasRole(['SuperAdmin', 'Administrador Global']);

        // 1. Cálculo de Deuda Total en la calle
        $deudaTotal = CuentaCorriente::sum('saldo_deudor') ?? 0;

        // 2. Ventas de Hoy (Recaudación del día)
        // NOTA: Si tu tabla 'ventas' tiene otra columna en vez de 'total', cambiala acá.
        $ventasHoy = DB::table('ventas')
            ->whereDate('created_at', Carbon::today())
            ->sum('total') ?? 0;

        // 3. Cajas Activas (Turnos que aún no se cerraron)
        $cajasActivas = DB::table('turno_cajas')
            ->whereNull('monto_cierre') // Asumimos que si no tiene monto de cierre, está abierta
            ->count();

        // 4. Cálculo de Productos con Bajo Stock (Con Filtro SaaS)
        $queryStock = DB::table('productos')
            ->join('producto_sucursal', 'productos.id', '=', 'producto_sucursal.producto_id')
            ->join('sucursales', 'sucursales.id', '=', 'producto_sucursal.sucursal_id')
            ->select(
                'productos.nombre as producto',
                'productos.stock_minimo',
                'producto_sucursal.cantidad_fisica as cantidad_fisica',
                'sucursales.nombre as sucursal'
            )
            ->whereRaw('producto_sucursal.cantidad_fisica <= productos.stock_minimo');

        // Si NO es jefe, solo ve las alertas de su propia sucursal
        if (!$esJefe && $user->branch_id) {
            $queryStock->where('producto_sucursal.sucursal_id', $user->branch_id);
        }

        $productosBajoStock = $queryStock->get();

        // 5. Mandamos todo a Vue
        return Inertia::render('Dashboard', [
            'deudaTotal' => (float) $deudaTotal,
            'ventasHoy' => (float) $ventasHoy,
            'cajasActivas' => $cajasActivas,
            'productosBajoStock' => $productosBajoStock,
            'esJefe' => $esJefe,
            'sucursalUsuario' => $user->branch ? $user->branch->nombre : 'Sede Central'
        ]);
    }
}