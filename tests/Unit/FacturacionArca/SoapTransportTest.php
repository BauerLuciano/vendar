<?php

namespace Tests\Unit\FacturacionArca;

use App\Facturacion\Infrastructure\Arca\Exceptions\ArcaIntegrationException;
use App\Facturacion\Infrastructure\Arca\SoapTransport;
use PHPUnit\Framework\TestCase;
use SoapFault;
use Tests\Support\FacturacionArca\FakeSoapClient;

class SoapTransportTest extends TestCase
{
    public function test_llamar_envia_operacion_y_argumentos(): void
    {
        $cliente = new FakeSoapClient((object) ['ok' => true]);
        $transporte = new SoapTransport($cliente);

        $respuesta = $transporte->llamar('FEDummy', [['in0' => 1]]);

        $this->assertTrue($respuesta->ok);
        $this->assertSame(['FEDummy'], $cliente->llamadas());
    }

    public function test_soap_fault_se_convierte_en_error_de_integracion(): void
    {
        $cliente = new FakeSoapClient(null, new SoapFault('SOAP-ENV:Server', 'Fallo del servidor ARCA'));
        $transporte = new SoapTransport($cliente);

        $this->expectException(ArcaIntegrationException::class);

        $transporte->llamar('FEDummy', []);
    }

    public function test_respuesta_no_objeto_lanza(): void
    {
        $cliente = new FakeSoapClient('texto-plano');
        $transporte = new SoapTransport($cliente);

        $this->expectException(ArcaIntegrationException::class);

        $transporte->llamar('FEDummy', []);
    }
}
