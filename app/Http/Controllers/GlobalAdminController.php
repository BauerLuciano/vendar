<?php

namespace App\Http\Controllers;

use App\Models\Comercio;
use App\Models\Sucursal; 
use App\Models\User;
use App\Models\Plan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class GlobalAdminController extends Controller
{
    public function index()
    {
        return Inertia::render('AdminGlobal/Comercios/Index', [
            'comercios' => Comercio::with('plan')->get(),
            'planes' => Plan::orderBy('orden')->orderBy('precio_mensual')->get(['id', 'nombre', 'slug']),
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
            'plan_id' => 'required|exists:planes,id',
            'status' => 'required|in:activo,suspendido,trial',
            'limite_sucursales' => 'required|integer|min:1',
            'limite_usuarios' => 'nullable|integer|min:0',
            'vencimiento_pago' => 'nullable|date',
            'modulos_habilitados' => 'nullable|array',
        ]);

        if (empty($validated['modulos_habilitados'])) {
            $plan = Plan::find($validated['plan_id']);
            $validated['modulos_habilitados'] = $plan ? $plan->modulos : ['pos' => true];
        }
        
        $validated['slug'] = Str::slug($request->nombre);

        $comercio = new Comercio();
        $comercio->nombre = $validated['nombre'];
        $comercio->slug = $validated['slug'];
        $comercio->plan_id = $validated['plan_id'];
        $comercio->status = $validated['status'];
        $comercio->limite_sucursales = $validated['limite_sucursales'];
        $comercio->limite_usuarios = $validated['limite_usuarios'] ?? null;
        $comercio->vencimiento_pago = $validated['vencimiento_pago'] ?? null;
        $comercio->modulos_habilitados = $validated['modulos_habilitados'];
        $comercio->save();

        Sucursal::create([
            'comercio_id' => $comercio->id,
            'nombre'      => 'Casa Central',
            'direccion'   => 'Dirección a definir',
            'latitud'     => -27.367, 
            'longitud'    => -55.896,
            'estado'      => true,
        ]);

        return redirect()->back()->with('exito', 'Comercio y sucursal base registrados con éxito.');
    }


    public function update(Request $request, Comercio $comercio)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'plan_id' => 'required|exists:planes,id',
            'status' => 'required|in:activo,suspendido,trial',
            'limite_sucursales' => 'required|integer|min:1',
            'limite_usuarios' => 'nullable|integer|min:0',
            'vencimiento_pago' => 'nullable|date',
            'modulos_habilitados' => 'required|array',
        ]);

        $comercio->nombre = $validated['nombre'];
        $comercio->slug = Str::slug($request->nombre);
        $comercio->plan_id = $validated['plan_id'];
        $comercio->status = $validated['status'];
        $comercio->limite_sucursales = $validated['limite_sucursales'];
        $comercio->limite_usuarios = $validated['limite_usuarios'] ?? null;
        $comercio->vencimiento_pago = $validated['vencimiento_pago'] ?? null;
        $comercio->modulos_habilitados = $validated['modulos_habilitados'];
        $comercio->save();

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

        // 2. Cálculo dinámico de MRR desde planes en DB
        $mrr = Comercio::where('status', 'activo')
            ->join('planes', 'comercios.plan_id', '=', 'planes.id')
            ->sum('planes.precio_mensual');

        // 3. Total de sucursales operando en la nube
        $totalSucursales = Sucursal::count();

        // 4. Total de cuentas de usuario creadas
        $totalUsuarios = User::count();

        // 5. Últimos 5 comercios registrados para la tabla rápida de monitoreo
        $ultimosComercios = Comercio::with('plan')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'plan' => $c->plan?->nombre ?? $c->plan ?? 'N/A',
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

        $comercios = Comercio::select('id', 'nombre', 'plan', 'plan_id', 'status', 'vencimiento_pago')
            ->with('plan:id,precio_mensual,nombre as plan_nombre,slug')
            ->orderBy('vencimiento_pago', 'asc')
            ->get()
            ->map(function ($c) use ($hoy) {
                $monto = $c->plan?->precio_mensual ?? 0;

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
                    'plan'             => $c->plan?->nombre ?? strtoupper($c->plan),
                    'slug'             => $c->plan?->slug ?? $c->plan,
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

    /**
     * Lista las solicitudes de comercios pendientes de aprobación
     */
    public function solicitudesPendientes()
    {
        $solicitudes = User::where('is_active', false)
            ->whereNotNull('plan_deseado')
            ->with('roles')
            ->latest()
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'nombre' => $u->name,
                    'email' => $u->email,
                    'plan_deseado' => $u->plan_deseado,
                    'fecha_registro' => $u->created_at ? $u->created_at->format('d/m/Y H:i') : 'N/A',
                ];
            });

        return Inertia::render('AdminGlobal/Solicitudes', [
            'solicitudes' => $solicitudes,
        ]);
    }

    /**
     * Aprueba una solicitud: activa el usuario, crea comercio + sucursal base
     */
    public function aprobarSolicitud(Request $request, User $user)
    {
        $validated = $request->validate([
            'nombre_comercio' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($user, $validated) {
            // 1. Activar usuario y asignar rol de SuperAdmin (dueño de comercio)
            $user->is_active = true;
        $user->save();
            $user->syncRoles(['SuperAdmin']);

            // 2. Buscar plan en DB
            $planSlug = match ($user->plan_deseado) {
                'Plan Básico' => 'basico',
                'Plan Estándar' => 'pro',
                'Plan Premium' => 'premium',
                default => 'basico',
            };
            $plan = Plan::where('slug', $planSlug)->first();

            // 3. Crear Comercio
            $comercio = new Comercio();
            $comercio->nombre = $validated['nombre_comercio'];
            $comercio->slug = Str::slug($validated['nombre_comercio']);
            $comercio->plan = $plan?->slug ?? 'basico';
            $comercio->plan_id = $plan?->id;
            $comercio->status = 'trial';
            $comercio->limite_sucursales = $plan?->sucursales_limit ?? 1;
            $comercio->limite_usuarios = $plan?->usuarios_limit ?? 1;
            $comercio->modulos_habilitados = $plan?->modulos ?? ['pos' => true];
            $comercio->save();

            // 4. Crear Sucursal base
            Sucursal::create([
                'comercio_id' => $comercio->id,
                'nombre' => 'Casa Central',
                'direccion' => 'Dirección a definir',
                'latitud' => -27.367,
                'longitud' => -55.896,
                'estado' => true,
            ]);

            // 5. Vincular usuario a la sucursal y al comercio
            $user->update([
                'comercio_id' => $comercio->id,
                'branch_id' => Sucursal::where('comercio_id', $comercio->id)->first()->id,
            ]);
        });

        return redirect()->back()->with('exito', "Solicitud de {$user->name} aprobada. Comercio y sucursal creados.");
    }

    /**
     * Rechaza una solicitud (elimina el usuario pendiente)
     */
    public function rechazarSolicitud(User $user)
    {
        $nombre = $user->name;
        $user->delete();

        return redirect()->back()->with('exito', "Solicitud de {$nombre} rechazada.");
    }

    public function marcarPagado(Request $request, Comercio $comercio)
    {
        $request->validate(['fecha' => 'nullable|date']);

        $comercio->update([
            'vencimiento_pago' => $request->fecha ?? now()->addMonth(),
        ]);

        return redirect()->back()->with('exito', "{$comercio->nombre} marcado como al día.");
    }

    public function generarLinkMP(Comercio $comercio)
    {
        return response()->json([
            'message' => 'Funcionalidad próxima a implementarse.',
            'preview' => route('admin.facturacion'),
        ]);
    }
}