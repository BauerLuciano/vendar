<?php

namespace App\Http\Controllers;

use App\Enums\MetodoPago;
use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\Lote; // 🔥 NUEVO: Importamos el modelo Lote
use App\Models\TurnoCaja;
use App\Models\Consumidor;
use App\Models\CuentaCorriente;
use App\Models\MovimientoCuentaCorriente;
use App\Models\MovimientoCaja;
use App\Jobs\EnviarTicketDigital;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class VentaController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user->branch_id) {
            abort(403, 'No tienes una sucursal asignada.');
        }
        $sucursalId = $user->branch_id;
        
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
                                $sub2->whereHas('consumidor', fn ($q) => $q->where('nombre', 'LIKE', "%{$search}%")
                                    ->orWhere('apellido', 'LIKE', "%{$search}%"));
                            });
                    } else {
                        $sub->whereHas('consumidor', fn ($q) => $q->where('nombre', 'LIKE', "%{$search}%")
                            ->orWhere('apellido', 'LIKE', "%{$search}%"));
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
        ]);

        $permitirStockNegativo = \App\Models\Configuracion::where('clave', 'permitir_stock_negativo')->value('valor');
        $permitirStockNegativo = filter_var($permitirStockNegativo, FILTER_VALIDATE_BOOLEAN);

        try {
            DB::beginTransaction();

            $comercioId = auth()->user()->branch?->comercio_id;

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

            $pagosNormalizados = collect($pagos)->map(fn ($p) => [
                'metodo_pago' => MetodoPago::fromString($p['metodo_pago'])->value,
                'monto' => (float) $p['monto'],
            ]);

            $sumaPagos = $pagosNormalizados->sum('monto');
            if (abs($sumaPagos - $totalCalculado) > 0.01) {
                throw new \Exception("La suma de los pagos (\$$sumaPagos) no coincide con el total calculado (\${$totalCalculado}).");
            }

            $tieneCuentaCorriente = $pagosNormalizados->contains('metodo_pago', MetodoPago::CUENTA_CORRIENTE->value);
            $montoCC = $pagosNormalizados->where('metodo_pago', MetodoPago::CUENTA_CORRIENTE->value)->sum('monto');

            $metodoPagoNormalizado = $pagosNormalizados->count() === 1
                ? $pagosNormalizados->first()['metodo_pago']
                : 'MULTIPLE';

            if ($tieneCuentaCorriente) {
                if (!$request->consumidor_id) {
                    throw new \Exception('Debe seleccionar un cliente para realizar una venta en cuenta corriente.');
                }

                $consumidor = Consumidor::with('cuentaCorriente')
                    ->when($comercioId, fn ($q) => $q->where('comercio_id', $comercioId))
                    ->where('estado', true)
                    ->findOrFail($request->consumidor_id);
                $cuenta = CuentaCorriente::lockForUpdate()
                    ->where('consumidor_id', $request->consumidor_id)
                    ->first();
                $deudaActual = $cuenta ? $cuenta->saldo_deudor : 0;
                $disponible = $consumidor->limite_cuenta_corriente - $deudaActual;

                if ($montoCC > $disponible) {
                    $montoFormateado = number_format($disponible, 2, ',', '.');
                    throw new \Exception("Crédito insuficiente. El límite disponible del cliente es de $$montoFormateado.");
                }
            }

            // 🛑 VALIDACIÓN DE STOCK ANTES DE CREAR LA VENTA
            foreach ($items as $item) {
                $stockActual = DB::table('producto_sucursal')
                    ->where('producto_id', $item['id'])
                    ->where('sucursal_id', $sucursalId)
                    ->lockForUpdate() 
                    ->first();

                $cantDisponible = $stockActual ? $stockActual->cantidad_fisica : 0;

                // Si NO se permite stock negativo, validamos estrictamente
                if (!$permitirStockNegativo) {
                    if (!$stockActual || $cantDisponible < $item['cantidad']) {
                        $nombre = $item['nombre'] ?? "Producto ID: {$item['id']}";
                        throw new \Exception("Stock insuficiente para: {$nombre}. Disponible: {$cantDisponible}");
                    }
                }
            }

            // 2. Crear la Venta
            $venta = Venta::create([
                'turno_caja_id' => $request->turno_caja_id,
                'consumidor_id' => $request->consumidor_id,
                'metodo_pago'   => $metodoPagoNormalizado,
                'pagos'         => $pagosNormalizados->toArray(),
                'total'         => $totalCalculado,
                'estado'        => 'Completada',
            ]);

            // 3. Lógica Financiera — un registro por cada método de pago
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

            // 4. Procesar Detalle, Descuento de Stock, Auditoría Y LOTES
            foreach ($items as $item) {
                
                $cantidadAVender = $item['cantidad'];

                $detalle = DetalleVenta::create([
                    'venta_id'        => $venta->id,
                    'producto_id'     => $item['id'],
                    'cantidad'        => $cantidadAVender,
                    'precio_unitario' => $item['precio_venta'],
                    'subtotal'        => $cantidadAVender * $item['precio_venta'],
                ]);

                // 🔥 LÓGICA DE LOTES (FIFO: First In, First Out)
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

                // Obtenemos el registro actual para saber la cantidad anterior
                $registroStock = DB::table('producto_sucursal')
                    ->where('producto_id', $item['id'])
                    ->where('sucursal_id', $sucursalId)
                    ->first();

                $cantidadAnterior = $registroStock ? $registroStock->cantidad_fisica : 0;
                $nuevaCantidad = $cantidadAnterior - $cantidadAVender;

                // Descuento físico de stock general (usamos updateOrInsert por si el registro no existe)
                DB::table('producto_sucursal')->updateOrInsert(
                    ['producto_id' => $item['id'], 'sucursal_id' => $sucursalId],
                    ['cantidad_fisica' => $nuevaCantidad]
                );
                
                // Registro en historial de movimientos (Auditoría mejorada)
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

            DB::commit();

            EnviarTicketDigital::dispatch($venta->id);

            return redirect()->back()->with([
                'success' => 'Venta exitosa',
                'venta_id' => $venta->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error($e->getMessage());
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
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

            if ($venta->estado === 'Cancelada') return redirect()->back();

            $sucursalId = $venta->turno->caja->sucursal_id;

            // 1. Devolver Stock de LOTES (restauración FIFO)
            foreach ($venta->detalles as $detalle) {
                foreach ($detalle->lotes as $lote) {
                    $cantidad = (float) $lote->pivot->cantidad;
                    $lote->increment('stock_actual', $cantidad);
                }
            }

            // 1b. Devolver Stock General
            foreach ($venta->detalles as $detalle) {
                DB::table('producto_sucursal')
                    ->where('sucursal_id', $sucursalId)
                    ->where('producto_id', $detalle->producto_id)
                    ->increment('cantidad_fisica', $detalle->cantidad);
            }

            // 2. Ajustar dinero (Revertir deuda o egreso de caja) — por cada método de pago
            $pagosParaRevertir = $venta->pagos_display;

            foreach ($pagosParaRevertir as $pagoRevertir) {
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

            $venta->update(['estado' => 'Cancelada', 'motivo_anulacion' => $request->motivo]);
            return redirect()->back();
        });
    }
}