<?php

namespace App\Http\Controllers;

use App\Enums\MetodoPago;
use App\Enums\PaymentChannel;
use App\Models\Caja;
use App\Models\TurnoCaja;
use App\Models\MovimientoCaja;
use App\Models\Producto;
use App\Models\Consumidor;
use App\Models\DetalleVenta;
use App\Models\VentaPendiente;
use App\Models\PaymentMethodConfiguration;
use App\Models\RecargoTarjeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Services\Promotion\PromotionEngineService;
use App\Services\Promotion\PromotionConflictResolver;

class PosController extends Controller
{
    public function __construct(
        private readonly PromotionEngineService $engine,
        private readonly PromotionConflictResolver $conflictResolver,
    ) {}

    // Esta es la puerta de entrada al POS
    public function index(Request $request)
    {
        $user = auth()->user();

        $turnoAbierto = TurnoCaja::where('user_id', $user->id)
            ->where('estado', 'Abierto')
            ->first();

        if ($turnoAbierto) {
            $sucursalId = session('sucursal_activa_id', $user->branch_id);
            if (!$sucursalId) {
                return redirect()->back()->withErrors(['error' => 'No tenés una sucursal asignada.']);
            }

            $comercioId = $user->branch?->comercio_id;

            $totalProductos = Producto::where('estado', true)
                ->whereHas('sucursales', fn ($q) => $q->where('sucursal_id', $sucursalId))
                ->count();

            $productos = $this->cargarProductosSucursal($sucursalId, 300);
            $productosFrecuentes = $this->cargarProductosFrecuentes($sucursalId);

            $clientesActivos = Consumidor::with('cuentaCorriente')
                ->where('estado', true)
                ->when($comercioId, fn ($q) => $q->where('comercio_id', $comercioId))
                ->limit(50)
                ->get();

            $paymentMethods = PaymentMethodConfiguration::where('comercio_id', $comercioId)
                ->where('enabled', true)
                ->where('channel', PaymentChannel::MANUAL)
                ->get()
                ->map(function ($pm) {
                    $baseLabel = MetodoPago::from($pm->metodo_pago)->label();
                    return [
                        'id' => $pm->id,
                        'metodo_pago' => $pm->metodo_pago,
                        'label' => $pm->provider ? $pm->provider : $baseLabel,
                        'provider' => $pm->provider,
                        'display_data' => $pm->display_data,
                    ];
                });

            $metodosBase = collect([
                MetodoPago::EFECTIVO,
                MetodoPago::DEBITO,
                MetodoPago::CREDITO,
                MetodoPago::CUENTA_CORRIENTE,
            ])->map(fn ($m) => [
                'value' => $m->value,
                'label' => $m->label(),
            ]);

            $recargos = RecargoTarjeta::where('comercio_id', $comercioId)
                ->where('enabled', true)
                ->get()
                ->groupBy('banco')
                ->map(fn ($group) => $group->keyBy(fn ($r) => $r->tipo_tarjeta . '_' . $r->cuotas));

            $bancosDisponibles = RecargoTarjeta::where('comercio_id', $comercioId)
                ->where('enabled', true)
                ->distinct()
                ->pluck('banco')
                ->sort()
                ->values();

            return Inertia::render('Pos/Terminal', [
                'turno' => $turnoAbierto->load('caja.sucursal'),
                'productos' => $productos,
                'clientes' => $clientesActivos,
                'totalProductos' => $totalProductos,
                'frecuentes' => $productosFrecuentes,
                'paymentMethods' => $paymentMethods,
                'metodosBase' => $metodosBase,
                'recargos' => $recargos,
                'bancosDisponibles' => $bancosDisponibles,
            ]);
        }

        $sucursalId = session('sucursal_activa_id', $user->branch_id);
        if (!$sucursalId) {
            return redirect()->back()->withErrors(['error' => 'No tenés una sucursal asignada.']);
        }

        $cajasDisponibles = Caja::where('sucursal_id', $sucursalId)
            ->where('estado', true)
            ->get();

        return Inertia::render('Pos/AperturaTurno', [
            'cajas' => $cajasDisponibles
        ]);
    }

