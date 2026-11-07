<?php

namespace App\Services\Suscripcion;

use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Exceptions\PagoManualDeshabilitadoException;
use App\Exceptions\PagoManualInvalidoException;
use App\Models\Comercio;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Registro técnico de pagos manuales de suscripción.
 *
 * Mientras los precios comerciales no estén confirmados, `registrar()` se
 * niega a operar. La bandera se habilita explícitamente por configuración y
 * nunca por defecto. Cuando se habilite, el pago:
 *
 *  - se registra en una transacción con lock sobre el comercio;
 *  - exige que el importe coincida con el precio configurado del plan;
 *  - calcula el período con `SuscripcionService::calcularPeriodo()`, sin
 *    acortar un período vigente;
 *  - es idempotente por `idempotency_key` y rechaza un segundo pago aprobado
 *    del mismo comercio para el mismo período;
 *  - recién después de persistir el pago actualiza vencimiento y estado;
 *  - deja auditoría con usuario, comercio y valores anteriores/nuevos.
 */
class PagoManualService
{
    public const METODOS_VALIDOS = ['efectivo', 'transferencia', 'tarjeta', 'otro'];

    public function __construct(
        private readonly SuscripcionService $suscripcionService,
    ) {}

    public function estaHabilitado(): bool
    {
        return (bool) config('vendar.facturacion.cobros_habilitados', false)
            && (bool) config('vendar.facturacion.precios_confirmados', false);
    }

    public function precioConfigurado(Comercio $comercio): ?float
    {
        $plan = $comercio->planContratado()->first();

        if (! $plan || $plan->precio_mensual === null) {
            return null;
        }

        return (float) $plan->precio_mensual;
    }

    /**
     * @param  array{importe?: mixed, payment_method?: mixed, referencia?: mixed, idempotency_key?: mixed, fecha_pago?: mixed}  $datos
     *
     * @throws PagoManualDeshabilitadoException
     * @throws PagoManualInvalidoException
     */
    public function registrar(Comercio $comercio, array $datos, User $responsable): Payment
    {
        if (! $this->estaHabilitado()) {
            throw new PagoManualDeshabilitadoException(
                'El registro de pagos manuales está deshabilitado hasta que se definan los precios comerciales.'
            );
        }

        $metodo = (string) ($datos['payment_method'] ?? '');

        if (! in_array($metodo, self::METODOS_VALIDOS, true)) {
            throw new PagoManualInvalidoException('Método de pago inválido.');
        }

        $importe = isset($datos['importe']) ? (float) $datos['importe'] : null;
        $referencia = isset($datos['referencia']) ? trim((string) $datos['referencia']) : null;
        $idempotencyKey = isset($datos['idempotency_key']) && $datos['idempotency_key'] !== ''
            ? (string) $datos['idempotency_key']
            : null;
        $fechaPago = isset($datos['fecha_pago'])
            ? Carbon::parse($datos['fecha_pago'])
            : now();

        return DB::transaction(function () use (
            $comercio,
            $responsable,
            $metodo,
            $importe,
            $referencia,
            $idempotencyKey,
            $fechaPago,
        ): Payment {
            /** @var Comercio $comercio */
            $comercio = Comercio::lockForUpdate()->findOrFail($comercio->id);

            $plan = $comercio->planContratado()->first();

            if (! $plan) {
                throw new PagoManualInvalidoException('El comercio no tiene un plan contratado.');
            }

            $precioConfigurado = (float) $plan->precio_mensual;

            if ($importe === null || abs($importe - $precioConfigurado) > 0.009) {
                throw new PagoManualInvalidoException(
                    'El importe no coincide con el precio configurado del plan.'
                );
            }

            if ($idempotencyKey !== null) {
                $existente = Payment::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                // Misma operación reintentada: devolvemos el pago original
                // sin volver a extender el vencimiento.
                if ($existente) {
                    return $existente;
                }
            }

            $periodo = $this->suscripcionService->calcularPeriodo(
                $comercio->vencimiento_pago
                    ? Carbon::parse($comercio->vencimiento_pago)
                    : null,
                $plan,
                $fechaPago,
            );

            $duplicado = Payment::query()
                ->where('payable_type', Comercio::class)
                ->where('payable_id', $comercio->id)
                ->where('provider', 'manual')
                ->where('status', PaymentStatus::APPROVED->value)
                ->whereDate('period_end', $periodo['fin']->toDateString())
                ->lockForUpdate()
                ->exists();

            if ($duplicado) {
                throw new PagoManualInvalidoException(
                    'Ya existe un pago aprobado para ese período.'
                );
            }

            $vencimientoAnterior = $comercio->vencimiento_pago?->toDateString();
            $estadoAnterior = $comercio->status;

            $pago = Payment::create([
                'payable_type' => Comercio::class,
                'payable_id' => $comercio->id,
                'provider' => 'manual',
                'channel' => PaymentChannel::MANUAL,
                'status' => PaymentStatus::APPROVED,
                'amount' => $importe,
                'currency' => 'ARS',
                'period_start' => $periodo['inicio']->toDateString(),
                'period_end' => $periodo['fin']->toDateString(),
                'payment_method' => $metodo,
                'created_by_id' => $responsable->id,
                'reference' => $referencia,
                'idempotency_key' => $idempotencyKey,
                'approved_at' => $fechaPago,
                'metadata' => [
                    'tipo' => 'suscripcion',
                    'origen' => 'manual',
                    'plan_id' => $plan->id,
                    'precio_configurado' => $precioConfigurado,
                ],
            ]);

            // Reactivación: sólo después de un pago válido y persistido.
            $comercio->vencimiento_pago = $periodo['fin']->toDateString();
            $comercio->status = 'activo';
            $comercio->pending_plan_id = null;
            $comercio->save();

            activity()
                ->performedOn($comercio)
                ->causedBy($responsable)
                ->withProperties([
                    'accion' => 'pago_manual_registrado',
                    'payment_id' => $pago->id,
                    'importe' => $importe,
                    'metodo' => $metodo,
                    'periodo_inicio' => $periodo['inicio']->toDateString(),
                    'periodo_fin' => $periodo['fin']->toDateString(),
                    'vencimiento_anterior' => $vencimientoAnterior,
                    'vencimiento_nuevo' => $periodo['fin']->toDateString(),
                    'estado_anterior' => $estadoAnterior,
                    'estado_nuevo' => $comercio->status,
                ])
                ->log('pago_manual_registrado');

            return $pago;
        });
    }
}
