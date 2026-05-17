<?php

namespace App\Http\Controllers;

use App\Models\Comercio;
use App\Models\Sucursal; 
use App\Models\User;    
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class GlobalAdminController extends Controller
{
    public function index()
    {
        return Inertia::render('AdminGlobal/Comercios/Index', [
            'comercios' => Comercio::all(),
            'modulosDisponibles' => [
                ['id' => 'pos', 'nombre' => 'Punto de Venta Base'],
                ['id' => 'lotes', 'nombre' => 'Gestión de Stock Avanzada (Lotes)'],
                ['id' => 'fiados', 'nombre' => 'Cuentas Corrientes (Fiados)'],
                ['id' => 'proveedores', 'nombre' => 'Gestión de Proveedores'],
                ['id' => 'auditoria', 'nombre' => 'Auditoría de Caja y Stock'],
                ['id' => 'transferencias', 'nombre' => 'Optimización de Stock (Sugerencias)'],
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'plan' => 'required|in:basico,pro,premium',
            'status' => 'required|in:activo,suspendido,trial',
            'limite_sucursales' => 'required|integer|min:1',
            'vencimiento_pago' => 'nullable|date',
            'modulos_habilitados' => 'nullable|array',
        ]);

        if (empty($validated['modulos_habilitados'])) {
            $validated['modulos_habilitados'] = ['pos' => true];
        }
        
        $validated['slug'] = Str::slug($request->nombre);

        $comercio = Comercio::create($validated);

        Sucursal::create([
            'comercio_id' => $comercio->id,
            'nombre'      => 'Casa Central',
            'direccion'   => 'Dirección a definir',
            'latitud'     => -27.367, 
            'longitud'    => -55.896,
            'estado'      => true, // Activa por defecto
        ]);

        return redirect()->back()->with('exito', 'Comercio y sucursal base registrados con éxito.');
    }


    public function update(Request $request, Comercio $comercio)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'plan' => 'required|in:basico,pro,premium',
            'status' => 'required|in:activo,suspendido,trial',
            'limite_sucursales' => 'required|integer|min:1',
            'vencimiento_pago' => 'nullable|date',
            'modulos_habilitados' => 'required|array',
        ]);

        $validated['slug'] = Str::slug($request->nombre);

        $comercio->update($validated);

        return redirect()->back()->with('exito', 'Configuración del comercio actualizada.');
    }

    /**
     * Muestra el panel de Métricas Globales estratégicas de VendAR
     */
    public function metricas()
    {
        // 1. Comercios totales y activos según tu campo 'status'
        $totalComercios = Comercio::count();
        $comerciosActivos = Comercio::where('status', 'activo')->count();

        // 2. Cálculo dinámico de MRR (Ingreso Recurrente Mensual Estimado)
        // Basado en los planes activos y los precios definidos en tu modelo de negocio
        $comerciosPorPlan = Comercio::where('status', 'activo')
            ->selectRaw('plan, count(*) as total')
            ->groupBy('plan')
            ->pluck('total', 'plan');

        // Estimación: Básico ($8.000), Pro ($15.000), Premium ($35.000)
        $mrr = (($comerciosPorPlan['basico'] ?? 0) * 8000) +
               (($comerciosPorPlan['pro'] ?? 0) * 15000) +
               (($comerciosPorPlan['premium'] ?? 0) * 35000);

        // 3. Total de sucursales operando en la nube
        $totalSucursales = Sucursal::count();

        // 4. Total de cuentas de usuario creadas
        $totalUsuarios = User::count();

        // 5. Últimos 5 comercios registrados para la tabla rápida de monitoreo
        $ultimosComercios = Comercio::latest()
            ->take(5)
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'plan' => strtoupper($c->plan),
                    'fecha' => $c->created_at ? $c->created_at->format('d/m/Y') : 'N/A',
                    'estado' => ucfirst($c->status)
                ];
            });

        return Inertia::render('AdminGlobal/Metricas', [
            'kpis' => [
                'comercios_activos' => $comerciosActivos,
                'comercios_totales' => $totalComercios,
                'mrr_estimado'      => $mrr,
                'sucursales_nube'   => $totalSucursales,
                'usuarios_totales'  => $totalUsuarios,
            ],
            'ultimosComercios' => $ultimosComercios
        ]);
    }

    public function facturacion()
    {
        $hoy = now();

        // Traemos los comercios con datos clave para la facturación
        $comercios = Comercio::select('id', 'nombre', 'plan', 'status', 'vencimiento_pago')
            ->orderBy('vencimiento_pago', 'asc')
            ->get()
            ->map(function ($c) use ($hoy) {
                // Precios de referencia según tu plan
                $precios = ['basico' => 8000, 'pro' => 15000, 'premium' => 35000];
                $monto = $precios[strtolower($c->plan)] ?? 0;

                // Determinamos el estado financiero real
                $estadoFinanciero = 'Al Día';
                if ($c->status === 'suspendido') {
                    $estadoFinanciero = 'Suspendido';
                } elseif ($c->vencimiento_pago && \Carbon\Carbon::parse($c->vencimiento_pago)->isBefore($hoy)) {
                    $estadoFinanciero = 'Vencido';
                }

                return [
                    'id'               => $c->id,
                    'nombre'           => $c->nombre,
                    'plan'             => strtoupper($c->plan),
                    'monto'            => $monto,
                    'vencimiento'      => $c->vencimiento_pago ? \Carbon\Carbon::parse($c->vencimiento_pago)->format('d/m/Y') : 'Sin fecha',
                    'estado_cobro'     => $estadoFinanciero,
                    'dias_restantes'   => $c->vencimiento_pago ? \Carbon\Carbon::parse($c->vencimiento_pago)->diffInDays($hoy, false) : 0,
                ];
            });

        // Cálculos rápidos para tus tarjetas de métricas financieras
        $totalEsperado = $comercios->whereIn('estado_cobro', ['Al Día', 'Vencido'])->sum('monto');
        $totalVencido = $comercios->where('estado_cobro', 'Vencido')->sum('monto');
        $clientesMorosos = $comercios->where('estado_cobro', 'Vencido')->count();

        return Inertia::render('AdminGlobal/Facturacion', [
            'comercios' => $comercios,
            'resumen' => [
                'total_esperado'   => $totalEsperado,
                'total_vencido'    => $totalVencido,
                'clientes_morosos' => $clientesMorosos,
            ]
        ]);
    }
}