    // Este método procesa el formulario de "Abrir Caja"
    public function abrirTurno(Request $request)
    {
        $request->validate([
            'caja_id' => 'required|exists:cajas,id',
            'saldo_inicial' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($request) {
            $user = auth()->user();

            // Verificamos por seguridad que nadie más esté usando esa caja
            $cajaEnUso = TurnoCaja::where('caja_id', $request->caja_id)
                ->where('estado', 'Abierto')
                ->exists();

            if ($cajaEnUso) {
                return redirect()->back()->withErrors([
                    'caja_id' => 'Esta caja ya está siendo utilizada por otro cajero.'
                ]);
            }

            $cajaFisica = Caja::findOrFail($request->caja_id);

            $turno = TurnoCaja::create([
                'caja_id'        => $request->caja_id,
                'user_id'        => $user->id,
                'sucursal_id'    => $cajaFisica->sucursal_id,
                'saldo_inicial'  => $request->saldo_inicial,
                'monto_apertura' => $request->saldo_inicial,
                'fecha_apertura' => Carbon::now(),
                'estado'         => 'Abierto',
            ]);

            MovimientoCaja::create([
                'turno_caja_id' => $turno->id,
                'tipo'          => 'INGRESO',
                'concepto'      => 'FONDO_INICIAL',
                'metodo_pago'   => MetodoPago::EFECTIVO->value,
                'monto'         => $request->saldo_inicial,
                'descripcion'   => 'Apertura de caja (Fondo Efectivo)',
            ]);

            return redirect()->route('pos.index')->with('success', 'Turno abierto correctamente. ¡Buenas ventas!');
        });
    }

    private function cargarProductosFrecuentes(int $sucursalId, int $limit = 12)
    {
        $topIds = DetalleVenta::select('producto_id', DB::raw('COUNT(*) as total'))
            ->whereHas('venta', function ($q) use ($sucursalId) {
                $q->where('estado', 'Completada')
                  ->whereHas('turno', fn ($q) => $q->where('sucursal_id', $sucursalId));
            })
            ->groupBy('producto_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('producto_id');

        if ($topIds->isEmpty()) {
            return collect();
        }

        return $this->cargarProductosSucursal($sucursalId, $limit, $topIds->toArray());
    }

    private function cargarProductosSucursal(int $sucursalId, int $limit = 50, ?array $ids = null)
    {
        $query = Producto::where('estado', true)
            ->whereHas('sucursales', fn ($q) => $q->where('sucursal_id', $sucursalId));

        if ($ids !== null) {
            $query->whereIn('id', $ids)->orderByRaw('array_position(ARRAY[' . implode(',', $ids) . ']::bigint[], id)');
        }

        $query->select('id', 'nombre', 'codigo_barras', 'precio_venta', 'imagen', 'unidad_medida')
            ->with([
                'sucursales' => function($q) use ($sucursalId) {
                    $q->where('sucursal_id', $sucursalId);
                },
                'reglaLiquidacion',
                'lotes' => function($q) use ($sucursalId) {
                    $q->where('sucursal_id', $sucursalId)
                      ->where('estado_liquidacion', true)
                      ->where('stock_actual', '>', 0);
                }
            ]);

        if ($ids === null) {
            $query->orderBy('nombre');
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->map(function($p) {
            $pivot = $p->sucursales->first();
            $p->stock_actual = $pivot ? (float)$pivot->pivot->cantidad_fisica : 0;

            $loteEnLiquidacion = $p->lotes->isNotEmpty();

            $p->en_liquidacion = false;
            $p->porcentaje_descuento = 0;
            $p->precio_rebajado = $p->precio_venta;

            if ($loteEnLiquidacion && $p->reglaLiquidacion && $p->reglaLiquidacion->estado) {
                $p->en_liquidacion = true;
                $p->porcentaje_descuento = (float) $p->reglaLiquidacion->porcentaje_descuento;
                $descuento = $p->precio_venta * ($p->porcentaje_descuento / 100);
                $p->precio_rebajado = round($p->precio_venta - $descuento, 2);
            }

            unset($p->lotes);
            return $p;
        });
    }

    public function guardarCarrito(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:productos,id',
            'items.*.nombre' => 'required|string',
            'items.*.cantidad' => 'required|numeric|min:0.01',
            'items.*.precio_venta' => 'required|numeric|min:0',
            'consumidor_id' => 'nullable|exists:consumidores,id',
        ]);

        $user = auth()->user();
        $turno = TurnoCaja::where('user_id', $user->id)->where('estado', 'Abierto')->first();
        if (!$turno) {
            return response()->json(['error' => 'No hay turno abierto'], 400);
        }

        $total = collect($request->items)->sum(fn ($i) => $i['cantidad'] * $i['precio_venta']);

        $pendiente = VentaPendiente::create([
            'user_id' => $user->id,
            'turno_caja_id' => $turno->id,
            'consumidor_id' => $request->consumidor_id,
            'items' => $request->items,
            'total' => $total,
            'estado' => 'activa',
        ]);

        return response()->json(['id' => $pendiente->id, 'total' => $total]);
    }

    public function listarPendientes()
    {
        $user = auth()->user();
        $turno = TurnoCaja::where('user_id', $user->id)->where('estado', 'Abierto')->first();
        if (!$turno) {
            return response()->json([]);
        }

        $pendientes = VentaPendiente::where('turno_caja_id', $turno->id)
            ->where('estado', 'activa')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'total' => (float) $p->total,
                'items_count' => count($p->items ?? []),
                'items' => $p->items,
                'consumidor_id' => $p->consumidor_id,
                'created_at' => $p->created_at->format('H:i'),
            ]);

