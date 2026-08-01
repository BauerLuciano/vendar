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
                'user' => $request->user() ? (method_exists($request->user(), 'getRoleNames') ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'branch_id' => $request->user()->branch_id,
                    'sucursal_activa_id' => session('sucursal_activa_id', $request->user()->branch_id),
                    'is_active' => $request->user()->is_active,
                    'plan_deseado' => $request->user()->plan_deseado,
                    'roles' => $request->user()->getRoleNames(),
                    'permissions' => $request->user()->getAllPermissions()->pluck('name'),
                ] : [
                    'id' => $request->user()->id,
                    'name' => $request->user()->nombre . ' ' . $request->user()->apellido,
                    'email' => $request->user()->email,
                ]) : null,
                
                // 🔥 VARIABLE CLAVE PARA EL MODO DIOS: Le avisa a Vue si estamos en el local de un cliente
                'impersonating' => session()->has('admin_comercio_original_id'),

                // 🔒 MÓDULOS HABILITADOS: Trae qué funciones compró el dueño (tenant) de esta sucursal
                'modulos' => fn () => $request->user() && $request->user()->branch_id ? (function() use ($request) {
                    $sucursalId = session('sucursal_activa_id', $request->user()->branch_id);
                    $sucursal = \App\Models\Sucursal::with('comercio')->find($sucursalId);
                    return $sucursal && $sucursal->comercio 
                        ? ($sucursal->comercio->modulos_habilitados ?? ['pos' => true]) 
                        : ['pos' => true];
                })() : ['pos' => true],
                
                // 🔔 CENTRO DE NOTIFICACIONES: Stock crítico + Lotes por vencer
                'alertas' => fn () => $request->user() ? (function() use ($request) {
                    $sucursalId = session('sucursal_activa_id', $request->user()->branch_id);
                    $esJefe = method_exists($request->user(), 'hasRole') && $request->user()->hasRole(['SuperAdmin', 'Administrador Global']);

                    // Stock crítico
                    $queryStock = DB::table('producto_sucursal')
                        ->join('productos', 'productos.id', '=', 'producto_sucursal.producto_id')
                        ->join('sucursales', 'sucursales.id', '=', 'producto_sucursal.sucursal_id')
                        ->where('productos.estado', true)
                        ->whereRaw('producto_sucursal.cantidad_fisica <= productos.stock_minimo');

                    if (!$esJefe && $sucursalId) {
                        $queryStock->where('producto_sucursal.sucursal_id', $sucursalId);
                    }

                    $stockCritico = [
                        'total' => (int) $queryStock->count(),
                        'detalle' => $queryStock->select(
                                'productos.nombre as producto',
                                'sucursales.nombre as sucursal',
                                'producto_sucursal.cantidad_fisica',
                                'productos.stock_minimo',
                                'productos.unidad_medida'
                            )
                            ->orderBy('producto_sucursal.cantidad_fisica', 'asc')
                            ->take(5)
                            ->get(),
                    ];

                    // Lotes próximos a vencer (próximos 30 días)
                    $queryLotes = DB::table('lotes')
                        ->join('productos', 'productos.id', '=', 'lotes.producto_id')
                        ->join('sucursales', 'sucursales.id', '=', 'lotes.sucursal_id')
                        ->where('lotes.stock_actual', '>', 0)
                        ->where('productos.estado', true)
                        ->where('lotes.fecha_vencimiento', '>=', now())
                        ->where('lotes.fecha_vencimiento', '<=', now()->addDays(30));

                    if (!$esJefe && $sucursalId) {
                        $queryLotes->where('lotes.sucursal_id', $sucursalId);
                    }

                    $lotesData = $queryLotes->orderBy('lotes.fecha_vencimiento', 'asc')
                        ->take(5)
                        ->select(
                            'productos.nombre as producto',
                            'sucursales.nombre as sucursal',
                            'lotes.fecha_vencimiento',
                            'lotes.stock_actual'
                        )
                        ->get()
                        ->map(fn ($l) => [
                            'producto' => $l->producto,
                            'sucursal' => $l->sucursal,
                            'fecha_vencimiento' => $l->fecha_vencimiento,
                            'stock_actual' => (float) $l->stock_actual,
                            'dias_restantes' => now()->diffInDays(\Carbon\Carbon::parse($l->fecha_vencimiento), false),
                        ]);

                    $lotesPorVencer = [
                        'total' => $lotesData->count(),
                        'detalle' => $lotesData,
                    ];

                    return [
                        'total' => $stockCritico['total'] + $lotesPorVencer['total'],
                        'stock_critico' => $stockCritico,
                        'lotes_por_vencer' => $lotesPorVencer,
                    ];
                })() : ['total' => 0, 'stock_critico' => ['total' => 0, 'detalle' => []], 'lotes_por_vencer' => ['total' => 0, 'detalle' => []]],
            ],

            'empresa' => fn () => Schema::hasTable('configuraciones') 
                            ? (function() {
                                $config = Configuracion::pluck('valor', 'clave')->toArray();
                                $config['permitir_stock_negativo'] = $config['permitir_stock_negativo'] ?? '0';
                                $config['moneda'] = $config['moneda'] ?? 'ARS';
                                return $config;
                            })()
                            : ['permitir_stock_negativo' => '0', 'moneda' => 'ARS'],

            'csrf_token' => fn () => csrf_token(),

            'onboarding' => fn () => $request->has('onboarding') && $request->user()
                ? app(\App\Services\OnboardingService::class)->pasoPorId($request->query('onboarding'))
                : null,

            'sucursal_activa' => fn () => $request->user() ? (function() use ($request) {
                $id = session('sucursal_activa_id', $request->user()->branch_id);
                if (!$id) return null;
                $suc = \App\Models\Sucursal::find($id);
                return $suc ? ['id' => $suc->id, 'nombre' => $suc->nombre] : null;
            })() : null,

            'flash' => [
                'exito' => fn () => $request->session()->get('exito'),
                'error' => fn () => $request->session()->get('error'),
                'success' => fn () => $request->session()->get('success'),
                'venta_id' => fn () => $request->session()->get('venta_id'),
                'es_pendiente' => fn () => $request->session()->get('es_pendiente'),
                'display_info' => fn () => $request->session()->get('display_info'),
                'alertas_inflacion' => fn () => $request->session()->get('alertas_inflacion'), 
            ],
        ];
    }
}