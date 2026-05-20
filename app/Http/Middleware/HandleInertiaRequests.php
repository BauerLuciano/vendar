<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\Configuracion;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; 

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'branch_id' => $request->user()->branch_id,
                    'is_active' => $request->user()->is_active,
                    'plan_deseado' => $request->user()->plan_deseado,
                    'roles' => $request->user()->getRoleNames(),
                    'permissions' => $request->user()->getAllPermissions()->pluck('name'),
                ] : null,
                
                // 🔥 VARIABLE CLAVE PARA EL MODO DIOS: Le avisa a Vue si estamos en el local de un cliente
                'impersonating' => session()->has('admin_comercio_original_id'),

                // 🔒 MÓDULOS HABILITADOS: Trae qué funciones compró el dueño (tenant) de esta sucursal
                'modulos' => fn () => $request->user() && $request->user()->branch_id ? (function() use ($request) {
                    $sucursal = \App\Models\Sucursal::with('comercio')->find($request->user()->branch_id);
                    return $sucursal && $sucursal->comercio 
                        ? ($sucursal->comercio->modulos_habilitados ?? ['pos' => true]) 
                        : ['pos' => true];
                })() : ['pos' => true],
                
                // 🔔 CENTRO DE NOTIFICACIONES: Total + Top 5 Críticos
                'alertas' => fn () => $request->user() ? (function() use ($request) {
                    $query = DB::table('producto_sucursal')
                        ->join('productos', 'productos.id', '=', 'producto_sucursal.producto_id')
                        ->join('sucursales', 'sucursales.id', '=', 'producto_sucursal.sucursal_id')
                        ->where('productos.estado', true)
                        ->whereRaw('producto_sucursal.cantidad_fisica <= productos.stock_minimo');
                        
                    // Filtro por sucursal si no es jefe
                    if (!$request->user()->hasRole(['SuperAdmin', 'Administrador Global']) && $request->user()->branch_id) {
                        $query->where('producto_sucursal.sucursal_id', $request->user()->branch_id);
                    }

                    return [
                        'total' => (int) $query->count(),
                        'detalle' => $query->select(
                                'productos.nombre as producto', 
                                'sucursales.nombre as sucursal', 
                                'producto_sucursal.cantidad_fisica', 
                                'productos.stock_minimo', 
                                'productos.unidad_medida'
                            )
                            // Ordenamos para que los que tienen stock negativo o cero salgan primero
                            ->orderBy('producto_sucursal.cantidad_fisica', 'asc')
                            ->take(5)
                            ->get()
                    ];
                })() : ['total' => 0, 'detalle' => []],
            ],

            'empresa' => fn () => Schema::hasTable('configuraciones') 
                            ? Configuracion::pluck('valor', 'clave')->toArray() 
                            : [],

            'flash' => [
                'exito' => fn () => $request->session()->get('exito'),
                'error' => fn () => $request->session()->get('error'),
                'success' => fn () => $request->session()->get('success'),
                'venta_id' => fn () => $request->session()->get('venta_id'),
                'alertas_inflacion' => fn () => $request->session()->get('alertas_inflacion'), 
            ],
        ];
    }
}