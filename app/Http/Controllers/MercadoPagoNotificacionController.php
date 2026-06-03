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
        $paymentId = $request->input('data.id') ?? $request->input('id');

        if (!$this->verificarFirma($request, $paymentId)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $topic = $request->input('topic');

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

        $pedido = PedidoWeb::where('comercio_id', $comercio->id)->findOrFail($externalRef);

        if ($pedido->pasarela_payment_id === $paymentId && $pedido->estado_pago === 'pagado') {
            return response()->json(['status' => 'already_processed']);
        }

        $pedido->pasarela_payment_id = $paymentId;

        if ($status === 'approved') {
            $pedido->estado_pago = 'pagado';
        } elseif (in_array($status, ['rejected', 'cancelled', 'refunded'])) {
            $pedido->estado_pago = 'rechazado';
        }

        $pedido->save();

        return response()->json(['status' => 'ok']);
    }

    private function verificarFirma(Request $request, ?string $paymentId): bool
    {
        $secret = config('services.mercadopago.webhook_secret');

        if (!$secret) {
            if (app()->environment('production')) {
                \Log::critical('MercadoPago webhook secret is missing in production — rejecting webhook');
                return false;
            }
            \Log::warning('MercadoPago webhook secret not configured — skipping signature verification');
            return true;
        }

        $signature = $request->header('X-Signature');
        if (!$signature) {
            \Log::warning('MercadoPago webhook rejected: missing X-Signature header');
            return false;
        }

        $parts = [];
        foreach (explode(',', $signature) as $part) {
            $segments = explode('=', $part, 2);
            if (count($segments) === 2) {
                $parts[trim($segments[0])] = trim($segments[1]);
            }
        }

        $ts = $parts['ts'] ?? null;
        $v1 = $parts['v1'] ?? null;

        if (!$ts || !$v1 || !$paymentId) {
            \Log::warning('MercadoPago webhook rejected: missing ts, v1, or paymentId');
            return false;
        }

        // Anti-replay: reject timestamps older than 5 minutes
        $age = abs(time() - (int) $ts);
        if ($age > 300) {
            \Log::warning('MercadoPago webhook rejected: timestamp too old', ['ts' => $ts, 'age' => $age]);
            return false;
        }

        $payload = "{$paymentId}|{$ts}|{$secret}";
        $expected = hash_hmac('sha256', $payload, $secret);

        $valid = hash_equals($expected, $v1);
        if (!$valid) {
            \Log::warning('MercadoPago webhook rejected: invalid signature');
        }
        return $valid;
    }

    private function procesarUpgradePlan(string $paymentId)
    {
        $token = trim(config('services.mercadopago.access_token'));

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
            $comercio->pending_plan_id = null;
            $comercio->save();
            return response()->json(['status' => 'already_upgraded']);
        }

        DB::transaction(function () use ($comercio, $plan) {
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
