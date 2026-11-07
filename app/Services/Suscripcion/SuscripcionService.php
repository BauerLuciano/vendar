<?php

namespace App\Services\Suscripcion;

use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Models\Comercio;
use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Única fuente de verdad sobre el estado y la renovación de la suscripción del
 * comercio. Tanto la confirmación HTTP (`suscripcion.confirmar-upgrade`) como
 * el webhook de Mercado Pago pasan por acá, de modo que ambos caminos apliquen
 * exactamente la misma fórmula de vencimiento y no puedan divergir.
 */
class SuscripcionService
{
    /**
     * Días restantes a partir de los cuales la suscripción se considera
     * "próxima a vencer" (mismo umbral que usa el banner del Dashboard).
     */
    public const UMBRAL_AVISO_DIAS = 10;

    /**
     * Ventana de renovación anticipada: con esta cantidad de días o menos
     * restantes el comercio ya puede renovar su plan.
     *
     * Antes de existir esta constante el botón "Renovar ahora" estaba
     * habilitado en cualquier momento, lo que permitía acumular meses
     * pagados por adelantado al precio vigente. La ventana se aplica sólo a
     * la renovación del mismo plan: cambiar de plan (upgrade) no se toca.
     */
    public const VENTANA_RENOVACION_DIAS = 30;

    /**
     * Ventana durante la cual no se permite generar una segunda preferencia
     * para el mismo comercio, para evitar pagos duplicados.
     */
    public const MINUTOS_PAGO_EN_VUELO = 30;

    /**
     * Estado de la suscripción, calculado en servidor. El frontend nunca
     * debería deducir esto por su cuenta desde `vencimiento_pago`.
     *
     * @return array{
     *     estado: string,
     *     dias_restantes: int|null,
     *     vencimiento_pago: string|null,
     *     puede_renovar: bool,
     *     dias_para_renovar: int|null,
     *     ventana_renovacion_dias: int,
     *     suspendido: bool,
     *     plan_id: int|null,
     *     pago_en_vuelo: array{plan_id: int, expira_en_minutos: int, checkout_url: string|null}|null
     * }
     */
    public function estadoSuscripcion(?Comercio $comercio): array
    {
        if (! $comercio) {
            return $this->estadoVacio();
        }

        $pagoEnVuelo = $this->pagoEnVuelo($comercio->id);

        $suspendido = $comercio->status === 'suspendido';

        if (! $comercio->vencimiento_pago) {
            return [
                'estado' => 'sin_vencimiento',
                'dias_restantes' => null,
                'vencimiento_pago' => null,
                // Sin vencimiento sólo renueva quien puede quedarse sin
                // operar: una cuenta suspendida.
                'puede_renovar' => $suspendido,
                'dias_para_renovar' => null,
                'ventana_renovacion_dias' => self::VENTANA_RENOVACION_DIAS,
                'suspendido' => $suspendido,
                'plan_id' => $comercio->plan_id,
                'pago_en_vuelo' => $pagoEnVuelo,
            ];
        }

        // Comparación por día completo, igual que el banner del Dashboard.
        $vencimiento = Carbon::parse($comercio->vencimiento_pago)->startOfDay();
        $diasRestantes = $this->diasRestantes($vencimiento);

        $estado = match (true) {
            $diasRestantes < 0 => 'vencida',
            $diasRestantes <= self::UMBRAL_AVISO_DIAS => 'por_vencer',
            default => 'activa',
        };

        $puedeRenovar = $this->puedeRenovarAhora($comercio);

        return [
            'estado' => $estado,
            'dias_restantes' => $diasRestantes,
            'vencimiento_pago' => $vencimiento->format('d/m/Y'),
            'puede_renovar' => $puedeRenovar,
            // Cuántos días faltan para que se abra la ventana. `null` cuando
            // ya se puede renovar, para que el frontend no tenga que
            // reconstruir el cálculo ni depender de un 30 hardcodeado.
            'dias_para_renovar' => $puedeRenovar
                ? null
                : $diasRestantes - self::VENTANA_RENOVACION_DIAS,
            'ventana_renovacion_dias' => self::VENTANA_RENOVACION_DIAS,
            'suspendido' => $suspendido,
            'plan_id' => $comercio->plan_id,
            'pago_en_vuelo' => $pagoEnVuelo,
        ];
    }

