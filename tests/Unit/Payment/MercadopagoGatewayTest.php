<?php

namespace Tests\Unit\Payment;

use App\Enums\PaymentStatus;
use App\Services\Payment\Gateways\MercadopagoGateway;
use Tests\TestCase;

/**
 * Mapeo de estados de Mercado Pago al enum interno.
 *
 * Es crítico para la renovación: un pago `expired` (el usuario entró al
 * checkout y se fue sin pagar) tiene que distinguirse de `pending`, porque el
 * webhook usa esa diferencia para decidir si libera el estado en vuelo o si
 * deja esperar una confirmación que puede llegar más tarde.
 */
class MercadopagoGatewayTest extends TestCase
{
    private MercadopagoGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new MercadopagoGateway([
            'access_token' => 'TEST-1234567890',
        ]);
    }

    public function test_normaliza_pago_aprobado(): void
    {
        $this->assertSame(PaymentStatus::APPROVED, $this->gateway->normalizeStatus('approved'));
    }

    /**
     * @dataProvider estadosPendientes
     */
    public function test_normaliza_estados_pendientes(string $estadoMp): void
    {
        $this->assertSame(PaymentStatus::PENDING, $this->gateway->normalizeStatus($estadoMp));
    }

    public static function estadosPendientes(): array
    {
        return [
            'pending' => ['pending'],
            'in_process' => ['in_process'],
            'in_mediation' => ['in_mediation'],
        ];
    }

    /**
     * @dataProvider estadosTerminales
     */
    public function test_normaliza_estados_terminales(string $estadoMp, PaymentStatus $esperado): void
    {
        $this->assertSame($esperado, $this->gateway->normalizeStatus($estadoMp));
    }

    public static function estadosTerminales(): array
    {
        return [
            'rechazado' => ['rejected', PaymentStatus::REJECTED],
            'cancelado' => ['cancelled', PaymentStatus::CANCELLED],
            'expirado' => ['expired', PaymentStatus::EXPIRED],
            'reembolsado' => ['refunded', PaymentStatus::REFUNDED],
            'chargeback' => ['charged_back', PaymentStatus::REFUNDED],
        ];
    }

    /**
     * Regresión: `expired` no tenía caso propio y caía en `default`, que
     // devuelve PENDING. Un pago expirado se creía que todavía podía
     * completarse, así que el estado en vuelo nunca se liberaba y el usuario
     * quedaba trabado viendo "Estamos procesando tu pago".
     */
    public function test_expired_no_se_confunde_con_pending(): void
    {
        $this->assertNotSame(
            $this->gateway->normalizeStatus('pending'),
            $this->gateway->normalizeStatus('expired'),
        );
    }

    public function test_estado_desconocido_cae_en_pending(): void
    {
        $this->assertSame(PaymentStatus::PENDING, $this->gateway->normalizeStatus('algo_que_no_conocemos'));
    }
}
