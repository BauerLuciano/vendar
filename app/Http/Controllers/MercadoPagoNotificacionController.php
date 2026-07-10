<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use Illuminate\Http\Request;
use App\Models\Comercio;
use App\Models\PedidoWeb;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use App\Services\Payment\PaymentService;
use App\Services\Payment\PaymentRecorder;

class MercadoPagoNotificacionController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaymentRecorder $paymentRecorder,
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

        $pedido = PedidoWeb::where('comercio_id', $comercio->id)->findOrFail($externalRef);

        if ($pedido->pasarela_payment_id === $paymentId && $pedido->estado_pago === 'pagado') {
            return response()->json(['status' => 'already_processed']);
        }

        $pedido->pasarela_payment_id = $paymentId;
        $estadoPagoAnterior = $pedido->estado_pago;

        if ($status->status === PaymentStatus::APPROVED) {
            $pedido->estado_pago = 'pagado';
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

        $plan = Plan::find($comercio->pending_plan_id);
        if (!$plan) {
            return response()->json(['error' => 'No pending plan upgrade found'], 400);
        }

        if ($comercio->plan_id === $plan->id) {
            $comercio->pending_plan_id = null;
            $comercio->save();
            return response()->json(['status' => 'already_upgraded']);
        }

        DB::transaction(function () use ($comercio, $plan, $paymentId) {
            $comercio = Comercio::lockForUpdate()->find($comercio->id);

            $comercio->plan_id = $plan->id;
            $comercio->pending_plan_id = null;
            $comercio->modulos_habilitados = $plan->modulos;
            $comercio->limite_sucursales = $plan->sucursales_limit;
            $comercio->limite_usuarios = $plan->usuarios_limit;
            $comercio->save();

            activity()
                ->performedOn($comercio)
                ->causedByAnonymous()
                ->withProperties([
                    'plan' => $plan->toArray(),
                    'via' => 'webhook',
                    'payment_id' => $paymentId,
                ])
                ->log('plan_upgraded_via_webhook');
        });

        return response()->json(['status' => 'ok']);
    }
}
