<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Comercio;
use App\Models\PedidoWeb;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
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

        // --- Plan upgrade flow ---
        if ($request->input('tipo') === 'plan') {
            return $this->procesarUpgradePlan($paymentId);
        }

        // --- Pedido web flow (existing) ---
        $comercio = Comercio::find($request->input('comercio_id'));
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

    private function procesarUpgradePlan(string $paymentId)
    {
        $token = trim(env('MERCADOPAGO_ACCESS_TOKEN'));

        $response = Http::withToken($token)
            ->get("https://api.mercadopago.com/v1/payments/{$paymentId}");

        if (!$response->successful()) {
            return response()->json(['error' => 'Failed to fetch payment from MP'], 502);
        }

        $payment = $response->json();

        if (($payment['status'] ?? null) !== 'approved') {
            return response()->json(['status' => 'not_approved']);
        }

        $externalRef = $payment['external_reference'] ?? null;
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

        // Idempotent: already upgraded
        if ($comercio->plan_id === $plan->id) {
            $comercio->update(['pending_plan_id' => null]);
            return response()->json(['status' => 'already_upgraded']);
        }

        DB::transaction(function () use ($comercio, $plan) {
            $comercio = Comercio::lockForUpdate()->find($comercio->id);

            $comercio->update([
                'plan_id' => $plan->id,
                'pending_plan_id' => null,
                'modulos_habilitados' => $plan->modulos,
                'limite_sucursales' => $plan->sucursales_limit,
                'limite_usuarios' => $plan->usuarios_limit,
            ]);

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
