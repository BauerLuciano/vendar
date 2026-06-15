<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\PedidoWeb;
use App\Models\Comercio;
use App\Services\Payment\PaymentService;
use App\Services\Payment\PaymentRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ViumiWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaymentRecorder $paymentRecorder,
    ) {}

    public function __invoke(Request $request)
    {
        $body = $request->input('data');
        $orderUuid = $body['order']['uuid'] ?? null;

        if (!$orderUuid) {
            return response()->json(['error' => 'Missing order UUID'], 400);
        }

        $pedido = PedidoWeb::where('pasarela_payment_id', $orderUuid)->first();

        if (!$pedido) {
            Log::warning('viüMi webhook: pedido no encontrado', ['uuid' => $orderUuid]);
            return response()->json(['error' => 'Order not found'], 404);
        }

        if ($pedido->estado_pago === 'pagado') {
            return response()->json(['status' => 'already_processed']);
        }

        $gateway = $this->paymentService
            ->forCommerce($pedido->comercio)
            ->gateway('viumi');

        try {
            $payload = $gateway->parseWebhookPayload($request);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $pedido->pasarela_payment_id = $payload->gatewayTransactionId;

        match ($payload->status) {
            PaymentStatus::APPROVED => $pedido->estado_pago = 'pagado',
            PaymentStatus::REJECTED => $pedido->estado_pago = 'rechazado',
            default => null,
        };

        $pedido->save();

        $this->paymentRecorder->recordWebhook($pedido, 'viumi', $payload);

        activity()
            ->performedOn($pedido)
            ->causedByAnonymous()
            ->withProperties([
                'via' => 'webhook_viumi',
                'payment_id' => $payload->gatewayTransactionId,
                'status' => $payload->status->value,
            ])
            ->log('pedido_actualizado_via_webhook');

        return response()->json(['status' => 'ok']);
    }
}
