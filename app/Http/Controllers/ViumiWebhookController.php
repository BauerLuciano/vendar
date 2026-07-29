<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\PedidoWeb;
use App\Models\Comercio;
use App\Services\Payment\PaymentService;
use App\Services\Payment\PaymentRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $gateway = $this->paymentService
            ->forCommerce($pedido->comercio)
            ->gateway('viumi');

        try {
            $payload = $gateway->parseWebhookPayload($request);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $resultado = DB::transaction(function () use ($pedido, $payload) {
            $pedido = PedidoWeb::lockForUpdate()->find($pedido->id);

            if ($pedido->estado_pago === 'pagado') {
                return 'already_processed';
            }

            $pedido->pasarela_payment_id = $payload->gatewayTransactionId;
            $estadoPagoAnterior = $pedido->estado_pago;

            match ($payload->status) {
                PaymentStatus::APPROVED => $pedido->estado_pago = 'pagado',
                PaymentStatus::REJECTED => $pedido->estado_pago = 'rechazado',
                default => null,
            };

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

                    DB::table('movimientos_stock')->insert([
                        'producto_id'       => $item->producto_id,
                        'sucursal_id'       => $pedido->sucursal_id,
                        'user_id'           => $pedido->user_id ?? 1,
                        'tipo_movimiento'   => 'Liberación Reserva',
                        'cantidad_anterior' => $ps ? $ps->cantidad_fisica : 0,
                        'cantidad_movimiento' => 0,
                        'cantidad_actual'   => $ps ? $ps->cantidad_fisica : 0,
                        'motivo'            => "Pago rechazado - Pedido web #{$pedido->id} (Viumi)",
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);
                }
            }

            return 'ok';
        });

        if ($resultado === 'already_processed') {
            return response()->json(['status' => 'already_processed']);
        }

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
