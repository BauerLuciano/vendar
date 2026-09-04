<?php

namespace App\Http\Controllers;

use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Comercio;
use App\Models\Payment;
use App\Models\PedidoWeb;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use App\Services\Payment\PaymentService;
use App\Services\Payment\PaymentRecorder;
use App\Services\Payment\PaymentConfirmationService;
use App\Services\Payment\Contracts\PaymentStatusResponse;

class MercadoPagoNotificacionController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaymentRecorder $paymentRecorder,
        private readonly PaymentConfirmationService $confirmationService,
    ) {}

    public function notificacion(Request $request)
    {
        $paymentId = $request->input('data.id') ?? $request->input('id');

        if ($request->input('tipo') === 'plan') {
            return $this->procesarUpgradePlan($paymentId, $request);
        }

        $comercio = Comercio::with('paymentGateways')->find($request->input('comercio_id'));

        if (!$comercio) {
            return response()->json(['error' => 'Comercio not found'], 404);
        }

        $gateway = $this->paymentService
            ->forCommerce($comercio)
            ->gateway('mercadopago');

        if (!$gateway->verifyWebhookSignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        if (!$paymentId) {
            return response()->json(['error' => 'Invalid notification'], 400);
        }

        try {
            $status = $gateway->getPaymentStatus($paymentId);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to fetch payment from MP'], 502);
        }

        $externalRef = $status->referenceId;
        if (!$externalRef) {
            return response()->json(['error' => 'No external reference in payment'], 400);
        }

        return DB::transaction(function () use ($externalRef, $comercio, $status, $paymentId) {
            $pedido = PedidoWeb::lockForUpdate()
                ->where('comercio_id', $comercio->id)
                ->findOrFail($externalRef);
            $pedido->load('items');

            if ($pedido->pasarela_payment_id === $paymentId && $pedido->estado_pago === 'pagado') {
                return response()->json(['status' => 'already_processed']);
            }

            $estadoPagoAnterior = $pedido->estado_pago;

            $pedido->pasarela_payment_id = $paymentId;

            if ($status->status === PaymentStatus::APPROVED) {
                $this->confirmationService->approve($pedido, $paymentId, 'webhook');

                return response()->json(['status' => 'ok']);
            } elseif (in_array($status->status, [PaymentStatus::REJECTED, PaymentStatus::CANCELLED, PaymentStatus::REFUNDED])) {
                $pedido->estado_pago = 'rechazado';
            }

            $pedido->save();

            if ($pedido->estado_pago === 'rechazado' && $estadoPagoAnterior !== 'rechazado'
                && !in_array($pedido->estado_pedido, ['entregado', 'cancelado'])) {
                foreach ($pedido->items as $item) {
                    $ps = DB::table('producto_sucursal')
                        ->where('sucursal_id', $pedido->sucursal_id)
                        ->where('producto_id', $item->producto_id)
                        ->lockForUpdate()
                        ->first();

                    $reservadaAnterior = $ps ? (float) $ps->cantidad_reservada : 0;

                    if ($ps && $ps->cantidad_reservada >= $item->cantidad) {
                        DB::table('producto_sucursal')
                            ->where('sucursal_id', $pedido->sucursal_id)
                            ->where('producto_id', $item->producto_id)
                            ->decrement('cantidad_reservada', $item->cantidad);
                    } elseif ($ps) {
                        DB::table('producto_sucursal')
                            ->where('sucursal_id', $pedido->sucursal_id)
                            ->where('producto_id', $item->producto_id)
                            ->update(['cantidad_reservada' => 0]);
                    }

                    DB::table('movimientos_stock')->insert([
                        'producto_id'         => $item->producto_id,
                        'sucursal_id'         => $pedido->sucursal_id,
                        'user_id'             => auth()->id() ?? User::whereHas('roles', fn($q) => $q->where('name', 'SuperAdmin'))->first()?->id ?? 1,
                        'tipo_movimiento'     => 'Liberación Reserva Web',
                        'cantidad_anterior'   => $reservadaAnterior,
                        'cantidad_movimiento' => (float) $item->cantidad,
                        'cantidad_actual'     => $ps ? (float) $ps->cantidad_fisica : 0,
                        'motivo'              => "Rechazo de pago MercadoPago - pedido web #{$pedido->id}",
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);
                }
            }

            $this->paymentRecorder->recordWebhook($pedido, 'mercadopago', new \App\Services\Payment\Contracts\WebhookPayload(
                gatewayTransactionId: $status->gatewayTransactionId,
                status: $status->status,
                referenceId: $status->referenceId,
                amount: $status->amount,
                raw: $status->raw,
            ));

            return response()->json(['status' => 'ok']);
        });
    }

    private function procesarUpgradePlan(string $paymentId, Request $request): \Illuminate\Http\JsonResponse
    {
        $gateway = $this->paymentService
            ->forPlatform()
            ->gateway('mercadopago');

        if (!$gateway->verifyWebhookSignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        try {
            $status = $gateway->getPaymentStatus($paymentId);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to fetch payment from MP'], 502);
        }

        if ($status->status !== PaymentStatus::APPROVED) {
            return response()->json(['status' => 'not_approved']);
        }

        $externalRef = $status->referenceId;
        if (!$externalRef) {
            return response()->json(['error' => 'No external reference'], 400);
        }

        $comercio = Comercio::find($externalRef);
        if (!$comercio) {
            return response()->json(['error' => 'Comercio not found'], 404);
        }

        if ($this->yaAplicadaRenovacion($paymentId)) {
            return response()->json(['status' => 'already_processed']);
        }

        $plan = Plan::find($comercio->pending_plan_id);
        if (!$plan) {
            return response()->json(['status' => 'already_processed']);
        }

        $mismoPlan = $comercio->plan_id === $plan->id;

        DB::transaction(function () use ($comercio, $plan, $paymentId, $status, $mismoPlan) {
            $comercio = Comercio::lockForUpdate()->find($comercio->id);

            if ($this->yaAplicadaRenovacion($paymentId)) {
                return;
            }

            $needsReactivation = $comercio->status === 'suspendido'
                || ($comercio->vencimiento_pago && \Carbon\Carbon::parse($comercio->vencimiento_pago)->isPast());

            if ($mismoPlan) {
                if ($needsReactivation) {
                    $comercio->status = 'activo';
                    $comercio->vencimiento_pago = now()->addMonth()->toDateString();
                }

                $comercio->pending_plan_id = null;
                $comercio->save();

                activity()
                    ->performedOn($comercio)
                    ->causedByAnonymous()
                    ->withProperties(['plan_id' => $comercio->plan_id, 'payment_id' => $paymentId, 'via' => 'renovacion_mismo_plan_webhook'])
                    ->log($needsReactivation ? 'plan_reactivated_via_webhook' : 'plan_renewed_via_webhook');
            } else {
                $comercio->plan_id = $plan->id;
                $comercio->pending_plan_id = null;
                $comercio->modulos_habilitados = $plan->modulos;
                $comercio->limite_sucursales = $plan->sucursales_limit;
                $comercio->limite_usuarios = $plan->usuarios_limit;

                if ($needsReactivation) {
                    $comercio->status = 'activo';
                    $comercio->vencimiento_pago = now()->addMonth()->toDateString();
                }

                $comercio->save();

                activity()
                    ->performedOn($comercio)
                    ->causedByAnonymous()
                    ->withProperties([
                        'plan' => $plan->toArray(),
                        'via' => 'webhook',
                        'payment_id' => $paymentId,
                        'reactivated' => $needsReactivation,
                    ])
                    ->log($needsReactivation ? 'plan_reactivated_via_webhook' : 'plan_upgraded_via_webhook');
            }

            $this->registrarPagoPlan($comercio, $paymentId, (float) $status->amount);
        });

        return response()->json(['status' => $mismoPlan ? 'already_upgraded' : 'ok']);
    }

    private function yaAplicadaRenovacion(string $paymentId): bool
    {
        return Payment::query()
            ->where('provider', 'mercadopago')
            ->where('gateway_transaction_id', $paymentId)
            ->exists();
    }

    private function registrarPagoPlan(Comercio $comercio, string $paymentId, ?float $amount = null): void
    {
        Payment::firstOrCreate(
            [
                'provider' => 'mercadopago',
                'gateway_transaction_id' => $paymentId,
            ],
            [
                'payable_type' => Comercio::class,
                'payable_id' => $comercio->id,
                'channel' => PaymentChannel::API,
                'status' => PaymentStatus::APPROVED,
                'reference' => (string) $comercio->id,
                'amount' => $amount,
                'approved_at' => now(),
            ],
        );
    }
}
