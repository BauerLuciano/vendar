<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Comercio;
use App\Models\Plan;
use App\Services\Payment\Contracts\CheckoutRequest;
use App\Services\Payment\Exceptions\PaymentException;
use App\Services\Payment\PaymentService;
use App\Services\Suscripcion\SuscripcionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SuscripcionController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly SuscripcionService $suscripcionService,
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
            'suscripcion' => $this->suscripcionService->estadoSuscripcion($comercio),
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

            // Anti-duplicado: si ya hay una preferencia de pago en curso para
            // este comercio no generamos otra, para no cobrar dos veces por la
            // misma intención de renovación / cambio de plan.
            if ($this->suscripcionService->pagoEnVuelo($comercio->id) !== null) {
                return response()->json([
                    'error' => 'Ya hay un pago en proceso para tu plan. Espera la confirmación de Mercado Pago antes de generar otro.',
                ], 409);
            }

            $esRenovacion = (int) $comercio->plan_id === (int) $plan->id;
            $estado = $this->suscripcionService->estadoSuscripcion($comercio);

            // Renovación anticipada acotada a una ventana de 30 días.
            //
            // Antes el botón "Renovar ahora" estaba habilitado siempre y acá
            // no había ninguna validación, así que un comercio podía
            // acumular meses pagados por adelantado al precio vigente. La
            // ventana se aplica únicamente a la renovación del MISMO plan:
            // cambiar de plan (upgrade) y la facturación de la administración
            // global no pasan por acá y quedan intactas.
            if ($esRenovacion && ! $this->suscripcionService->puedeRenovarAhora($comercio)) {
                \Log::info('Renovación anticipada bloqueada por ventana de renovación', [
                    'user_id' => $user->id,
                    'comercio_id' => $comercio->id,
                    'plan_id' => $plan->id,
                    'dias_restantes' => $estado['dias_restantes'],
                    'dias_para_renovar' => $estado['dias_para_renovar'],
                ]);

                $diasParaRenovar = $estado['dias_para_renovar'];

                return response()->json([
                    'error' => 'Tu plan se renueva solo y ya está pagado hasta el '
                        .$estado['vencimiento_pago'].'. '
                        .'La renovación anticipada se habilita en '.$diasParaRenovar.' '
                        .($diasParaRenovar === 1 ? 'día' : 'días').'.',
                    'dias_para_renovar' => $diasParaRenovar,
                    'ventana_renovacion_dias' => SuscripcionService::VENTANA_RENOVACION_DIAS,
                ], 422);
            }

            $comercio->update(['pending_plan_id' => $plan->id]);
            $this->suscripcionService->marcarPagoEnVuelo($comercio->id, $plan->id);

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
                metadata: [
                    'tipo' => 'suscripcion',
                    'intento' => $esRenovacion ? 'renovacion' : 'upgrade',
                    'plan_id' => $plan->id,
                    'estado' => $estado['estado'],
                ],
            );

            $response = $this->paymentService
                ->forPlatform()
                ->createCheckout('mercadopago', $checkoutRequest);

            return response()->json([
                'init_point' => $response->checkoutUrl,
                'es_renovacion' => $esRenovacion,
                'plan_id' => $plan->id,
                'estado' => $estado['estado'],
            ]);

        } catch (PaymentException $e) {
            $this->suscripcionService->limpiarPagoEnVuelo($comercio->id ?? 0);

            return response()->json([
                'error' => 'Error de pasarela de pago',
                'detalle' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            $this->suscripcionService->limpiarPagoEnVuelo($comercio->id ?? 0);

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
        $plan = Plan::findOrFail($request->plan_id);
        $paymentId = (string) $request->payment_id;

        // La verificación contra Mercado Pago se hace SIEMPRE, también cuando
        // la renovación es del mismo plan. Sin esto, un `plan_id` propio junto
        // con un `payment_id` inventado alcanzaba para reactivar la cuenta y
        // alikejar el vencimiento sin haber pagado.
        try {
            $status = $this->paymentService
                ->forPlatform()
                ->getPaymentStatus('mercadopago', $paymentId);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'No se pudo verificar el pago'], 502);
        }

        if ($status->status !== PaymentStatus::APPROVED) {
            return response()->json(['error' => 'El pago no está aprobado'], 400);
        }

        if ($status->referenceId !== (string) $comercio->id) {
            return response()->json(['error' => 'El pago no corresponde a este comercio'], 403);
        }

        // Si el webhook ya aplicó este pago, `pending_plan_id` ya fue limpiado.
        // Solo exigimos que coincida cuando el pago todavía no se aplicó, para
        // no romper la carrera entre el webhook y la confirmación del frontend.
        $yaAplicado = $this->suscripcionService->pagoYaRegistrado($paymentId);

        if (! $yaAplicado && (int) $comercio->pending_plan_id !== (int) $plan->id) {
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

        $intento = $this->suscripcionService->aplicarPago(
            comercio: $comercio,
            plan: $plan,
            paymentId: $paymentId,
            amount: $status->amount,
            via: 'confirmar_upgrade',
        );

        $comercio->refresh();

        return response()->json([
            'status' => $intento === 'renovacion' ? 'already_upgraded' : 'ok',
            'intento' => $intento,
            'plan_id' => $comercio->plan_id,
            'plan' => Plan::find($comercio->plan_id),
            'suscripcion' => $this->suscripcionService->estadoSuscripcion($comercio),
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

    /**
     * Libera el pago en vuelo: descarta la preferencia de Mercado Pago que
     * quedó abierta y limpia `pending_plan_id`.
     *
     * Es la salida para el caso "el usuario seleccionó un plan por error y
     * nunca pagó". Sin esto quedaba bloqueado con un 409 durante toda la
     * ventana de 30 minutos sin poder reintentar.
     */
    public function cancelarPago()
    {
        $user = auth()->user();
        $comercio = Comercio::findOrFail($user->comercio_id);

        $this->suscripcionService->liberarPagoEnVuelo($comercio, 'cancelado_por_usuario');

        $comercio->refresh();

        return response()->json([
            'status' => 'ok',
            'suscripcion' => $this->suscripcionService->estadoSuscripcion($comercio),
        ]);
    }

    public function planActual()
    {
        $user = auth()->user();
        $comercio = Comercio::find($user->comercio_id);

        if (! $comercio) {
            return response()->json(['plan_id' => null, 'pending_plan_id' => null]);
        }

        return response()->json([
            'plan_id' => $comercio->plan_id,
            'pending_plan_id' => $comercio->pending_plan_id,
            'suscripcion' => $this->suscripcionService->estadoSuscripcion($comercio),
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
