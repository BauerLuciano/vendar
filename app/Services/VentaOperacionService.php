<?php

namespace App\Services;

use App\Enums\MetodoPago;
use App\Enums\PaymentChannel;
use App\Enums\VentaStatus;
use App\Models\CuentaCorriente;
use App\Models\DetalleVenta;
use App\Models\MovimientoCaja;
use App\Models\MovimientoCuentaCorriente;
use App\Models\PaymentMethodConfiguration;
use App\Models\Venta;
use App\Services\Payment\PaymentRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Anulación y devolución de venta como operación comercial completa: restaura
 * stock por lotes y por producto/sucursal, registra los movimientos, revierte
 * pagos, caja y cuenta corriente, y actualiza el estado de la venta.
 *
 * Nació como `App\Facturacion\Application\VentaOperacionFiscalService`, que
 * además emitía la Nota de Crédito de ARCA dentro de la misma transacción. Al
 * eliminar el módulo de facturación electrónica se conservó toda la parte
 * comercial —que es la que mueve stock y dinero— y se quitó únicamente la
 * emisión de la NC y el registro de pendientes de reintento.
 */
final class VentaOperacionService
{
    public function __construct(
        private readonly PaymentRecorder $paymentRecorder,
    ) {}

    /**
     * Anula una venta: restaura stock, revierte pagos/caja/cuenta corriente y
     * marca la venta como cancelada, todo dentro de la misma transacción.
     */
    public function anular(int $ventaId, string $motivo, ?int $comercioId = null, ?int $usuarioId = null): void
    {
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

            foreach ($venta->detalles as $detalle) {
                $detalle->update(['cantidad_devuelta' => $detalle->cantidad]);
            }
        });
    }

    /**
     * Devuelve total o parcialmente una venta: restaura stock por detalle y
     * revierte pagos/caja/cuenta corriente proporcionalmente al monto devuelto.
     *
     * @param  array<int, array{detalle_id: int, cantidad: float}>  $items
     */
    public function devolver(int $ventaId, array $items, ?int $comercioId = null, ?int $usuarioId = null): void
    {
        DB::transaction(function () use ($ventaId, $items, $usuarioId) {
            $venta = Venta::lockForUpdate()->with('turno.caja', 'detalles.lotes', 'detalles.producto')->findOrFail($ventaId);

            if ($venta->estado !== VentaStatus::COMPLETED) {
                throw new \RuntimeException('Solo se pueden devolver ventas completadas.');
            }

            $sucursalId = $venta->turno->caja->sucursal_id;

            $detallesMap = $venta->detalles->keyBy('id');

            $loteIds = collect($items)
                ->flatMap(fn ($i) => ($d = $detallesMap->get($i['detalle_id'])) ? $d->lotes->pluck('id') : collect())
                ->unique()->sort()->values()->all();
            if (! empty($loteIds)) {
                DB::table('lotes')->whereIn('id', $loteIds)->lockForUpdate()->get();
            }

            $montoTotalDevuelto = 0;

            foreach ($items as $item) {
                $detalle = $detallesMap->get($item['detalle_id']);
                if (! $detalle) {
                    continue;
                }

                $detalle = DetalleVenta::lockForUpdate()->findOrFail($detalle->id);

                $cantidadADevolver = (float) $item['cantidad'];
                $yaDevuelto = (float) ($detalle->cantidad_devuelta ?? 0);

                if ($yaDevuelto + $cantidadADevolver > (float) $detalle->cantidad) {
                    throw new \RuntimeException(
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
        });
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