    /**
     * ¿Se puede renovar ahora el plan vigente?
     *
     * Única fuente de verdad de la ventana de renovación anticipada. La usan
     * `estadoSuscripcion()` para el frontend y
     * `SuscripcionController::generarPreferencia()` como validación de
     * backend, para que la restricción no se pueda saltar llamando directo
     * al endpoint.
     *
     * Se permite cuando:
     *  - la cuenta está suspendida (hay que poder reactivar sí o sí);
     *  - la suscripción ya venció;
     *  - dentro de la ventana: faltan `VENTANA_RENOVACION_DIAS` días o menos.
     */
    public function puedeRenovarAhora(?Comercio $comercio): bool
    {
        if (! $comercio) {
            return false;
        }

        if ($comercio->status === 'suspendido') {
            return true;
        }

        if (! $comercio->vencimiento_pago) {
            return false;
        }

        return $this->diasRestantes(Carbon::parse($comercio->vencimiento_pago)->startOfDay())
            <= self::VENTANA_RENOVACION_DIAS;
    }

    /**
     * Días completos que faltan para el vencimiento. Negativo si ya venció.
     *
     * Se cuenta por día completo y en la misma dirección que
     * `VerificarEstadoCuenta`, para que la ventana de renovación no se
     * desalinee con el corte de acceso ni con el banner del Dashboard.
     */
    private function diasRestantes(Carbon $vencimiento): int
    {
        return (int) now()->startOfDay()->diffInDays($vencimiento, false);
    }

    /**
     * Nueva fecha de vencimiento tras un pago aprobado.
     *
     * Prorroga desde el vencimiento vigente si todavía no pasó (renovación
     * anticipada) y arranca un mes nuevo desde hoy si ya venció o la cuenta
     * estaba suspendida. `addMonthNoOverflow` evita el corrimiento en meses
     * de 31 días (31/01 + 1 mes = 28/02, no 02/03).
     */
    public function nuevaFechaVencimiento(?Carbon $vencimientoActual): Carbon
    {
        $base = ($vencimientoActual && $vencimientoActual->copy()->startOfDay()->isFuture())
            ? $vencimientoActual->copy()->startOfDay()
            : now();

        return $base->addMonthNoOverflow();
    }

    /**
     * Calcula el período que cubre un pago aprobado, sin acortar nunca un
     * período vigente.
     *
     * Regla comercial acordada para los cobros manuales:
     *  - si el vencimiento actual todavía está vigente (incluido el día del
     *    vencimiento, porque el acceso se corta recién al finalizar ese día),
     *    el nuevo período empieza después del vencimiento;
     *  - si ya venció, empieza en la fecha de pago.
     *
     * La duración se toma del plan (`duracion_meses`) cuando exista. Los
     * planes actuales no tienen esa columna y se asumen mensuales, pero el
     * algoritmo no hardcodea el mes: si mañana se agrega duración al plan,
     * se respeta sin tocar este método.
     *
     * @return array{inicio: Carbon, fin: Carbon, meses: int, continua_periodo_vigente: bool}
     */
    public function calcularPeriodo(
        ?Carbon $vencimientoActual,
        ?Plan $plan = null,
        ?Carbon $fechaPago = null,
    ): array {
        $fechaPago = ($fechaPago ?? now())->copy()->startOfDay();

        $vencimiento = $vencimientoActual?->copy()->startOfDay();
        $periodoVigente = $vencimiento !== null
            && $vencimiento->greaterThanOrEqualTo($fechaPago);

        $inicio = $periodoVigente ? $vencimiento->copy() : $fechaPago->copy();
        $meses = $this->mesesDelPlan($plan);

        return [
            'inicio' => $inicio,
            'fin' => $inicio->copy()->addMonthsNoOverflow($meses),
            'meses' => $meses,
            'continua_periodo_vigente' => $periodoVigente,
        ];
    }

    /**
     * Duración contratada del plan en meses. Se deja desacoplada de la tabla
     * actual: hoy todos los planes son mensuales, pero el día que exista una
     * columna de duración este método la usa automáticamente.
     */
    private function mesesDelPlan(?Plan $plan): int
    {
        $meses = $plan?->getAttribute('duracion_meses');

        if (! is_numeric($meses) || (int) $meses < 1) {
            return 1;
        }

        return (int) $meses;
    }

