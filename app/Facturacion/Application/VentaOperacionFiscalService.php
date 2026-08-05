<?php

namespace App\Facturacion\Application;

use App\Enums\MetodoPago;
use App\Enums\PaymentChannel;
use App\Enums\VentaStatus;
use App\Facturacion\Application\Exceptions\EmisionVentaException;
use App\Facturacion\Application\Exceptions\VentaOperacionException;
use App\Facturacion\Domain\Contracts\PendienteNcRepository;
use App\Models\CuentaCorriente;
use App\Models\DetalleVenta;
use App\Models\MovimientoCaja;
use App\Models\MovimientoCuentaCorriente;
use App\Models\PaymentMethodConfiguration;
use App\Models\Venta;
use App\Services\Payment\PaymentRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Caso de uso de aplicación F8: anulación y devolución de venta como operación
 * completa (stock por lotes + producto_sucursal + movimientos_stock, reversión
 * de pagos/caja/cuenta corriente y Nota de Crédito), extraído del VentaController
 * para que el Panel de Diagnóstico pueda reintentarla (arquitectura §8 y §15).
 *
 * Si la emisión de la NC falla, la transacción revierte por completo y el
 * pendiente queda registrado en nc_pendientes para reintento. El reintento
 * re-ejecuta la operación completa: es la única opción fiscalmente correcta,
 * porque la venta vuelve a quedar COMPLETED tras el rollback.
 */
final class VentaOperacionFiscalService
{
    public function __construct(
        private readonly NcService $nc,
        private readonly PaymentRecorder $paymentRecorder,
        private readonly PendienteNcRepository $pendientes,
    ) {}

