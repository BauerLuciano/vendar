<?php

namespace App\Http\Controllers;

use App\Enums\MetodoPago;
use App\Enums\PaymentChannel;
use App\Enums\VentaStatus;
use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\Lote;
use App\Models\TurnoCaja;
use App\Models\Consumidor;
use App\Models\CuentaCorriente;
use App\Models\MovimientoCuentaCorriente;
use App\Models\MovimientoCaja;
use App\Models\PaymentMethodConfiguration;
use App\Jobs\EnviarTicketDigital;
use App\Services\Payment\Contracts\CheckoutRequest;
use App\Services\Payment\PaymentRecorder;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class VentaController extends Controller
{
    public function __construct(
        private readonly PaymentRecorder $paymentRecorder,
        private readonly PaymentService $paymentService,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $sucursalId = session('sucursal_activa_id', $user->branch_id);
        if (!$sucursalId) {
            abort(403, 'No tienes una sucursal asignada.');
        }
        
        $search = $request->input('search');
        $estado = $request->input('estado', 'all');
        $fecha_desde = $request->input('fecha_desde');
        $fecha_hasta = $request->input('fecha_hasta');

        $ventas = Venta::with(['consumidor', 'turno.cajero', 'turno.caja', 'detalles.producto'])
            ->whereHas('turno.caja', function ($q) use ($sucursalId) {
                $q->where('sucursal_id', $sucursalId);
            })
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    if (is_numeric($search)) {
                        $sub->where('id', $search)
                            ->orWhere(function ($sub2) use ($search) {
                                $sub2->whereHas('consumidor', fn ($q) => $q->where('nombre', 'ILIKE', "%{$search}%")
                                    ->orWhere('apellido', 'ILIKE', "%{$search}%"));
                            });
                    } else {
                        $sub->whereHas('consumidor', fn ($q) => $q->where('nombre', 'ILIKE', "%{$search}%")
                            ->orWhere('apellido', 'ILIKE', "%{$search}%"));
                    }
                });
            })
            ->when($estado !== 'all', function ($q) use ($estado) {
                $q->where('estado', $estado);
            })
            ->when($fecha_desde, function ($q, $fecha_desde) {
                $q->whereDate('created_at', '>=', $fecha_desde);
            })
            ->when($fecha_hasta, function ($q, $fecha_hasta) {
                $q->whereDate('created_at', '<=', $fecha_hasta);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Ventas/Index', [
            'ventas' => $ventas,
            'filtros' => $request->only(['search', 'estado', 'fecha_desde', 'fecha_hasta'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'turno_caja_id' => 'required|exists:turno_cajas,id',
            'consumidor_id' => 'nullable|exists:consumidores,id', 
            'items'         => 'required|array|min:1',
            'total'         => 'required|numeric|min:0',
            'metodo_pago'   => 'required_without:pagos|string',
            'pagos'         => 'nullable|array|min:1',
            'pagos.*.metodo_pago' => 'required_with:pagos|string',
            'pagos.*.monto' => 'required_with:pagos|numeric|min:0',
            'pagos.*.banco' => 'nullable|string|max:255',
            'pagos.*.tipo_tarjeta' => 'nullable|string|in:DEBITO,CREDITO',
            'pagos.*.cuotas' => 'nullable|integer|min:1',
            'pagos.*.recargo_porcentaje' => 'nullable|numeric|min:0|max:100',
            'pagos.*.recargo_monto' => 'nullable|numeric|min:0',
        ]);

        $comercioId = auth()->user()->branch?->comercio_id;
        $permitirStockNegativo = \App\Models\Configuracion::paraComercio($comercioId)['permitir_stock_negativo'] ?? '0';
        $permitirStockNegativo = filter_var($permitirStockNegativo, FILTER_VALIDATE_BOOLEAN);

        $labelMap = $comercioId ? \App\Models\PaymentMethodConfiguration::labelMap($comercioId) : [];
        $items = collect($request->items)->sortBy('id')->values()->all();
        $totalCalculado = collect($items)->sum(fn ($item) => (float) ($item['precio_venta'] ?? 0) * (float) ($item['cantidad'] ?? 0));

        $turno = TurnoCaja::with('caja')
            ->when($comercioId, fn ($q) => $q->whereHas('caja.sucursal', fn ($sq) => $sq->where('comercio_id', $comercioId)))
            ->findOrFail($request->turno_caja_id);
        $sucursalId = $turno->caja->sucursal_id;

        $pagos = $request->pagos;
        if (!$pagos) {
            $pagos = [['metodo_pago' => $request->metodo_pago, 'monto' => (float) $request->total]];
        }

        $pagosNormalizados = collect($pagos)->map(function ($p) {
            $pago = [
                'metodo_pago' => MetodoPago::fromString($p['metodo_pago'])->value,
                'monto' => (float) $p['monto'],
            ];

            // Include recargo data for card payments
            if (isset($p['banco']) && $p['banco']) {
                $pago['banco'] = $p['banco'];
            }
            if (isset($p['tipo_tarjeta']) && $p['tipo_tarjeta']) {
                $pago['tipo_tarjeta'] = $p['tipo_tarjeta'];
            }
            if (isset($p['cuotas']) && $p['cuotas']) {
                $pago['cuotas'] = (int) $p['cuotas'];
            }
            if (isset($p['recargo_porcentaje']) && $p['recargo_porcentaje'] !== null) {
                $pago['recargo_porcentaje'] = (float) $p['recargo_porcentaje'];
            }
            if (isset($p['recargo_monto']) && $p['recargo_monto'] !== null) {
                $pago['recargo_monto'] = (float) $p['recargo_monto'];
            }

            return $pago;
        });

        $sumaPagos = $pagosNormalizados->sum('monto');
        if (abs($sumaPagos - $totalCalculado) > 0.01) {
            return redirect()->back()->withErrors(['error' =>
                "La suma de los pagos (\$$sumaPagos) no coincide con el total calculado (\${$totalCalculado})."
            ]);
        }

        // Issue #2 & #3: Calculate recargo_monto server-side from pagos, don't trust frontend
        $recargoMontoCalculado = $pagosNormalizados
            ->filter(fn ($p) => isset($p['tipo_tarjeta']) && in_array($p['tipo_tarjeta'], ['DEBITO', 'CREDITO']))
            ->sum('recargo_monto');

        $tieneCuentaCorriente = $pagosNormalizados->contains('metodo_pago', MetodoPago::CUENTA_CORRIENTE->value);
        $montoCC = $pagosNormalizados->where('metodo_pago', MetodoPago::CUENTA_CORRIENTE->value)->sum('monto');

        $metodoPagoNormalizado = $pagosNormalizados->count() === 1
            ? $pagosNormalizados->first()['metodo_pago']
            : 'MULTIPLE';

        if ($tieneCuentaCorriente) {
            if (!$request->consumidor_id) {
                return redirect()->back()->withErrors(['error' =>
                    'Debe seleccionar un cliente para realizar una venta en cuenta corriente.'
                ]);
            }

            $consumidor = Consumidor::with('cuentaCorriente')
                ->when($comercioId, fn ($q) => $q->where('comercio_id', $comercioId))
                ->where('estado', true)
                ->find($request->consumidor_id);

            if (!$consumidor) {
                return redirect()->back()->withErrors(['error' => 'El cliente no existe o no pertenece a tu comercio.']);
            }

            $cuenta = CuentaCorriente::lockForUpdate()
                ->where('consumidor_id', $request->consumidor_id)
                ->first();
            $deudaActual = $cuenta ? $cuenta->saldo_deudor : 0;
            $disponible = $consumidor->limite_cuenta_corriente - $deudaActual;

            if ($montoCC > $disponible) {
                $montoFormateado = number_format($disponible, 2, ',', '.');
                return redirect()->back()->withErrors(['error' =>
                    "Crédito insuficiente. El límite disponible del cliente es de $$montoFormateado."
                ]);
            }
        }

        $allConfigs = $this->loadAllConfigs($comercioId);
        $manualConfigs = $allConfigs['manual'];   // channel=MANUAL
        $gatewayConfigs = $allConfigs['gateway']; // channel=QR, POINT, API

        $esFlujoManual = $pagosNormalizados->contains(fn ($p) => isset($manualConfigs[$p['metodo_pago']]));
        $esFlujoGateway = $pagosNormalizados->contains(fn ($p) => isset($gatewayConfigs[$p['metodo_pago']]));

        if ($esFlujoManual && $esFlujoGateway) {
            return redirect()->back()->withErrors(['error' =>
                'No podés combinar métodos manuales con pagos electrónicos en una misma venta.'
            ]);
        }

        $esPendiente = $esFlujoManual || $esFlujoGateway;

        // ──────────────────────────────────────────────
        // 1. DB transaction (solo operaciones de BD)
        // ──────────────────────────────────────────────
        try {
            DB::beginTransaction();

            foreach ($items as $item) {
                $productoActivo = Producto::where('id', $item['id'])->where('estado', true)->exists();
                if (!$productoActivo) {
                    $nombre = $item['nombre'] ?? "Producto ID: {$item['id']}";
                    throw new \Exception("El producto {$nombre} no está activo y no puede venderse.");
                }

                $stockActual = DB::table('producto_sucursal')
                    ->where('producto_id', $item['id'])
                    ->where('sucursal_id', $sucursalId)
                    ->lockForUpdate()
                    ->first();

                $cantDisponible = $stockActual
                    ? $stockActual->cantidad_fisica - $stockActual->cantidad_reservada
                    : 0;

                if (!$permitirStockNegativo) {
                    if (!$stockActual || $cantDisponible < $item['cantidad']) {
                        $nombre = $item['nombre'] ?? "Producto ID: {$item['id']}";
                        throw new \Exception("Stock insuficiente para: {$nombre}. Disponible: {$cantDisponible}");
                    }
                }
            }

            $venta = Venta::create([
                'turno_caja_id' => $request->turno_caja_id,
                'consumidor_id' => $request->consumidor_id,
                'metodo_pago'   => $metodoPagoNormalizado,
                'pagos'         => $pagosNormalizados->toArray(),
                'total'         => $totalCalculado,
                'recargo_monto' => $recargoMontoCalculado,
                'estado'        => $esPendiente ? VentaStatus::PENDING : VentaStatus::COMPLETED,
            ]);

            if (!$esPendiente) {
                foreach ($pagosNormalizados as $pago) {
                    if ($pago['metodo_pago'] === MetodoPago::CUENTA_CORRIENTE->value) {
                        if (!isset($cuenta)) {
                            $cuenta = CuentaCorriente::firstOrCreate(
                                ['consumidor_id' => $request->consumidor_id],
                                ['saldo_deudor' => 0],
                            );
                        }

                        $cuenta->increment('saldo_deudor', $pago['monto']);

                        MovimientoCuentaCorriente::create([
                            'cuenta_corriente_id' => $cuenta->id,
                            'venta_id'            => $venta->id,
                            'monto'               => $pago['monto'],
                            'tipo'                => 'cargo',
                            'descripcion'         => 'Compra en POS (' . $pago['metodo_pago'] . ')',
                        ]);
                    } else {
                        MovimientoCaja::create([
                            'turno_caja_id' => $request->turno_caja_id,
                            'tipo'          => 'INGRESO',
                            'concepto'      => 'VENTA_MOSTRADOR',
                            'metodo_pago'   => $pago['metodo_pago'],
                            'monto'         => $pago['monto'],
                            'descripcion'   => 'Ticket de venta #' . $venta->id . ' (' . $pago['metodo_pago'] . ')',
                        ]);
                    }
                }
            }

            foreach ($items as $item) {
                $cantidadAVender = $item['cantidad'];

                $detalle = DetalleVenta::create([
                    'venta_id'        => $venta->id,
                    'producto_id'     => $item['id'],
                    'cantidad'        => $cantidadAVender,
                    'precio_unitario' => $item['precio_venta'],
                    'subtotal'        => $cantidadAVender * $item['precio_venta'],
                ]);

                $lotes = Lote::where('producto_id', $item['id'])
                    ->where('sucursal_id', $sucursalId)
                    ->where('stock_actual', '>', 0)
                    ->orderBy('fecha_vencimiento', 'asc')
                    ->lockForUpdate()
                    ->get();

                $pendientePorRestar = $cantidadAVender;
                $lotesConsumidos = [];

                foreach ($lotes as $lote) {
                    if ($pendientePorRestar <= 0) break;

                    if ($lote->stock_actual >= $pendientePorRestar) {
                        $cantidadRestada = $pendientePorRestar;
                        $lote->decrement('stock_actual', $pendientePorRestar);
                        $pendientePorRestar = 0;
                    } else {
                        $cantidadRestada = (float) $lote->stock_actual;
                        $pendientePorRestar -= $lote->stock_actual;
                        $lote->update(['stock_actual' => 0]);
                    }

                    $lotesConsumidos[] = [
                        'detalle_venta_id' => $detalle->id,
                        'lote_id'          => $lote->id,
                        'cantidad'         => $cantidadRestada,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ];
                }

                if (!empty($lotesConsumidos)) {
                    DB::table('detalle_venta_lote')->insert($lotesConsumidos);
                }

                $registroStock = DB::table('producto_sucursal')
                    ->where('producto_id', $item['id'])
                    ->where('sucursal_id', $sucursalId)
                    ->first();

                $cantidadAnterior = $registroStock ? $registroStock->cantidad_fisica : 0;
                $nuevaCantidad = $cantidadAnterior - $cantidadAVender;

                DB::table('producto_sucursal')->updateOrInsert(
                    ['producto_id' => $item['id'], 'sucursal_id' => $sucursalId],
                    ['cantidad_fisica' => $nuevaCantidad]
                );

                DB::table('movimientos_stock')->insert([
                    'producto_id'         => $item['id'],
                    'sucursal_id'         => $sucursalId,
                    'user_id'             => auth()->id(),
                    'tipo_movimiento'     => 'Venta',
                    'cantidad_anterior'   => $cantidadAnterior,
                    'cantidad_movimiento' => -$cantidadAVender,
                    'cantidad_actual'     => $nuevaCantidad,
                    'motivo'              => "Venta POS #{$venta->id}" . ($nuevaCantidad < 0 ? " (STOCK NEGATIVO)" : ""),
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }

            if ($esFlujoManual) {
                foreach ($pagosNormalizados as $pago) {
                    $config = $manualConfigs[$pago['metodo_pago']] ?? null;
                    if (!$config) {
                        continue;
                    }

                    $this->paymentRecorder->createPending(
                        payable: $venta,
                        provider: $config['provider'] ?? $pago['metodo_pago'],
                        amount: $pago['monto'],
                        channel: PaymentChannel::MANUAL,
                        reference: (string) $venta->id,
                    );
                }
            }

            if ($esFlujoGateway) {
                foreach ($pagosNormalizados as $pago) {
                    $config = $gatewayConfigs[$pago['metodo_pago']] ?? null;
                    if (!$config) {
                        continue;
                    }

                    $this->paymentRecorder->createPending(
                        payable: $venta,
                        provider: $config['provider'],
                        amount: $pago['monto'],
                        channel: PaymentChannel::from($config['channel']),
                        reference: "venta_{$venta->id}",
                    );
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error($e->getMessage());
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }

        // ──────────────────────────────────────────────
        // 2. Si es instantáneo → ticket y respuesta
        // ──────────────────────────────────────────────
        if (!$esPendiente) {
            EnviarTicketDigital::dispatch($venta->id);

            return redirect()->back()->with([
                'success' => 'Venta exitosa',
                'venta_id' => $venta->id,
            ]);
        }

        // ──────────────────────────────────────────────
        // 3. Si es manual → respuesta con display_info
        // ──────────────────────────────────────────────
        if ($esFlujoManual) {
            $displayInfo = [];
            foreach ($pagosNormalizados as $pago) {
                $config = $manualConfigs[$pago['metodo_pago']] ?? null;
                $label = $labelMap[$pago['metodo_pago']] ?? MetodoPago::fromString($pago['metodo_pago'])->label();
                $displayInfo[] = [
                    'metodo_pago' => $pago['metodo_pago'],
                    'label' => $label,
                    'monto' => $pago['monto'],
                    'provider' => $config['provider'] ?? null,
                    'display_data' => $config['display_data'] ?? null,
                    'config_id' => $config['id'] ?? null,
                    'channel' => 'manual',
                ];
            }

            return redirect()->back()->with([
                'success' => 'Venta pendiente de pago',
                'venta_id' => $venta->id,
                'es_pendiente' => true,
                'display_info' => $displayInfo,
            ]);
        }

        // ──────────────────────────────────────────────
        // 4. Si es gateway → llamar API externa (fuera de tx)
        // ──────────────────────────────────────────────
        $displayInfo = [];
        $gatewayError = null;

        foreach ($pagosNormalizados as $pago) {
            $config = $gatewayConfigs[$pago['metodo_pago']] ?? null;
            if (!$config) {
                continue;
            }

            $label = $labelMap[$pago['metodo_pago']] ?? MetodoPago::fromString($pago['metodo_pago'])->label();
            $provider = $config['provider'];
            $channel = PaymentChannel::from($config['channel']);

            $itemsMapped = array_map(fn ($item) => [
                'id' => (string) $item['id'],
                'title' => $item['nombre'] ?? 'Producto',
                'quantity' => (int) ($item['cantidad'] ?? 1),
                'unit_price' => (float) ($item['precio_venta'] ?? 0),
                'total_amount' => (float) ($item['precio_venta'] ?? 0) * (int) ($item['cantidad'] ?? 1),
            ], $request->items);

            try {
                $response = $this->paymentService->initiatePosPayment(
                    provider: $provider,
                    request: new CheckoutRequest(
                        referenceId: "venta_{$venta->id}",
                        title: "Venta POS #{$venta->id}",
                        description: "Venta en POS",
                        amount: $pago['monto'],
                        items: $itemsMapped,
                        notificationUrl: route('mercadopago.notificacion', ['comercio_id' => $comercioId]),
                    ),
                    channel: $channel,
                    options: [
                        'user_id' => $config['user_id'] ?? null,
                        'store_id' => $config['store_id'] ?? null,
                    ],
                );

                $venta->payments()
                    ->where('provider', $provider)
                    ->where('status', PaymentStatus::PENDING)
                    ->latest()
                    ->update([
                        'provider_reference' => $response->gatewayTransactionId,
                        'gateway_response' => $response->raw,
                    ]);

                $presentacion = $response->presentation;

                $displayInfo[] = [
                    'metodo_pago' => $pago['metodo_pago'],
                    'label' => $label,
                    'monto' => $pago['monto'],
                    'provider' => $provider,
                    'config_id' => $config['id'],
                    'channel' => $channel->value,
                    'presentation' => $presentacion ? [
                        'type' => $presentacion->type,
                        'data' => $presentacion->data,
                    ] : null,
                ];
            } catch (\Throwable $e) {
                \Log::error("Error gateway para venta #{$venta->id}: {$e->getMessage()}");
                $gatewayError = $e->getMessage();
            }
        }

        if ($gatewayError) {
            return redirect()->back()->withErrors(['error' =>
                "Venta creada pero el pago electrónico falló: {$gatewayError}. Cancelá la venta e intentá de nuevo."
            ]);
        }

        return redirect()->back()->with([
            'success' => 'Venta pendiente de pago',
            'venta_id' => $venta->id,
            'es_pendiente' => true,
            'display_info' => $displayInfo,
        ]);
    }

    public function status(Venta $venta): JsonResponse
    {
        return response()->json([
            'estado' => $venta->estado->value,
        ]);
    }

    public function confirmarPago(Request $request, Venta $venta)
    {
        $comercioId = auth()->user()->branch?->comercio_id;
        if ($comercioId) {
            $existe = Venta::where('id', $venta->id)
                ->whereHas('turno.caja.sucursal', fn ($q) => $q->where('comercio_id', $comercioId))
                ->exists();
            if (!$existe) {
                abort(403, 'Esta venta no pertenece a tu comercio.');
            }
        }

        return DB::transaction(function () use ($venta) {
            $venta = Venta::lockForUpdate()->with('turno.caja')->findOrFail($venta->id);

            if ($venta->estado !== VentaStatus::PENDING) {
                return redirect()->back()->withErrors(['error' => 'La venta no está pendiente de pago.']);
            }

            $pagos = $venta->pagos ?? [['metodo_pago' => $venta->metodo_pago, 'monto' => $venta->total]];

            $manualConfigs = $this->loadManualConfigs(auth()->user()->branch?->comercio_id);

            foreach ($pagos as $pago) {
                $metodo = MetodoPago::fromString($pago['metodo_pago']);
                $config = $manualConfigs[$pago['metodo_pago']] ?? null;
                $provider = $config['provider'] ?? $pago['metodo_pago'];

                if ($metodo === MetodoPago::CUENTA_CORRIENTE) {
                    $cuenta = CuentaCorriente::firstOrCreate(
                        ['consumidor_id' => $venta->consumidor_id],
                        ['saldo_deudor' => 0],
                    );
                    $cuenta->increment('saldo_deudor', $pago['monto']);
                    MovimientoCuentaCorriente::create([
                        'cuenta_corriente_id' => $cuenta->id,
                        'venta_id'            => $venta->id,
                        'monto'               => $pago['monto'],
                        'tipo'                => 'cargo',
                        'descripcion'         => 'Compra en POS (' . $pago['metodo_pago'] . ')',
                    ]);
                } else {
                    MovimientoCaja::create([
                        'turno_caja_id' => $venta->turno_caja_id,
                        'tipo'          => 'INGRESO',
                        'concepto'      => 'VENTA_MOSTRADOR',
                        'metodo_pago'   => $pago['metodo_pago'],
                        'monto'         => $pago['monto'],
                        'descripcion'   => 'Confirmación pago venta #' . $venta->id . ' (' . $pago['metodo_pago'] . ')',
                    ]);
                }

                if ($config) {
                    $this->paymentRecorder->approve($venta, $provider);
                }
            }

            $venta->update(['estado' => VentaStatus::COMPLETED]);

            EnviarTicketDigital::dispatch($venta->id);

            return redirect()->back()->with(['success' => 'Pago confirmado', 'venta_id' => $venta->id]);
        });
    }

    public function cancelar(Request $request, Venta $venta)
    {
        $request->validate(['motivo' => 'required|string|max:255']);

        $comercioId = auth()->user()->branch?->comercio_id;
        if ($comercioId) {
            $existe = Venta::where('id', $venta->id)
                ->whereHas('turno.caja.sucursal', fn ($q) => $q->where('comercio_id', $comercioId))
                ->exists();
            if (!$existe) {
                abort(403, 'Esta venta no pertenece a tu comercio.');
            }
        }

        return DB::transaction(function () use ($venta, $request) {
            $venta = Venta::lockForUpdate()->with('turno.caja', 'detalles.lotes')->findOrFail($venta->id);

            if ($venta->estado === VentaStatus::CANCELLED) return redirect()->back();

            $sucursalId = $venta->turno->caja->sucursal_id;

            $loteIds = $venta->detalles->flatMap(fn ($d) => $d->lotes->pluck('id'))->unique()->sort()->values()->all();
            if (!empty($loteIds)) {
                DB::table('lotes')->whereIn('id', $loteIds)->lockForUpdate()->get();
            }

            foreach ($venta->detalles as $detalle) {
                foreach ($detalle->lotes as $lote) {
                    $cantidad = (float) $lote->pivot->cantidad;
                    DB::table('lotes')
                        ->where('id', $lote->id)
                        ->update([
                            'stock_actual' => DB::raw("stock_actual + " . $cantidad),
                            'updated_at'  => now(),
                        ]);
                }
            }

            foreach ($venta->detalles as $detalle) {
                $stockLocked = DB::table('producto_sucursal')
                    ->where('sucursal_id', $sucursalId)
                    ->where('producto_id', $detalle->producto_id)
                    ->lockForUpdate()
                    ->first();

                $cantidadAnterior = $stockLocked ? (float) $stockLocked->cantidad_fisica : 0;

                DB::table('producto_sucursal')
                    ->where('sucursal_id', $sucursalId)
                    ->where('producto_id', $detalle->producto_id)
                    ->update([
                        'cantidad_fisica' => DB::raw("cantidad_fisica + " . (float) $detalle->cantidad),
                        'updated_at'     => now(),
                    ]);

                DB::table('movimientos_stock')->insert([
                    'producto_id'         => $detalle->producto_id,
                    'sucursal_id'         => $sucursalId,
                    'user_id'             => auth()->id(),
                    'tipo_movimiento'     => 'Cancelación Venta',
                    'cantidad_anterior'   => $cantidadAnterior,
                    'cantidad_movimiento' => $detalle->cantidad,
                    'cantidad_actual'     => $cantidadAnterior + $detalle->cantidad,
                    'motivo'              => "Venta #{$venta->id}: {$request->motivo}",
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }

            $pagos = $venta->pagos_display;

            if ($venta->estado === VentaStatus::COMPLETED) {
                foreach ($pagos as $pagoRevertir) {
                    if ($pagoRevertir['metodo_pago'] === MetodoPago::CUENTA_CORRIENTE->value && $venta->consumidor_id) {
                        $cuenta = CuentaCorriente::where('consumidor_id', $venta->consumidor_id)
                            ->lockForUpdate()
                            ->first();
                        if ($cuenta) {
                            $cuenta->decrement('saldo_deudor', $pagoRevertir['monto']);
                            MovimientoCuentaCorriente::create([
                                'cuenta_corriente_id' => $cuenta->id,
                                'venta_id'            => $venta->id,
                                'monto'               => $pagoRevertir['monto'],
                                'tipo'                => 'abono',
                                'descripcion'         => 'Anulación Venta #' . $venta->id . ' (' . $pagoRevertir['metodo_pago'] . ')',
                            ]);
                        }
                    } else {
                        MovimientoCaja::create([
                            'turno_caja_id' => $venta->turno_caja_id,
                            'tipo'          => 'EGRESO',
                            'concepto'      => 'ANULACION_VENTA',
                            'metodo_pago'   => $pagoRevertir['metodo_pago'],
                            'monto'         => $pagoRevertir['monto'],
                            'descripcion'   => 'Anulación de venta #' . $venta->id . ' - Motivo: ' . $request->motivo . ' (' . $pagoRevertir['metodo_pago'] . ')',
                        ]);
                    }
                }
            }

            $manualConfigsCancel = $this->loadManualConfigs(auth()->user()->branch?->comercio_id);

            foreach ($pagos as $pagoRevertir) {
                $config = $manualConfigsCancel[$pagoRevertir['metodo_pago']] ?? null;
                if ($config) {
                    $provider = $config['provider'] ?? $pagoRevertir['metodo_pago'];
                    $this->paymentRecorder->cancel($venta, $provider);
                }
            }

            $venta->update(['estado' => VentaStatus::CANCELLED, 'motivo_anulacion' => $request->motivo]);

            foreach ($venta->detalles as $detalle) {
                $detalle->update(['cantidad_devuelta' => $detalle->cantidad]);
            }

            return redirect()->back();
        });
    }

    public function devolver(Request $request, Venta $venta)
    {
        $request->validate([
            'items'                       => 'required|array|min:1',
            'items.*.detalle_id'          => 'required|integer|exists:detalle_ventas,id',
            'items.*.cantidad'            => 'required|numeric|min:0.01',
        ]);

        $comercioId = auth()->user()->branch?->comercio_id;
        if ($comercioId) {
            $existe = Venta::where('id', $venta->id)
                ->whereHas('turno.caja.sucursal', fn ($q) => $q->where('comercio_id', $comercioId))
                ->exists();
            if (!$existe) {
                abort(403, 'Esta venta no pertenece a tu comercio.');
            }
        }

        return DB::transaction(function () use ($venta, $request) {
            $venta = Venta::lockForUpdate()->with('turno.caja', 'detalles.lotes', 'detalles.producto')->findOrFail($venta->id);

            if ($venta->estado !== VentaStatus::COMPLETED) {
                return redirect()->back()->withErrors('Solo se pueden devolver ventas completadas.');
            }

            $sucursalId = $venta->turno->caja->sucursal_id;
            $montoTotalDevuelto = 0;

            $detallesMap = $venta->detalles->keyBy('id');

            $loteIds = collect($request->items)
                ->flatMap(fn ($i) => ($d = $detallesMap->get($i['detalle_id'])) ? $d->lotes->pluck('id') : collect())
                ->unique()->sort()->values()->all();
            if (!empty($loteIds)) {
                DB::table('lotes')->whereIn('id', $loteIds)->lockForUpdate()->get();
            }

            foreach ($request->items as $item) {
                $detalle = $detallesMap->get($item['detalle_id']);
                if (!$detalle) continue;

                $detalle = DetalleVenta::lockForUpdate()->findOrFail($detalle->id);

                $cantidadADevolver = (float) $item['cantidad'];
                $yaDevuelto = (float) ($detalle->cantidad_devuelta ?? 0);

                if ($yaDevuelto + $cantidadADevolver > (float) $detalle->cantidad) {
                    return redirect()->back()->withErrors("Ya devolviste {$yaDevuelto} de {$detalle->cantidad} unidades de {$detalle->producto->nombre}. No podés devolver {$cantidadADevolver} más.");
                }
                $precioUnitario = (float) $detalle->precio_unitario;
                $montoTotalDevuelto += $precioUnitario * $cantidadADevolver;

                $cantidadRestante = $cantidadADevolver;
                foreach ($detalle->lotes as $lote) {
                    $cantidadLote = (float) $lote->pivot->cantidad;
                    $aRestaurar = min($cantidadRestante, $cantidadLote);
                    if ($aRestaurar > 0) {
                        DB::table('lotes')
                            ->where('id', $lote->id)
                            ->update([
                                'stock_actual' => DB::raw("stock_actual + " . $aRestaurar),
                                'updated_at'   => now(),
                            ]);
                        $cantidadRestante -= $aRestaurar;
                    }
                    if ($cantidadRestante <= 0) break;
                }

                $stockLocked = DB::table('producto_sucursal')
                    ->where('sucursal_id', $sucursalId)
                    ->where('producto_id', $detalle->producto_id)
                    ->lockForUpdate()
                    ->first();

                $cantidadAnterior = $stockLocked ? (float) $stockLocked->cantidad_fisica : 0;

                DB::table('producto_sucursal')
                    ->where('sucursal_id', $sucursalId)
                    ->where('producto_id', $detalle->producto_id)
                    ->update([
                        'cantidad_fisica' => DB::raw("cantidad_fisica + " . $cantidadADevolver),
                        'updated_at'      => now(),
                    ]);

                DB::table('movimientos_stock')->insert([
                    'producto_id'         => $detalle->producto_id,
                    'sucursal_id'         => $sucursalId,
                    'user_id'             => auth()->id(),
                    'tipo_movimiento'     => 'Devolución',
                    'cantidad_anterior'   => $cantidadAnterior,
                    'cantidad_movimiento' => $cantidadADevolver,
                    'cantidad_actual'     => $cantidadAnterior + $cantidadADevolver,
                    'motivo'              => "Devolución Venta #{$venta->id} (detalle #{$detalle->id})",
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                $detalle->increment('cantidad_devuelta', $cantidadADevolver);
            }

            if ($montoTotalDevuelto > 0) {
                $pagos = $venta->pagos_display;
                $totalPagos = collect($pagos)->sum('monto');

                foreach ($pagos as $pago) {
                    $proporcion = $totalPagos > 0 ? $pago['monto'] / $totalPagos : 0;
                    $montoADevolver = round($montoTotalDevuelto * $proporcion, 2);
                    if ($montoADevolver <= 0) continue;

                    if ($pago['metodo_pago'] === MetodoPago::CUENTA_CORRIENTE->value && $venta->consumidor_id) {
                        $cuenta = CuentaCorriente::where('consumidor_id', $venta->consumidor_id)
                            ->lockForUpdate()
                            ->first();
                        if ($cuenta) {
                            $cuenta->decrement('saldo_deudor', $montoADevolver);
                            MovimientoCuentaCorriente::create([
                                'cuenta_corriente_id' => $cuenta->id,
                                'venta_id'            => $venta->id,
                                'monto'               => $montoADevolver,
                                'tipo'                => 'abono',
                                'descripcion'         => 'Devolución Venta #' . $venta->id,
                            ]);
                        }
                    } else {
                        MovimientoCaja::create([
                            'turno_caja_id' => $venta->turno_caja_id,
                            'tipo'          => 'EGRESO',
                            'concepto'      => 'DEVOLUCION',
                            'metodo_pago'   => $pago['metodo_pago'],
                            'monto'         => $montoADevolver,
                            'descripcion'   => 'Devolución Venta #' . $venta->id . ' (' . $pago['metodo_pago'] . ')',
                        ]);
                    }
                }
            }

            return redirect()->back()->with('success', 'Devolución procesada correctamente.');
        });
    }

    public function pendientes(Request $request)
    {
        $user = auth()->user();
        $turno = TurnoCaja::where('user_id', $user->id)->where('estado', 'Abierto')->first();
        if (!$turno) {
            return response()->json([]);
        }

        $comercioId = $user->branch?->comercio_id;

        $ventas = Venta::with(['payments', 'consumidor'])
            ->withCount('detalles')
            ->where('turno_caja_id', $turno->id)
            ->where('estado', VentaStatus::PENDING)
            ->when($comercioId, fn ($q) => $q->whereHas('turno.caja.sucursal', fn ($sq) => $sq->where('comercio_id', $comercioId)))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($v) {
                return [
                    'id' => $v->id,
                    'total' => (float) $v->total,
                    'metodo_pago' => $v->metodo_pago,
                    'pagos' => $v->pagos,
                    'consumidor' => $v->consumidor ? $v->consumidor->nombre . ' ' . $v->consumidor->apellido : null,
                    'created_at' => $v->created_at->format('H:i'),
                    'items_count' => $v->detalles_count,
                ];
            });

        return response()->json($ventas);
    }

    private function loadManualConfigs(?int $comercioId): array
    {
        if (!$comercioId) {
            return [];
        }

        $configs = PaymentMethodConfiguration::where('comercio_id', $comercioId)
            ->where('enabled', true)
            ->where('channel', PaymentChannel::MANUAL)
            ->get();

        $indexed = [];
        foreach ($configs as $cfg) {
            $indexed[$cfg->metodo_pago] = [
                'provider' => $cfg->provider,
                'display_data' => $cfg->display_data,
                'id' => $cfg->id,
            ];
        }

        return $indexed;
    }

    private function loadAllConfigs(?int $comercioId): array
    {
        if (!$comercioId) {
            return ['manual' => [], 'gateway' => []];
        }

        $configs = PaymentMethodConfiguration::where('comercio_id', $comercioId)
            ->where('enabled', true)
            ->get();

        $manual = [];
        $gateway = [];

        foreach ($configs as $cfg) {
            $entry = [
                'provider' => $cfg->provider,
                'display_data' => $cfg->display_data,
                'id' => $cfg->id,
                'channel' => $cfg->channel->value,
            ];

            if ($cfg->channel === PaymentChannel::MANUAL) {
                $manual[$cfg->metodo_pago] = $entry;
            } else {
                $entry['user_id'] = $cfg->provider_config['user_id'] ?? null;
                $entry['store_id'] = $cfg->provider_config['store_id'] ?? null;
                $gateway[$cfg->metodo_pago] = $entry;
            }
        }

        return ['manual' => $manual, 'gateway' => $gateway];
    }
}