    /**
     * Aplica un pago aprobado sobre la suscripción: prorroga el período, reactiva
     * la cuenta si estaba suspendida y, si el plan es distinto al actual,
     * actualiza módulos y límites.
     *
     * Es idempotente: si el `paymentId` ya fue registrado no vuelve a
     * prorrogar, así que el webhook y la confirmación HTTP pueden ejecutarse
     * en cualquier orden sin duplicar el mes.
     *
     * @return string 'renovacion' si el plan es el mismo, 'upgrade' si cambió
     */
    public function aplicarPago(
        Comercio $comercio,
        Plan $plan,
        string $paymentId,
        ?float $amount = null,
        string $via = 'confirmar_upgrade',
    ): string {
        $resultado = DB::transaction(function () use ($comercio, $plan, $paymentId, $amount, $via) {
            /** @var Comercio $comercio */
            $comercio = Comercio::lockForUpdate()->findOrFail($comercio->id);

            $mismoPlan = (int) $comercio->plan_id === (int) $plan->id;

            if ($this->pagoYaRegistrado($paymentId)) {
                return $mismoPlan ? 'renovacion' : 'upgrade';
            }

            $venciaVencido = $comercio->status === 'suspendido'
                || ($comercio->vencimiento_pago && Carbon::parse($comercio->vencimiento_pago)->isPast());

            if (! $mismoPlan) {
                $comercio->plan_id = $plan->id;
                $comercio->modulos_habilitados = $plan->modulos;
                $comercio->limite_sucursales = $plan->sucursales_limit;
                $comercio->limite_usuarios = $plan->usuarios_limit;
            }

            $comercio->pending_plan_id = null;
            $comercio->vencimiento_pago = $this->nuevaFechaVencimiento($comercio->vencimiento_pago)->toDateString();
            $comercio->status = 'activo';
            $comercio->save();

            $nuevoVencimiento = $comercio->vencimiento_pago->toDateString();

            $activity = activity()->performedOn($comercio);

            if ($via === 'webhook') {
                $activity->causedByAnonymous();
            } else {
                $activity->causedBy(auth()->user());
            }

            $activity
                ->withProperties([
                    'plan_id' => $comercio->plan_id,
                    'plan' => $mismoPlan ? null : $plan->toArray(),
                    'intento' => $mismoPlan ? 'renovacion' : 'upgrade',
                    'via' => $via,
                    'payment_id' => $paymentId,
                    'reactivated' => $venciaVencido,
                    'vencimiento_pago' => $nuevoVencimiento,
                ])
                ->log($this->descripcionActividad($mismoPlan, $venciaVencido, $via));

            $this->registrarPago(
                comercio: $comercio,
                paymentId: $paymentId,
                amount: $amount ?? (float) $plan->precio_mensual,
                mismoPlan: $mismoPlan,
            );

            return $mismoPlan ? 'renovacion' : 'upgrade';
        });

        $this->limpiarPagoEnVuelo($comercio->id);

        return $resultado;
    }

    public function pagoYaRegistrado(string $paymentId): bool
    {
        return Payment::query()
            ->where('provider', 'mercadopago')
            ->where('gateway_transaction_id', $paymentId)
            ->exists();
    }

    /**
     * Marca que ya hay una preferencia de pago en curso, para bloquear la
     * generación de una segunda durante los próximos minutos.
     */
    public function marcarPagoEnVuelo(int $comercioId, int $planId): void
    {
        Cache::put(
            $this->clavePagoEnVuelo($comercioId),
            [
                'plan_id' => $planId,
                'expira' => now()->addMinutes(self::MINUTOS_PAGO_EN_VUELO)->toIso8601String(),
                // Se completa después de crear la preferencia. Guardarlo
                // permite que "Reintentar pago" reanude el mismo checkout en
                // lugar de generar una segunda preferencia y duplicar el cobro.
                'checkout_url' => null,
            ],
            now()->addMinutes(self::MINUTOS_PAGO_EN_VUELO),
        );
    }

    /**
     * Guarda el link del checkout de la preferencia viva sin extender su
     * ventana de bloqueo. Así el usuario puede retomar exactamente el mismo
     * pago desde la pantalla de "pago pendiente".
     */
    public function recordarCheckoutEnVuelo(int $comercioId, string $checkoutUrl): void
    {
        $clave = $this->clavePagoEnVuelo($comercioId);
        $datos = Cache::get($clave);

        if (! is_array($datos) || ! isset($datos['plan_id'], $datos['expira'])) {
            return;
        }

        try {
            $expira = Carbon::parse($datos['expira']);
        } catch (\Throwable) {
            return;
        }

        $segundos = (int) ceil(now()->diffInSeconds($expira, false));

        if ($segundos <= 0) {
            return;
        }

        $datos['checkout_url'] = $checkoutUrl;

        Cache::put($clave, $datos, now()->addSeconds($segundos));
    }

