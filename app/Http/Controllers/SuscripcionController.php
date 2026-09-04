<?php

namespace App\Http\Controllers;

use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Models\Comercio;
use App\Models\Payment;
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
            $request->validate([
                'plan_id' => 'required|exists:planes,id',
                'origin' => ['nullable', 'string', 'max:255'],
            ]);

            $user = auth()->user();
            $comercio = Comercio::find($user->comercio_id);

            if (! $comercio) {
                return response()->json(['error' => 'Comercio no encontrado'], 404);
            }

            $plan = Plan::findOrFail($request->plan_id);

            if (! $plan->activo) {
                return response()->json(['error' => 'Plan no disponible'], 400);
            }

            $returnOrigin = $this->resolverOriginRetorno($request);

            if ($returnOrigin === null) {
                \Log::warning('Intento de generar preferencia de suscripción con origin no permitido', [
                    'user_id' => $user->id,
                    'comercio_id' => $comercio->id,
                    'origin' => $request->input('origin'),
                ]);

                return response()->json(['error' => 'Origin no permitido'], 400);
            }

            $comercio->update(['pending_plan_id' => $plan->id]);

            // Las back_urls deben ser un dominio con nombre (DNS) para que
            // Mercado Pago las acepte y habilite el botón "Volver al sitio" /
            // auto_return. localhost queda descartado por MP (documentación
            // oficial de Checkout Pro). Apuntamos al retorno público definido
            // en MP_PUBLIC_URL, que redirige de vuelta a la app.
            $publicUrl = rtrim((string) config('services.mercadopago.public_url', ''), '/');

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
                successUrl: $publicUrl.'/retorno?pago=exito&plan_id='.$plan->id,
                failureUrl: $publicUrl.'/retorno?pago=error',
                pendingUrl: $publicUrl.'/retorno?pago=pendiente',
                notificationUrl: $publicUrl.'/api/mercadopago/notificacion?tipo=plan',
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
            $reactivada = false;

            if ($comercio->status === 'suspendido'
                || ($comercio->vencimiento_pago && \Carbon\Carbon::parse($comercio->vencimiento_pago)->isPast())) {
                $comercio->status = 'activo';
                $comercio->vencimiento_pago = now()->addMonth()->toDateString();
                $reactivada = true;
            }

            $comercio->pending_plan_id = null;
            $comercio->save();

            if ($reactivada) {
                activity()
                    ->performedOn($comercio)
                    ->causedBy(auth()->user())
                    ->withProperties(['plan_id' => $comercio->plan_id, 'via' => 'renovacion_mismo_plan'])
                    ->log('plan_reactivated');
            }

            $this->registrarPagoRenovacion($comercio->id, (string) $request->payment_id);

            return response()->json([
                'status' => 'already_upgraded',
                'plan_id' => $comercio->plan_id,
                'plan' => Plan::find($comercio->plan_id),
            ]);
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

            $needsReactivation = $comercio->status === 'suspendido'
                || ($comercio->vencimiento_pago && \Carbon\Carbon::parse($comercio->vencimiento_pago)->isPast());

            if ($needsReactivation) {
                $comercio->status = 'activo';
                $comercio->vencimiento_pago = now()->addMonth()->toDateString();
            }

            $comercio->save();

            activity()
                ->performedOn($comercio)
                ->causedBy(auth()->user())
                ->withProperties([
                    'plan' => $plan->toArray(),
                    'via' => 'confirmar_upgrade',
                    'reactivated' => $needsReactivation,
                ])
                ->log($needsReactivation ? 'plan_reactivated' : 'plan_upgraded');
        });

        $this->registrarPagoRenovacion($comercio->id, (string) $request->payment_id);

        return response()->json([
            'status' => 'ok',
            'plan' => $plan,
        ]);
    }

    public function retorno(Request $request)
    {
        $statusMP = strtolower((string) (
            $request->input('status')
            ?? $request->input('collection_status')
            ?? $request->input('pago')
            ?? ''
        ));

        $pago = match ($statusMP) {
            'approved', 'success', 'exito' => 'exito',
            'rejected', 'cancelled', 'error' => 'error',
            'pending', 'in_process', 'pendiente' => 'pendiente',
            default => (string) ($request->input('pago', 'exito')),
        };

        $params = ['pago' => $pago];

        if ($request->filled('plan_id')) {
            $params['plan_id'] = $request->integer('plan_id');
        }

        $paymentId = (string) ($request->input('payment_id') ?? $request->input('collection_id') ?? '');
        if ($paymentId !== '') {
            $params['payment_id'] = $paymentId;
        }

        $base = rtrim((string) config('app.url'), '/');

        \Log::info('Retorno Mercado Pago (suscripción) recibido', [
            'pago' => $pago,
            'plan_id' => $params['plan_id'] ?? null,
            'payment_id' => $paymentId,
            'parametros' => $request->query(),
        ]);

        $destino = $base.'/mi-plan'.(count($params) > 0 ? '?'.http_build_query($params) : '');

        return redirect()->away($destino);
    }

    private function registrarPagoRenovacion(int $comercioId, string $paymentId, ?float $amount = null): void
    {
        Payment::firstOrCreate(
            [
                'provider' => 'mercadopago',
                'gateway_transaction_id' => $paymentId,
            ],
            [
                'payable_type' => Comercio::class,
                'payable_id' => $comercioId,
                'channel' => PaymentChannel::API,
                'status' => PaymentStatus::APPROVED,
                'reference' => (string) $comercioId,
                'amount' => $amount,
                'approved_at' => now(),
            ],
        );
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

    private function resolverOriginRetorno(Request $request): ?string
    {
        $raw = trim((string) $request->input('origin', ''));

        $origin = ($raw !== '') ? $raw : (string) config('app.url');
        $normalized = $this->normalizarOrigin($origin);

        if ($normalized === null || ! in_array($normalized, $this->originRetornoPermitidos(), true)) {
            return null;
        }

        return $normalized;
    }

    private function originRetornoPermitidos(): array
    {
        $raw = array_merge(
            (array) config('services.mercadopago.allowed_return_origins', []),
            (array) config('app.url'),
            (array) config('services.mercadopago.public_url'),
        );

        $normalized = [];

        foreach ($raw as $origin) {
            $value = $this->normalizarOrigin((string) $origin);

            if ($value !== null) {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function normalizarOrigin(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $parts = parse_url($value);

        if (! $parts || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = null;
        }

        return $scheme.'://'.$host.($port !== null ? ':'.$port : '');
    }
}
