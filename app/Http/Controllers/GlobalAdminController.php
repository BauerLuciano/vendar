<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Exceptions\PagoManualInvalidoException;
use App\Models\Comercio;
use App\Models\Payment;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Plan;
use App\Services\Suscripcion\PagoManualService;
use App\Services\Suscripcion\SuscripcionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        $preciosConfirmados = (bool) config('vendar.facturacion.precios_confirmados', false);

        // 1. Comercios totales y activos según tu campo 'status'
        $totalComercios = Comercio::count();
        $comerciosActivos = Comercio::where('status', 'activo')->count();

        // 2. MRR: sólo se calcula si los precios están confirmados. Mientras
        //    no lo estén, devolver un número sería una proyección basada en
        //    importes que el negocio todavía no definió.
        $mrr = $preciosConfirmados
            ? (float) Comercio::where('status', 'activo')
                ->join('planes', 'comercios.plan_id', '=', 'planes.id')
                ->sum('planes.precio_mensual')
            : null;

        // 3. Total de sucursales operando en la nube
        $totalSucursales = Sucursal::count();

        // 4. Total de cuentas de usuario creadas
        $totalUsuarios = User::count();

        // 5. Últimos 5 comercios registrados para la tabla rápida de monitoreo.
        //    Se usa `planContratado` para no confundir la columna legacy
        //    `comercios.plan` con la relación al plan.
        $ultimosComercios = Comercio::with('planContratado')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'plan' => $c->planContratado?->nombre
                        ?? ($c->plan ? strtoupper($c->plan) : 'N/A'),
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
            'ultimosComercios' => $ultimosComercios,
            'precios_confirmados' => $preciosConfirmados,
        ]);
    }

    public function facturacion(Request $request)
    {
        $hoy = now()->startOfDay();
        $preciosConfirmados = (bool) config('vendar.facturacion.precios_confirmados', false);

        $comercios = Comercio::select('id', 'nombre', 'plan', 'plan_id', 'status', 'vencimiento_pago')
            ->with('planContratado:id,nombre,slug,precio_mensual')
            ->orderBy('vencimiento_pago', 'asc')
            ->get()
            ->map(function (Comercio $c) use ($hoy, $preciosConfirmados) {
                $plan = $c->planContratado;
                $vencimiento = $c->vencimiento_pago
                    ? Carbon::parse($c->vencimiento_pago)->startOfDay()
                    : null;

                $diasRestantes = $vencimiento
                    ? (int) $hoy->diffInDays($vencimiento, false)
                    : null;

                $suspendido = $c->status === 'suspendido';

                // El día del vencimiento todavía es un día con acceso: el
                // middleware corta al finalizar la jornada. Por eso `dias === 0`
                // no puede mostrarse como Vencido.
                $estadoCobro = match (true) {
                    $suspendido => 'Suspendido',
                    $vencimiento === null => 'Sin vencimiento',
                    $diasRestantes < 0 => 'Vencido',
                    $diasRestantes === 0 => 'Al Día',
                    $diasRestantes <= SuscripcionService::UMBRAL_AVISO_DIAS => 'Por Vencer',
                    default => 'Al Día',
                };

                $precioConfigurado = $plan?->precio_mensual !== null
                    ? (float) $plan->precio_mensual
                    : null;

                return [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'plan' => $plan?->nombre
                        ?? ($c->plan ? strtoupper($c->plan) : 'Sin plan'),
                    'slug' => $plan?->slug ?? $c->plan,
                    // Precio configurado en el plan, no tarifa comercial.
                    'precio_configurado' => $precioConfigurado,
                    'precio_es_referencia' => ! $preciosConfirmados,
                    'vencimiento' => $vencimiento?->format('d/m/Y'),
                    'estado_cobro' => $estadoCobro,
                    'dias_restantes' => $diasRestantes,
                    'dias_atraso' => $diasRestantes !== null && $diasRestantes < 0
                        ? abs($diasRestantes)
                        : 0,
                    'suspendido' => $suspendido,
                ];
            });

        $vencidos = $comercios->where('estado_cobro', 'Vencido')->count();
        $suspendidos = $comercios->where('estado_cobro', 'Suspendido')->count();

        $historial = $this->construirHistorial($request);

        return Inertia::render('AdminGlobal/Facturacion', [
            'comercios' => $comercios,
            'resumen' => [
                'total_comercios' => $comercios->count(),
                'al_dia' => $comercios->where('estado_cobro', 'Al Día')->count(),
                'por_vencer' => $comercios->where('estado_cobro', 'Por Vencer')->count(),
                'vencidos' => $vencidos,
                'suspendidos' => $suspendidos,
                'sin_vencimiento' => $comercios->where('estado_cobro', 'Sin vencimiento')->count(),
                // Los suspendidos siguen siendo deuda: no desaparecen de mora.
                'en_mora' => $vencidos + $suspendidos,
                'clientes_morosos' => $vencidos + $suspendidos,
            ],
            'historial' => $historial['pagos'],
            'historial_filtros' => $historial['filtros'],
            'comercios_filtro' => Comercio::orderBy('nombre')->get(['id', 'nombre']),
            'estados_pago' => array_map(fn (PaymentStatus $e) => $e->value, PaymentStatus::cases()),
            'precios_confirmados' => $preciosConfirmados,
            'cobros_habilitados' => (bool) config('vendar.facturacion.cobros_habilitados', false),
            'aviso_precios' => 'Los precios comerciales de VendAR todavía están pendientes de definición. El registro de cobros permanece deshabilitado.',
            'aviso_pagos_online' => 'Los pagos online estarán disponibles próximamente.',
        ]);
    }

    /**
     * Historial de pagos de suscripción con filtros por comercio, estado y
     * período. No inventa registros: si no hay pagos, devuelve una lista vacía.
     */
    public function historialPagos(Request $request)
    {
        $estados = array_map(fn (PaymentStatus $e) => $e->value, PaymentStatus::cases());

        $request->validate([
            'comercio_id' => 'nullable|integer|exists:comercios,id',
            'estado' => ['nullable', Rule::in($estados)],
            'periodo' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $historial = $this->construirHistorial($request);

        return response()->json([
            'pagos' => $historial['pagos'],
            'filtros' => $historial['filtros'],
            'total' => count($historial['pagos']),
        ]);
    }

    /**
     * @return array{pagos: array<int, array<string, mixed>>, filtros: array<string, mixed>}
     */
    private function construirHistorial(Request $request): array
    {
        $filtros = [
            'comercio_id' => $request->integer('comercio_id') ?: null,
            'estado' => $request->string('estado')->toString() ?: null,
            'periodo' => $request->string('periodo')->toString() ?: null,
        ];

        $query = Payment::query()
            ->where('payable_type', Comercio::class)
            ->with(['createdBy:id,name,email', 'payable:id,nombre'])
            ->orderByDesc('approved_at')
            ->orderByDesc('id');

        if ($filtros['comercio_id']) {
            $query->where('payable_id', $filtros['comercio_id']);
        }

        $estadosValidos = array_map(fn (PaymentStatus $e) => $e->value, PaymentStatus::cases());
        if ($filtros['estado'] && in_array($filtros['estado'], $estadosValidos, true)) {
            $query->where('status', $filtros['estado']);
        }

        if ($filtros['periodo'] && preg_match('/^\d{4}-\d{2}$/', $filtros['periodo'])) {
            $desde = Carbon::createFromFormat('Y-m', $filtros['periodo'])->startOfMonth();
            $hasta = $desde->copy()->endOfMonth();

            $query->where(function ($q) use ($desde, $hasta) {
                $q->whereBetween('period_start', [$desde, $hasta])
                    ->orWhereBetween('period_end', [$desde, $hasta]);
            });
        }

        $pagos = $query->limit(100)->get()->map(function (Payment $p) {
            return [
                'id' => $p->id,
                'comercio_id' => $p->payable_id,
                'comercio' => $p->payable?->nombre,
                'fecha' => $p->approved_at?->format('d/m/Y H:i')
                    ?? $p->created_at?->format('d/m/Y H:i'),
                'importe' => $p->amount !== null ? (float) $p->amount : null,
                'moneda' => $p->currency,
                'metodo' => $p->payment_method,
                'periodo_inicio' => $p->period_start?->format('d/m/Y'),
                'periodo_fin' => $p->period_end?->format('d/m/Y'),
                'estado' => $p->status instanceof PaymentStatus
                    ? $p->status->value
                    : (string) $p->status,
                'responsable' => $p->createdBy?->name,
                'referencia' => $p->reference,
            ];
        })->values()->all();

        return [
            'pagos' => $pagos,
            'filtros' => $filtros,
        ];
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

    public function marcarPagado(
        Request $request,
        Comercio $comercio,
        PagoManualService $pagoManualService,
    ) {
        // Mientras no haya precios comerciales definidos, ni siquiera el
        // Administrador Global puede registrar un cobro. La acción se rechaza
        // en backend y la interfaz no muestra el botón.
        if (! $pagoManualService->estaHabilitado()) {
            abort(403, 'Los precios comerciales todavía están pendientes de definición. El registro de cobros permanece deshabilitado.');
        }

        // La fecha de vencimiento NO se acepta desde el formulario. Sólo se
        // registra la fecha en que se recibió el pago; el período lo calcula
        // el servicio a partir del vencimiento vigente.
        $validated = $request->validate([
            'importe' => 'required|numeric|min:0',
            'payment_method' => ['required', 'string', Rule::in(PagoManualService::METODOS_VALIDOS)],
            'referencia' => 'nullable|string|max:255',
            'idempotency_key' => 'nullable|string|max:191',
            'fecha_pago' => 'nullable|date',
        ]);

        try {
            $pago = $pagoManualService->registrar($comercio, $validated, $request->user());
        } catch (PagoManualInvalidoException $e) {
            return redirect()->back()->withErrors(['pago' => $e->getMessage()]);
        }

        return redirect()->back()->with(
            'exito',
            "Pago #{$pago->id} registrado para {$comercio->nombre}."
        );
    }

    public function generarLinkMP(Comercio $comercio)
    {
        return response()->json([
            'disponible' => false,
            'cobro_generado' => false,
            'message' => 'Los pagos online estarán disponibles próximamente.',
        ]);
    }
}
