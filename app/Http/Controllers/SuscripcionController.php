<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Comercio;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

class SuscripcionController extends Controller
{
    public function miPlan()
    {
        $user = auth()->user();
        $comercio = Comercio::with('plan')->find($user->comercio_id);

        $planes = Plan::where('activo', true)
            ->orderBy('orden')
            ->orderBy('precio_mensual')
            ->get();

        return Inertia::render('Suscripcion/MiPlan', [
            'comercio' => $comercio,
            'planes' => $planes,
        ]);
    }

    public function generarPreferencia(Request $request)
    {
        try {
            $request->validate(['plan_id' => 'required|exists:planes,id']);

            $user = auth()->user();
            $comercio = Comercio::find($user->comercio_id);

            if (!$comercio) {
                return response()->json(['error' => 'Comercio no encontrado'], 404);
            }

            $plan = Plan::findOrFail($request->plan_id);

            if (!$plan->activo) {
                return response()->json(['error' => 'Plan no disponible'], 400);
            }

            // Guardar intención de upgrade
            $comercio->update(['pending_plan_id' => $plan->id]);

            $token = trim(config('services.mercadopago.access_token'));
            MercadoPagoConfig::setAccessToken($token);

            if (app()->environment('local')) {
                MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
            }

            $client = new PreferenceClient();
            $baseUrl = config('app.url');

            $preference = $client->create([
                "items" => [
                    [
                        "id" => "plan-" . $plan->id,
                        "title" => "VendAR: " . $plan->nombre,
                        "quantity" => 1,
                        "unit_price" => (float) $plan->precio_mensual,
                        "currency_id" => "ARS"
                    ]
                ],
                "back_urls" => [
                    "success" => "{$baseUrl}/mi-plan?pago=exito&plan_id={$plan->id}",
                    "failure" => "{$baseUrl}/mi-plan?pago=error",
                    "pending" => "{$baseUrl}/mi-plan?pago=pendiente",
                ],
                "auto_return" => "approved",
                "external_reference" => (string) $comercio->id,
                "notification_url" => url('/api/mercadopago/notificacion?tipo=plan'),
                "binary_mode" => true,
            ]);

            return response()->json([
                'init_point' => $preference->init_point
            ]);

        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            return response()->json([
                'error' => 'Error de API MP',
                'detalle_real_mp' => $e->getApiResponse()->getContent()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error general',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }

    public function confirmarUpgrade(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:planes,id',
            'payment_id' => 'required|string',
        ]);

        $user = auth()->user();
        $comercio = Comercio::findOrFail($user->comercio_id);

        if ($comercio->plan_id === (int) $request->plan_id) {
            return response()->json(['status' => 'already_upgraded', 'plan_id' => $comercio->plan_id]);
        }

        // Validar que el plan solicitado coincida con la intención de compra activa
        if ((int) $comercio->pending_plan_id !== (int) $request->plan_id) {
            \Log::warning('Intento de upgrade con plan_id no coincidente', [
                'user_id' => $user->id,
                'comercio_id' => $comercio->id,
                'requested_plan_id' => $request->plan_id,
                'pending_plan_id' => $comercio->pending_plan_id,
            ]);
            return response()->json([
                'error' => 'El plan solicitado no coincide con la intención de pago. Generá una nueva preferencia.'
            ], 400);
        }

        // Validar pago contra MP API
        $token = trim(config('services.mercadopago.access_token'));
        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->get("https://api.mercadopago.com/v1/payments/{$request->payment_id}");

        if (!$response->successful()) {
            return response()->json(['error' => 'No se pudo verificar el pago'], 502);
        }

        $payment = $response->json();

        if ($payment['status'] !== 'approved') {
            return response()->json(['error' => 'El pago no está aprobado'], 400);
        }

        // Verificar que el pago corresponda a este comercio
        if ((string) $payment['external_reference'] !== (string) $comercio->id) {
            return response()->json(['error' => 'El pago no corresponde a este comercio'], 403);
        }

        $plan = Plan::findOrFail($request->plan_id);

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
                ->causedBy(auth()->user())
                ->withProperties(['plan' => $plan->toArray(), 'via' => 'confirmar_upgrade'])
                ->log('plan_upgraded');
        });

        return response()->json([
            'status' => 'ok',
            'plan' => $plan,
        ]);
    }

    public function planActual()
    {
        $user = auth()->user();
        $comercio = Comercio::find($user->comercio_id);

        if (!$comercio) {
            return response()->json(['plan_id' => null]);
        }

        return response()->json([
            'plan_id' => $comercio->plan_id,
            'pending_plan_id' => $comercio->pending_plan_id,
        ]);
    }
}
