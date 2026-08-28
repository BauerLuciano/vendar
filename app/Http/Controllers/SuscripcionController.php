<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Comercio;
use App\Models\Plan;
use App\Services\Payment\Contracts\CheckoutRequest;
use App\Services\Payment\Exceptions\PaymentException;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SuscripcionController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

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

            if (! $comercio) {
                return response()->json(['error' => 'Comercio no encontrado'], 404);
            }

            $plan = Plan::findOrFail($request->plan_id);

            if (! $plan->activo) {
                return response()->json(['error' => 'Plan no disponible'], 400);
            }

            $comercio->update(['pending_plan_id' => $plan->id]);

            $checkoutRequest = new CheckoutRequest(
                referenceId: (string) $comercio->id,
                amount: (float) $plan->precio_mensual,
                title: 'VendAR: '.$plan->nombre,
                description: 'Plan '.$plan->nombre.' - VendAR',
                items: [[
                    'id' => 'plan-'.$plan->id,
                    'title' => 'VendAR: '.$plan->nombre,
                    'quantity' => 1,
                    'unit_price' => (float) $plan->precio_mensual,
                    'currency_id' => 'ARS',
                ]],
                successUrl: config('services.mercadopago.public_url').'/mi-plan?pago=exito&plan_id='.$plan->id,
                failureUrl: config('services.mercadopago.public_url').'/mi-plan?pago=error',
                pendingUrl: config('services.mercadopago.public_url').'/mi-plan?pago=pendiente',
                notificationUrl: config('services.mercadopago.public_url').'/api/mercadopago/notificacion?tipo=plan',
            );

            $response = $this->paymentService
                ->forPlatform()
                ->createCheckout('mercadopago', $checkoutRequest);

            return response()->json([
                'init_point' => $response->checkoutUrl,
            ]);

        } catch (PaymentException $e) {
            return response()->json([
                'error' => 'Error de pasarela de pago',
                'detalle' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error general',
                'detalle' => $e->getMessage(),
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

        if ((int) $comercio->pending_plan_id !== (int) $request->plan_id) {
            \Log::warning('Intento de upgrade con plan_id no coincidente', [
                'user_id' => $user->id,
                'comercio_id' => $comercio->id,
                'requested_plan_id' => $request->plan_id,
                'pending_plan_id' => $comercio->pending_plan_id,
            ]);

            return response()->json([
                'error' => 'El plan solicitado no coincide con la intención de pago. Generá una nueva preferencia.',
            ], 400);
        }

        try {
            $status = $this->paymentService
                ->forPlatform()
                ->getPaymentStatus('mercadopago', $request->payment_id);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'No se pudo verificar el pago'], 502);
        }

        if ($status->status !== PaymentStatus::APPROVED) {
            return response()->json(['error' => 'El pago no está aprobado'], 400);
        }

        if ($status->referenceId !== (string) $comercio->id) {
            return response()->json(['error' => 'El pago no corresponde a este comercio'], 403);
        }

        $plan = Plan::findOrFail($request->plan_id);

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

        if (! $comercio) {
            return response()->json(['plan_id' => null]);
        }

        return response()->json([
            'plan_id' => $comercio->plan_id,
            'pending_plan_id' => $comercio->pending_plan_id,
        ]);
    }
}