        return response()->json($pendientes);
    }

    public function recuperarCarrito(VentaPendiente $ventaPendiente)
    {
        $user = auth()->user();
        $turno = TurnoCaja::where('user_id', $user->id)->where('estado', 'Abierto')->first();
        if (!$turno || $ventaPendiente->turno_caja_id !== $turno->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        if ($ventaPendiente->estado !== 'activa') {
            return response()->json(['error' => 'La venta pendiente ya fue recuperada'], 400);
        }

        $ventaPendiente->update(['estado' => 'recuperada']);

        return response()->json([
            'items' => $ventaPendiente->items,
            'consumidor_id' => $ventaPendiente->consumidor_id,
            'total' => (float) $ventaPendiente->total,
        ]);
    }

    public function eliminarPendiente(VentaPendiente $ventaPendiente)
    {
        $user = auth()->user();
        $turno = TurnoCaja::where('user_id', $user->id)->where('estado', 'Abierto')->first();
        if (!$turno || $ventaPendiente->turno_caja_id !== $turno->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $ventaPendiente->update(['estado' => 'cancelada']);

        return response()->json(['success' => true]);
    }

    public function buscarProductos(Request $request)
    {
        $user = auth()->user();
        $sucursalId = session('sucursal_activa_id', $user->branch_id);
        if (!$sucursalId) {
            return response()->json([]);
        }

        $query = $request->get('q', '');
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $productos = Producto::where('estado', true)
            ->whereHas('sucursales', fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->select('id', 'nombre', 'codigo_barras', 'precio_venta', 'imagen', 'unidad_medida')
            ->where(function ($q) use ($query) {
                if (is_numeric($query)) {
                    $q->where('codigo_barras', 'LIKE', $query . '%')
                      ->orWhere('id', (int) $query);
                } else {
                    $q->where('nombre', 'ILIKE', '%' . $query . '%');
                }
            })
            ->with([
                'sucursales' => function($q) use ($sucursalId) {
                    $q->where('sucursal_id', $sucursalId);
                },
                'reglaLiquidacion',
                'lotes' => function($q) use ($sucursalId) {
                    $q->where('sucursal_id', $sucursalId)
                      ->where('estado_liquidacion', true)
                      ->where('stock_actual', '>', 0);
                }
            ])
            ->orderBy('nombre')
            ->limit(30)
            ->get()
            ->map(function($p) {
                $pivot = $p->sucursales->first();
                $p->stock_actual = $pivot ? (float)$pivot->pivot->cantidad_fisica : 0;
                $p->en_liquidacion = false;
                $p->porcentaje_descuento = 0;
                $p->precio_rebajado = $p->precio_venta;

                if ($p->reglaLiquidacion && $p->reglaLiquidacion->estado && $p->lotes->isNotEmpty()) {
                    $p->en_liquidacion = true;
                    $p->porcentaje_descuento = (float) $p->reglaLiquidacion->porcentaje_descuento;
                    $descuento = $p->precio_venta * ($p->porcentaje_descuento / 100);
                    $p->precio_rebajado = round($p->precio_venta - $descuento, 2);
                }

                unset($p->lotes, $p->reglaLiquidacion, $p->sucursales);
                return $p;
            });

        return response()->json($productos);
    }

    public function movimientosTurno(Request $request)
    {
        $user = auth()->user();
        $turno = TurnoCaja::where('user_id', $user->id)
            ->where('estado', 'Abierto')
            ->first();

        if (!$turno) {
            return response()->json([]);
        }

        $comercioId = $turno->caja?->sucursal?->comercio_id;
        $labelMap = $comercioId ? \App\Models\PaymentMethodConfiguration::labelMap($comercioId) : [];

        $movimientos = $turno->movimientos()
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'tipo' => $m->tipo,
                'concepto' => $m->concepto,
                'metodo_pago' => $m->metodo_pago,
                'metodo_pago_display' => $labelMap[$m->metodo_pago] ?? \App\Enums\MetodoPago::fromString($m->metodo_pago)->label(),
                'monto' => (float) $m->monto,
                'descripcion' => $m->descripcion,
                'created_at' => $m->created_at->format('H:i'),
            ]);

        return response()->json($movimientos);
    }

    public function buscarClientes(Request $request)
    {
        $user = auth()->user();
        $comercioId = $user->branch?->comercio_id;
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $clientes = Consumidor::with('cuentaCorriente')
            ->where('estado', true)
            ->when($comercioId, fn ($q) => $q->where('comercio_id', $comercioId))
            ->where(function ($q) use ($query) {
                $q->where('nombre', 'ILIKE', '%' . $query . '%')
                  ->orWhere('apellido', 'ILIKE', '%' . $query . '%')
                  ->orWhere('documento', 'LIKE', '%' . $query . '%');
            })
            ->orderBy('nombre')
            ->limit(20)
            ->get();

        return response()->json($clientes);
    }

    /**
     * Bulk price computation for POS cart items.
     * Returns effective unit prices including all active promotions
     * (percent, fixed_amount, fixed_price, 2x1, x_for_y).
     */
    public function precios(Request $request)
    {
        $request->validate([
            'items'   => 'required|array|min:1',
            'items.*' => 'array',
            'items.*.id'       => 'required|exists:productos,id',
            'items.*.cantidad' => 'required|numeric|min:1',
        ]);

        $user = auth()->user();
        $comercioId = $user->branch?->comercio_id;
        $sucursalId = session('sucursal_activa_id', $user->branch_id);

        $itemData = collect($request->items)->map(fn ($i) => [
            'id'       => (int) $i['id'],
            'cantidad' => max(1, (int) ($i['cantidad'] ?? 1)),
        ]);

        $productIds = $itemData->pluck('id')->unique()->values()->all();
        $qtyByProduct = $itemData->pluck('cantidad', 'id')->toArray();

        $productos = Producto::whereIn('id', $productIds)
            ->where('estado', true)
            ->whereHas('sucursales', fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->get();

        $results = $this->engine->forProducts($productos, $comercioId);

        $out = [];
        foreach ($results as $r) {
            $prod       = $r['producto'];
            $promoResult = $r['promotion_result'];
            $quantity   = $qtyByProduct[$prod->id] ?? 1;
            $basePrice  = (float) $prod->precio_venta;

            $effectiveUnit   = $basePrice;
            $discountApplied = 0.0;
            $discountType    = null;

            $best = $promoResult->bestPromotion;

            if ($best !== null && $best->promotion) {
                $dtype = $best->promotion->discountType;

                if (in_array($dtype, ['percent', 'fixed_amount', 'fixed_price'], true)) {
                    $effectiveUnit   = $best->finalPrice;
                    $discountApplied = $best->discountAmount;
                    $discountType    = $dtype;
                } elseif ($dtype === '2x1' || $dtype === 'x_for_y') {
                    $x = $dtype === '2x1' ? 2 : (int) ($best->promotion->discountConfig['x'] ?? 2);
                    $y = $dtype === '2x1' ? 1 : (int) ($best->promotion->discountConfig['y'] ?? 1);

                    if ($quantity >= $x && $x > 0 && $y > 0 && $y < $x) {
                        $groups  = intdiv($quantity, $x);
                        $remainder = $quantity % $x;
                        $effectiveUnit = ($groups * $y + $remainder) * $basePrice / $quantity;
                        $discountApplied = $basePrice - $effectiveUnit;
                        $discountType = $dtype;
                    }
                }
            }

            // Fallback: ConflictResolver skips 2x1/x_for_y in bestPromotion,
            // so check the promotions array directly for quantity-based discounts
            if ($discountType === null && !empty($promoResult->promotions)) {
                foreach ($promoResult->promotions as $pd) {
                    if (in_array($pd->discountType, ['2x1', 'x_for_y'], true)) {
                        $x = $pd->discountType === '2x1' ? 2 : (int) ($pd->discountConfig['x'] ?? 2);
                        $y = $pd->discountType === '2x1' ? 1 : (int) ($pd->discountConfig['y'] ?? 1);

                        if ($quantity >= $x && $x > 0 && $y > 0 && $y < $x) {
                            $groups  = intdiv($quantity, $x);
                            $remainder = $quantity % $x;
                            $effectiveUnit = ($groups * $y + $remainder) * $basePrice / $quantity;
                            $discountApplied = $basePrice - $effectiveUnit;
                            $discountType = $pd->discountType;
                        }
                        break;
                    }
                }
            }

            $out[] = [
                'id'                   => $prod->id,
                'precio_unitario'      => round($effectiveUnit, 2),
                'precio_original'      => $basePrice,
                'descuento_aplicado'   => round($discountApplied, 2),
                'tipo_descuento'       => $discountType,
                'cantidad'             => $quantity,
            ];
        }

        return response()->json(['items' => $out]);
    }
}