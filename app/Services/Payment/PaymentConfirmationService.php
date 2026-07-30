<?php

namespace App\Services\Payment;

use App\Enums\PaymentStatus;
use App\Models\PedidoWeb;
use App\Services\Payment\Contracts\WebhookPayload;
use Illuminate\Support\Facades\DB;

class PaymentConfirmationService
{
    public function __construct(
        private readonly PaymentRecorder $paymentRecorder,
    ) {}

    public function approve(PedidoWeb $pedido, string $paymentId, string $confirmedBy = 'webhook'): void
    {
        DB::transaction(function () use ($pedido, $paymentId, $confirmedBy) {
            $pedido = PedidoWeb::lockForUpdate()->findOrFail($pedido->id);
            $pedido->load('items');

            if ($pedido->estado_pago === 'pagado') {
                return;
            }

            $pedido->pasarela_payment_id = $paymentId;
            $pedido->estado_pago = 'pagado';
            $pedido->save();

            $this->paymentRecorder->recordWebhook(
                $pedido,
                'mercadopago',
                new WebhookPayload(
                    gatewayTransactionId: $paymentId,
                    status: PaymentStatus::APPROVED,
                    referenceId: (string) $pedido->id,
                    amount: (float) $pedido->total,
                    raw: [
                        'confirmed_by' => $confirmedBy,
                        'pedido_id'    => $pedido->id,
                    ],
                ),
            );
        });
    }
}
