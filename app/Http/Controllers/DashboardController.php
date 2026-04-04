<?php

namespace App\Http\Controllers;

use App\Models\CuentaCorriente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Cálculo de Deuda Total en la calle
        $deudaTotal = CuentaCorriente::sum('saldo_deudor') ?? 0;

        // 2. Cálculo de Productos con Bajo Stock (Cruce de tablas ACTUALIZADO)
        $productosBajoStock = DB::table('productos')
            ->join('producto_sucursal', 'productos.id', '=', 'producto_sucursal.producto_id')
            ->join('sucursales', 'sucursales.id', '=', 'producto_sucursal.sucursal_id')
            ->select(
                'productos.nombre as producto',
                'productos.stock_minimo',
                // ¡ACÁ ESTÁ LA CLAVE! Le forzamos el alias para que Vue lo encuentre.
                'producto_sucursal.cantidad_fisica as cantidad_fisica', 
                'sucursales.nombre as sucursal' 
            )
            ->whereRaw('producto_sucursal.cantidad_fisica <= productos.stock_minimo')
            ->get();

        // 3. Le mandamos la data servida a Vue
        return Inertia::render('Dashboard', [
            'deudaTotal' => (float) $deudaTotal,
            'productosBajoStock' => $productosBajoStock
        ]);
    }
}