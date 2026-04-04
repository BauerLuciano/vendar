<?php

namespace App\Http\Controllers;

use App\Models\CuentaCorriente;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Cálculo de Deuda Total en la calle
        // El ?? 0 es por si la tabla está vacía y devuelve null, para que no explote la vista
        $deudaTotal = CuentaCorriente::sum('saldo_deudor') ?? 0;

        // 2. Cálculo de Productos con Bajo Stock (Cruce de tablas)
        $productosBajoStock = DB::table('productos')
            ->join('branch_producto', 'productos.id', '=', 'branch_producto.producto_id')
            ->join('branches', 'branches.id', '=', 'branch_producto.branch_id')
            ->select(
                'productos.nombre as producto', 
                'productos.stock_minimo', 
                'branch_producto.cantidad_fisica', 
                'branches.name as sucursal'
            )
            ->whereRaw('branch_producto.cantidad_fisica <= productos.stock_minimo')
            ->get();

        // 3. Le mandamos la data servida a Vue
        return Inertia::render('Dashboard', [
            'deudaTotal' => (float) $deudaTotal,
            'productosBajoStock' => $productosBajoStock
        ]);
    }
}