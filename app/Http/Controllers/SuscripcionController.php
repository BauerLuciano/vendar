<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Comercio;
use App\Models\Plan;
use App\Services\Payment\Contracts\CheckoutRequest;
use App\Services\Payment\Exceptions\PaymentException;
use App\Services\Payment\PaymentService;
use App\Services\Suscripcion\SuscripcionService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
        // La validación va fuera del try: antes una `plan_id` inválida caía en
        // el catch genérico y el usuario recibía un 500 "Error general" en
        // lugar del 422 con el motivo real.
        $datos = $request->validate([
            // `exists` se valida abajo con un mensaje accionable. Si el plan se
            // desactivó o borró entre la carga de la pantalla y el clic, el
            // usuario tiene que ver "no está disponible", no un error de
            // validación genérico.
            'plan_id' => 'required|integer',
            'origin' => ['nullable', 'string', 'max:255'],
        ]);

        $user = auth()->user();
        $comercio = Comercio::find($user->comercio_id);

        if (! $comercio) {
            return response()->json(['error' => 'Comercio no encontrado'], 404);
        }

        $plan = Plan::find($datos['plan_id']);

        if (! $plan || ! $plan->activo) {
            return response()->json([
                'error' => 'El plan elegido ya no está disponible. Recargá la página y elegí otro.',
            ], 400);
        }

        $returnOrigin = $this->resolverOriginRetorno($request);

        if ($returnOrigin === null) {
            Log::warning('Intento de generar preferencia de suscripción con origin no permitido', [
                'user_id' => $user->id,
                'comercio_id' => $comercio->id,
                'origin' => $request->input('origin'),
                'permitidos' => $this->originRetornoPermitidos(),
            ]);

            return response()->json([
                'error' => 'No pudimos iniciar el pago porque el sitio desde el que estás accediendo no está autorizado.',
                'detalle' => app()->environment('local')
                    ? 'Agregá el origen "'.$request->input('origin').'" a MP_ALLOWED_RETURN_ORIGINS y recargá la configuración.'
                    : null,
            ], 400);
        }

        // Serializa la generación de preferencias del mismo comercio. El
        // chequeo de `pagoEnVuelo` no era atómico: dos clics simultáneos
        // podían pasar el guard antes de que el primero marcara el pago.
        $lock = Cache::lock('suscripcion:generar-pago:'.$comercio->id, 15);

        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            return response()->json([
                'error' => 'Estamos procesando otra solicitud de pago. Esperá unos segundos y volvé a intentar.',
            ], 409);
        }

        try {
            // Anti-duplicado: si ya hay una preferencia de pago en curso para
            // este comercio no generamos otra, para no cobrar dos veces por la
            // misma intención de renovación / cambio de plan. Devolvemos el
            // link existente para que el usuario pueda reanudar ese mismo pago.
            $enVuelo = $this->suscripcionService->pagoEnVuelo($comercio->id);

            if ($enVuelo !== null) {
                return response()->json([
                    'error' => 'Ya tenés un pago pendiente de confirmación. Podés reanudarlo o liberarlo antes de generar uno nuevo.',
                    'pago_en_vuelo' => $enVuelo,
                    'init_point' => $enVuelo['checkout_url'] ?? null,
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
                Log::info('Renovación anticipada bloqueada por ventana de renovación', [
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

            try {
                // El notification_url lo llama Mercado Pago desde sus
                // servidores: necesita la URL pública (ngrok en desarrollo).
                $publicUrl = $this->basePublica();

                // Las back_urls las abre el navegador del usuario. Si está
                // entrando por un dominio con nombre o por la IP de la red
                // local, volvemos por ahí; si entra por localhost usamos la
                // URL pública, porque MP no acepta localhost en back_urls.
                $backUrlBase = $this->baseBackUrls($returnOrigin, $publicUrl);

                $queryExito = http_build_query([
                    'pago' => 'exito',
                    'plan_id' => $plan->id,
                    'origin' => $returnOrigin,
                ]);
                $queryError = http_build_query([
                    'pago' => 'error',
                    'origin' => $returnOrigin,
                ]);
                $queryPendiente = http_build_query([
                    'pago' => 'pendiente',
                    'plan_id' => $plan->id,
                    'origin' => $returnOrigin,
                ]);

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
                    successUrl: $backUrlBase.'/retorno?'.$queryExito,
                    failureUrl: $backUrlBase.'/retorno?'.$queryError,
                    pendingUrl: $backUrlBase.'/retorno?'.$queryPendiente,
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

                // Guardamos el link de ESTA preferencia. "Reintentar pago"
                // reanuda el mismo checkout en lugar de crear otro.
                $this->suscripcionService->recordarCheckoutEnVuelo(
                    $comercio->id,
                    $response->checkoutUrl,
                );

                return response()->json([
                    'init_point' => $response->checkoutUrl,
                    'es_renovacion' => $esRenovacion,
                    'plan_id' => $plan->id,
                    'estado' => $estado['estado'],
                    'pago_en_vuelo' => $this->suscripcionService->pagoEnVuelo($comercio->id),
                ]);
            } catch (PaymentException $e) {
                // Si la pasarela falla, liberamos también `pending_plan_id`.
                // Antes sólo se limpiaba la caché y el pendiente quedaba sucio.
                $this->suscripcionService->liberarPagoEnVuelo($comercio, 'error_pasarela');

                Log::error('Error de Mercado Pago al generar preferencia de suscripción', [
                    'comercio_id' => $comercio->id,
                    'plan_id' => $plan->id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'error' => 'No pudimos generar el link de pago con Mercado Pago. No se realizó ningún cobro.',
                    'detalle' => app()->environment('local') ? $e->getMessage() : null,
                ], 502);
            } catch (\Throwable $e) {
                $this->suscripcionService->liberarPagoEnVuelo($comercio, 'error_generacion');

                Log::error('Error inesperado al generar preferencia de suscripción', [
                    'comercio_id' => $comercio->id,
                    'plan_id' => $plan->id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'error' => 'No pudimos iniciar el pago. No se realizó ningún cobro.',
                    'detalle' => app()->environment('local') ? $e->getMessage() : null,
                ], 500);
            }
        } finally {
            $lock->release();
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

        // Volvemos al origen desde el que el usuario inició el pago (validado
        // contra la lista permitida). Así funciona tanto en localhost como
        // entrando por la IP de la red local o por un dominio de desarrollo.
        // Si no vino el parámetro, usamos el host con el que se alcanzó esta
        // ruta y, como último recurso, APP_URL.
        $originSolicitado = $this->normalizarOrigin((string) $request->input('origin', ''));
        $originValido = $originSolicitado !== null
            && in_array($originSolicitado, $this->originRetornoPermitidos(), true);

        $base = $originValido
            ? $originSolicitado
            : rtrim((string) $request->getSchemeAndHttpHost(), '/');

        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/');
        }

        Log::info('Retorno Mercado Pago (suscripción) recibido', [
            'pago' => $pago,
            'plan_id' => $params['plan_id'] ?? null,
            'payment_id' => $paymentId,
            'origin' => $originValido ? $originSolicitado : null,
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

        if ($normalized === null) {
            return null;
        }

        if (in_array($normalized, $this->originRetornoPermitidos(), true)) {
            return $normalized;
        }

        // En desarrollo el frontend puede servirse desde un puerto distinto
        // (php artisan serve, Vite, etc.) o desde la IP de la red local. Sin
        // esta tolerancia, el usuario veía "origin no permitido" incluso
        // estando en la misma máquina. En producción la lista es estricta.
        if ($this->esOriginDeDesarrollo($normalized)) {
            return $normalized;
        }

        return null;
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

    /**
     * Orígenes aceptados sólo fuera de producción: loopback en cualquier
     * puerto, dominios *.test / *.local y direcciones IP privadas (red local).
     */
    private function esOriginDeDesarrollo(string $origin): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        $host = strtolower((string) parse_url($origin, PHP_URL_HOST));

        if ($host === '') {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        if (str_ends_with($host, '.test') || str_ends_with($host, '.local')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );
        }

        return false;
    }

    /**
     * Base pública para el webhook (Mercado Pago la llama desde sus
     * servidores). Nunca queda vacía: si MP_PUBLIC_URL no está definida se usa
     * APP_URL y, en último caso, localhost.
     */
    private function basePublica(): string
    {
        $url = trim((string) config('services.mercadopago.public_url'));

        if ($url === '') {
            $url = trim((string) config('app.url'));
        }

        if ($url === '') {
            $url = 'http://localhost';
        }

        return rtrim($url, '/');
    }

    /**
     * Base para las back_urls que abre el navegador. Mercado Pago no acepta
     * localhost/127.0.0.1, así que en ese caso usamos la URL pública. Si el
     * usuario entró por un dominio con nombre o por una IP de la red local,
     * volvemos por ese mismo origen, que sí es alcanzable desde su navegador.
     */
    private function baseBackUrls(string $returnOrigin, string $publicUrl): string
    {
        $host = strtolower((string) parse_url($returnOrigin, PHP_URL_HOST));

        if ($host !== '' && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return $returnOrigin;
        }

        return $publicUrl !== '' ? $publicUrl : $returnOrigin;
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