    /**
     * Preferencia de pago viva, con los minutos que le quedan de bloqueo.
     *
     * `checkout_url` puede ser `null` para intenciones creadas antes de que se
     * empezara a guardar el link (o si Mercado Pago no lo devolvió).
     *
     * @return array{plan_id: int, expira_en_minutos: int, checkout_url: string|null}|null
     */
    public function pagoEnVuelo(?int $comercioId): ?array
    {
        if (! $comercioId) {
            return null;
        }

        $datos = Cache::get($this->clavePagoEnVuelo($comercioId));

        if (! is_array($datos) || ! isset($datos['plan_id'], $datos['expira'])) {
            return null;
        }

        try {
            $expira = Carbon::parse($datos['expira']);
        } catch (\Throwable) {
            return null;
        }

        $minutos = (int) ceil(now()->diffInSeconds($expira, false) / 60);

        // Si la ventana ya venció, la preferencia queda huérfana: se limpia
        // para que el usuario pueda volver a intentar sin esperar.
        if ($minutos <= 0) {
            $this->limpiarPagoEnVuelo($comercioId);

            return null;
        }

        return [
            'plan_id' => (int) $datos['plan_id'],
            'expira_en_minutos' => $minutos,
            'checkout_url' => isset($datos['checkout_url']) && is_string($datos['checkout_url'])
                ? $datos['checkout_url']
                : null,
        ];
    }

    public function limpiarPagoEnVuelo(?int $comercioId): void
    {
        if (! $comercioId) {
            return;
        }

        Cache::forget($this->clavePagoEnVuelo($comercioId));
    }

    /**
     * Libera el pago en vuelo: descarta la preferencia viva y limpia
     * `pending_plan_id`.
     *
     * Es la salida que hoy no existía. Sin esto, si el usuario abandonaba el
     * checkout de Mercado Pago quedaba bloqueado con un 409 durante toda la
     * ventana de 30 minutos y `pending_plan_id` "sucio" de forma indefinida.
     *
     * Sólo se toca `pending_plan_id`: si ya se aplicó un pago, el estado de la
     * suscripción es el bueno y no se toca.
     */
    public function liberarPagoEnVuelo(Comercio $comercio, string $motivo = 'liberado'): void
    {
        $this->limpiarPagoEnVuelo($comercio->id);

        if ($comercio->pending_plan_id === null) {
            return;
        }

        $comercio->pending_plan_id = null;
        $comercio->save();

        activity()
            ->performedOn($comercio)
            ->causedByAnonymous()
            ->withProperties([
                'plan_id' => $comercio->plan_id,
                'plan_pendiente_id' => null,
                'motivo' => $motivo,
            ])
            ->log('plan_payment_released');
    }

    private function clavePagoEnVuelo(int $comercioId): string
    {
        return 'suscripcion:pago-en-vuelo:'.$comercioId;
    }

    /**
     * @return array{
     *     estado: string,
     *     dias_restantes: int|null,
     *     vencimiento_pago: string|null,
     *     puede_renovar: bool,
     *     dias_para_renovar: int|null,
     *     ventana_renovacion_dias: int,
     *     suspendido: bool,
     *     plan_id: int|null,
     *     pago_en_vuelo: array{plan_id: int, expira_en_minutos: int, checkout_url: string|null}|null
     * }
     */
    private function estadoVacio(): array
    {
        return [
            'estado' => 'sin_vencimiento',
            'dias_restantes' => null,
            'vencimiento_pago' => null,
            'puede_renovar' => false,
            'dias_para_renovar' => null,
            'ventana_renovacion_dias' => self::VENTANA_RENOVACION_DIAS,
            'suspendido' => false,
            'plan_id' => null,
            'pago_en_vuelo' => null,
        ];
    }

    private function registrarPago(Comercio $comercio, string $paymentId, ?float $amount, bool $mismoPlan): void
    {
        Payment::firstOrCreate(
            [
                'provider' => 'mercadopago',
                'gateway_transaction_id' => $paymentId,
            ],
            [
                'payable_type' => Comercio::class,
                'payable_id' => $comercio->id,
                'channel' => PaymentChannel::API,
                'status' => PaymentStatus::APPROVED,
                'reference' => (string) $comercio->id,
                'amount' => $amount,
                'approved_at' => now(),
                'metadata' => [
                    'tipo' => 'suscripcion',
                    'plan_id' => $comercio->plan_id,
                    'intento' => $mismoPlan ? 'renovacion' : 'upgrade',
                ],
            ],
        );
    }

    private function descripcionActividad(bool $mismoPlan, bool $reactivada, string $via): string
    {
        $sufijo = $via === 'webhook' ? '_via_webhook' : '';

        return match (true) {
            $mismoPlan && $reactivada => 'plan_reactivated'.$sufijo,
            $mismoPlan => 'plan_renewed'.$sufijo,
            $reactivada => 'plan_reactivated'.$sufijo,
            default => 'plan_upgraded'.$sufijo,
        };
    }
}