    /**
     * Anula una venta: restaura stock, revierte pagos/caja/cuenta corriente y
     * emite la NC total dentro de la transacción (invariante 2). Si la NC falla,
     * el rollback restaura la venta a COMPLETED y queda un pendiente registrado.
     */
    public function anular(int $ventaId, string $motivo, ?int $comercioId = null, ?int $usuarioId = null): void
    {
        try {
            DB::transaction(function () use ($ventaId, $motivo, $comercioId, $usuarioId) {
                $venta = Venta::lockForUpdate()->with('turno.caja', 'detalles.lotes')->findOrFail($ventaId);

                if ($venta->estado === VentaStatus::CANCELLED) {
                    return;
                }

                $sucursalId = $venta->turno->caja->sucursal_id;

                $loteIds = $venta->detalles->flatMap(fn ($d) => $d->lotes->pluck('id'))->unique()->sort()->values()->all();
                if (! empty($loteIds)) {
                    DB::table('lotes')->whereIn('id', $loteIds)->lockForUpdate()->get();
                }

                foreach ($venta->detalles as $detalle) {
                    foreach ($detalle->lotes as $lote) {
                        $cantidad = (float) $lote->pivot->cantidad;
                        DB::table('lotes')
                            ->where('id', $lote->id)
                            ->update([
                                'stock_actual' => DB::raw('stock_actual + '.$cantidad),
                                'updated_at' => now(),
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
                            'cantidad_fisica' => DB::raw('cantidad_fisica + '.(float) $detalle->cantidad),
                            'updated_at' => now(),
                        ]);

                    DB::table('movimientos_stock')->insert([
                        'producto_id' => $detalle->producto_id,
                        'sucursal_id' => $sucursalId,
                        'user_id' => $usuarioId,
                        'tipo_movimiento' => 'Cancelación Venta',
                        'cantidad_anterior' => $cantidadAnterior,
                        'cantidad_movimiento' => $detalle->cantidad,
                        'cantidad_actual' => $cantidadAnterior + $detalle->cantidad,
                        'motivo' => "Venta #{$venta->id}: {$motivo}",
                        'created_at' => now(),
                        'updated_at' => now(),
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
                                    'venta_id' => $venta->id,
                                    'monto' => $pagoRevertir['monto'],
                                    'tipo' => 'abono',
                                    'descripcion' => 'Anulación Venta #'.$venta->id.' ('.$pagoRevertir['metodo_pago'].')',
                                ]);
                            }
                        } else {
                            MovimientoCaja::create([
                                'turno_caja_id' => $venta->turno_caja_id,
                                'tipo' => 'EGRESO',
                                'concepto' => 'ANULACION_VENTA',
                                'metodo_pago' => $pagoRevertir['metodo_pago'],
                                'monto' => $pagoRevertir['monto'],
                                'descripcion' => 'Anulación de venta #'.$venta->id.' - Motivo: '.$motivo.' ('.$pagoRevertir['metodo_pago'].')',
                            ]);
                        }
                    }
                }

                $manualConfigsCancel = $this->loadManualConfigs($comercioId);

                foreach ($pagos as $pagoRevertir) {
                    $config = $manualConfigsCancel[$pagoRevertir['metodo_pago']] ?? null;
                    if ($config) {
                        $provider = $config['provider'] ?? $pagoRevertir['metodo_pago'];
                        $this->paymentRecorder->cancel($venta, $provider);
                    }
                }

                $venta->update(['estado' => VentaStatus::CANCELLED, 'motivo_anulacion' => $motivo]);

                // F6: NC total obligatoria dentro de la transacción (invariante 2).
                $this->nc->emitirNcSiCorresponde($venta);

                foreach ($venta->detalles as $detalle) {
                    $detalle->update(['cantidad_devuelta' => $detalle->cantidad]);
                }
            });
        } catch (EmisionVentaException $e) {
            $this->pendientes->registrarPendiente([
                'comercio_id' => $comercioId ?? $this->comercioDe($ventaId),
                'venta_id' => $ventaId,
                'tipo_operacion' => 'anulacion',
                'motivo' => $motivo,
                'items' => null,
                'monto_devuelto' => null,
                'motivo_fallo' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Devuelve (total o parcialmente) una venta: restaura stock por detalle,
     * revierte pagos/caja/cuenta corriente proporcionalmente y emite la NC
     * parcial dentro de la transacción (invariante 2). Los items a devolver se
     * persisten en el pendiente para poder reintentar la operación completa.
     *
     * @param  array<int, array{detalle_id: int, cantidad: float}>  $items
     */
    public function devolver(int $ventaId, array $items, ?int $comercioId = null, ?int $usuarioId = null): void
    {
        $montoTotalDevuelto = 0;

        try {
            DB::transaction(function () use ($ventaId, $items, $usuarioId, &$montoTotalDevuelto) {
                $venta = Venta::lockForUpdate()->with('turno.caja', 'detalles.lotes', 'detalles.producto')->findOrFail($ventaId);

                if ($venta->estado !== VentaStatus::COMPLETED) {
                    throw new VentaOperacionException('Solo se pueden devolver ventas completadas.');
                }

                $sucursalId = $venta->turno->caja->sucursal_id;

                $detallesMap = $venta->detalles->keyBy('id');

                $loteIds = collect($items)
                    ->flatMap(fn ($i) => ($d = $detallesMap->get($i['detalle_id'])) ? $d->lotes->pluck('id') : collect())
                    ->unique()->sort()->values()->all();
                if (! empty($loteIds)) {
                    DB::table('lotes')->whereIn('id', $loteIds)->lockForUpdate()->get();
                }

                foreach ($items as $item) {
                    $detalle = $detallesMap->get($item['detalle_id']);
                    if (! $detalle) {
                        continue;
                    }

                    $detalle = DetalleVenta::lockForUpdate()->findOrFail($detalle->id);

                    $cantidadADevolver = (float) $item['cantidad'];
                    $yaDevuelto = (float) ($detalle->cantidad_devuelta ?? 0);

                    if ($yaDevuelto + $cantidadADevolver > (float) $detalle->cantidad) {
                        throw new VentaOperacionException(
                            "Ya devolviste {$yaDevuelto} de {$detalle->cantidad} unidades de {$detalle->producto->nombre}. No podés devolver {$cantidadADevolver} más."
                        );
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
                                    'stock_actual' => DB::raw('stock_actual + '.$aRestaurar),
                                    'updated_at' => now(),
                                ]);
                            $cantidadRestante -= $aRestaurar;
                        }
                        if ($cantidadRestante <= 0) {
                            break;
                        }
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
                            'cantidad_fisica' => DB::raw('cantidad_fisica + '.$cantidadADevolver),
                            'updated_at' => now(),
                        ]);

                    DB::table('movimientos_stock')->insert([
                        'producto_id' => $detalle->producto_id,
                        'sucursal_id' => $sucursalId,
                        'user_id' => $usuarioId,
                        'tipo_movimiento' => 'Devolución',
                        'cantidad_anterior' => $cantidadAnterior,
                        'cantidad_movimiento' => $cantidadADevolver,
                        'cantidad_actual' => $cantidadAnterior + $cantidadADevolver,
                        'motivo' => "Devolución Venta #{$venta->id} (detalle #{$detalle->id})",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $detalle->increment('cantidad_devuelta', $cantidadADevolver);
                }

                if ($montoTotalDevuelto > 0) {
                    $pagos = $venta->pagos_display;
                    $totalPagos = collect($pagos)->sum('monto');

                    foreach ($pagos as $pago) {
                        $proporcion = $totalPagos > 0 ? $pago['monto'] / $totalPagos : 0;
                        $montoADevolver = round($montoTotalDevuelto * $proporcion, 2);
                        if ($montoADevolver <= 0) {
                            continue;
                        }

                        if ($pago['metodo_pago'] === MetodoPago::CUENTA_CORRIENTE->value && $venta->consumidor_id) {
                            $cuenta = CuentaCorriente::where('consumidor_id', $venta->consumidor_id)
                                ->lockForUpdate()
                                ->first();
                            if ($cuenta) {
                                $cuenta->decrement('saldo_deudor', $montoADevolver);
                                MovimientoCuentaCorriente::create([
                                    'cuenta_corriente_id' => $cuenta->id,
                                    'venta_id' => $venta->id,
                                    'monto' => $montoADevolver,
                                    'tipo' => 'abono',
                                    'descripcion' => 'Devolución Venta #'.$venta->id,
                                ]);
                            }
                        } else {
                            MovimientoCaja::create([
                                'turno_caja_id' => $venta->turno_caja_id,
                                'tipo' => 'EGRESO',
                                'concepto' => 'DEVOLUCION',
                                'metodo_pago' => $pago['metodo_pago'],
                                'monto' => $montoADevolver,
                                'descripcion' => 'Devolución Venta #'.$venta->id.' ('.$pago['metodo_pago'].')',
                            ]);
                        }
                    }
                }

                // F6: NC parcial por el monto devuelto dentro de la transacción
                // (invariante 2). Si falla, la devolución completa se revierte.
                $this->nc->emitirNcSiCorresponde($venta, (float) $montoTotalDevuelto);
            });
        } catch (EmisionVentaException $e) {
            $this->pendientes->registrarPendiente([
                'comercio_id' => $comercioId ?? $this->comercioDe($ventaId),
                'venta_id' => $ventaId,
                'tipo_operacion' => 'devolucion',
                'motivo' => null,
                'items' => $items,
                'monto_devuelto' => $montoTotalDevuelto,
                'motivo_fallo' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function comercioDe(int $ventaId): int
    {
        $venta = Venta::with('turno.caja.sucursal')->findOrFail($ventaId);

        return (int) $venta->turno->caja->sucursal->comercio_id;
    }

    private function loadManualConfigs(?int $comercioId): array
    {
        if (! $comercioId) {
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
}
