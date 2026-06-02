<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PedidoWeb;
use Illuminate\Support\Facades\Http;

class MercadoPagoNotificacionController extends Controller
{
    public function notificacion(Request $request)
    {
        $topic = $request->input('topic');
        $paymentId = $request->input('data.id') ?? $request->input('id');

        if ($topic !== 'payment' || !$paymentId) {
            return response()->json(['error' => 'Invalid notification'], 400);
        }

        $comercio = \App\Models\Comercio::find($request->input('comercio_id'));
        if (!$comercio || !$comercio->mp_access_token) {
            return response()->json(['error' => 'Comercio or MP token not found'], 404);
        }

        $response = Http::withToken($comercio->mp_access_token)
            ->get("https://api.mercadopago.com/v1/payments/{$paymentId}");

        if (!$response->successful()) {
            return response()->json(['error' => 'Failed to fetch payment from MP'], 502);
        }

        $payment = $response->json();
        $externalRef = $payment['external_reference'] ?? null;
        $status = $payment['status'] ?? null;

        if (!$externalRef) {
            return response()->json(['error' => 'No external reference in payment'], 400);
        }

        $pedido = PedidoWeb::findOrFail($externalRef);

        if ($pedido->pasarela_payment_id === $paymentId && $pedido->estado_pago === 'pagado') {
            return response()->json(['status' => 'already_processed']);
        }

        $updateData = ['pasarela_payment_id' => $paymentId];

        if ($status === 'approved') {
            $updateData['estado_pago'] = 'pagado';
        } elseif (in_array($status, ['rejected', 'cancelled', 'refunded'])) {
            $updateData['estado_pago'] = 'rechazado';
        }

        $pedido->update($updateData);

        return response()->json(['status' => 'ok']);
    }
}
