<?php

namespace Tests\Unit\FacturacionArca;

use App\Facturacion\Infrastructure\Arca\Certificado\CertificadoMaterial;
use App\Facturacion\Infrastructure\Arca\Entorno\ArcaEndpointResolver;
use App\Facturacion\Infrastructure\Arca\Entorno\EntornoArca;
use App\Facturacion\Infrastructure\Arca\Exceptions\ArcaIntegrationException;
use App\Facturacion\Infrastructure\Arca\Wsaa\FirmaCms;
use App\Facturacion\Infrastructure\Arca\Wsaa\WsaaClient;
use App\Facturacion\Infrastructure\Arca\Wsaa\WsaaToken;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FacturacionArca\FakeArcaSoapTransport;
use Tests\Support\FacturacionArca\FakeSoapClientFactory;
use Tests\Support\FacturacionArca\GeneraPfx;
use Tests\Support\FacturacionArca\RespuestasArca;
use Tests\TestCase;

class WsaaClientTest extends TestCase
{
    use RespuestasArca;

    public function test_obtener_token_dos_veces_reutiliza_el_cache(): void
    {
        $transporte = new FakeArcaSoapTransport(
            fn () => (object) ['loginReturn' => $this->loginXml((new DateTimeImmutable)->modify('+600 seconds'))]
        );

        $client = $this->cliente($transporte);
        $material = $this->material();

        $primero = $client->obtenerToken('wsfe', EntornoArca::PRODUCCION, $material);
        $segundo = $client->obtenerToken('wsfe', EntornoArca::PRODUCCION, $material);

        $this->assertSame($primero, $segundo);
        $this->assertSame(1, $transporte->cantidadDeLlamadas('login'));
    }

    public function test_renueva_cuando_el_token_guardado_vence_pronto(): void
    {
        $transporte = new FakeArcaSoapTransport(
            fn () => (object) ['loginReturn' => $this->loginXml((new DateTimeImmutable)->modify('+600 seconds'))]
        );

        $cercano = new WsaaToken('TOKEN_VIEJO', 'SIGN_VIEJO', (new DateTimeImmutable)->modify('+30 seconds'));
        $material = $this->material();
        Cache::put('arca.wsaa.produccion.wsfe.'.substr(hash('sha256', $material->pfx()), 0, 16), $cercano, 600);

        $client = $this->cliente($transporte);

        $obtenido = $client->obtenerToken('wsfe', EntornoArca::PRODUCCION, $material);

        $this->assertNotSame($cercano, $obtenido);
        $this->assertSame('TOKEN_WSAA_TEST', $obtenido->token());
        $this->assertSame(1, $transporte->cantidadDeLlamadas('login'));
    }

    public function test_materiales_distintos_no_comparten_token_de_cache(): void
    {
        $transporte = new FakeArcaSoapTransport(
            fn () => (object) ['loginReturn' => $this->loginXml((new DateTimeImmutable)->modify('+600 seconds'))]
        );

        $client = $this->cliente($transporte);

        $primero = $client->obtenerToken('wsfe', EntornoArca::PRODUCCION, $this->material());
        $segundo = $client->obtenerToken('wsfe', EntornoArca::PRODUCCION, $this->material());

        $this->assertNotSame($primero, $segundo);
        $this->assertSame(2, $transporte->cantidadDeLlamadas('login'));
    }

    public function test_login_sin_ticket_lanza_error_de_integracion(): void
    {
        $transporte = new FakeArcaSoapTransport((object) ['loginReturn' => null]);

        $client = $this->cliente($transporte);

        $this->expectException(ArcaIntegrationException::class);

        $client->obtenerToken('wsfe', EntornoArca::PRODUCCION, $this->material());
    }

    public function test_timeout_de_red_en_login_se_envuelve_como_error_de_integracion(): void
    {
        $transporte = new FakeArcaSoapTransport(
            (object) ['loginReturn' => null],
            new \SoapFault('HTTP', 'Could not connect to host'),
        );

        $client = $this->cliente($transporte);

        try {
            $client->obtenerToken('wsfe', EntornoArca::PRODUCCION, $this->material());
            $this->fail('Debía lanzar ArcaIntegrationException ante un timeout de red.');
        } catch (ArcaIntegrationException $e) {
            $this->assertStringContainsString('autenticación', $e->getMessage());
        }
    }

    public function test_pfx_invalido_lanza_error_de_integracion(): void
    {
        $transporte = new FakeArcaSoapTransport(
            fn () => (object) ['loginReturn' => $this->loginXml((new DateTimeImmutable)->modify('+600 seconds'))]
        );

        $client = $this->cliente($transporte);

        $this->expectException(ArcaIntegrationException::class);

        $client->obtenerToken('wsfe', EntornoArca::PRODUCCION, new CertificadoMaterial('pfx-invalido', 'clave'));
    }

    private function cliente(FakeArcaSoapTransport $transporte): WsaaClient
    {
        return new WsaaClient(
            new FakeSoapClientFactory($transporte),
            new FirmaCms,
            new ArcaEndpointResolver(config('services.arca')),
        );
    }

    private function material(): CertificadoMaterial
    {
        $pfx = GeneraPfx::valido();

        return new CertificadoMaterial($pfx['pfx'], $pfx['password']);
    }
}
