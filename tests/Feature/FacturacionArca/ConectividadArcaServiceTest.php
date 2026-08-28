<?php

namespace Tests\Feature\FacturacionArca;

use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Infrastructure\Arca\Certificado\CertificadoMaterial;
use App\Facturacion\Infrastructure\Arca\Certificado\CertificadoService;
use App\Facturacion\Infrastructure\Arca\Certificado\PfxParser;
use App\Facturacion\Infrastructure\Arca\Cifrado\CertificadoEncryptor;
use App\Facturacion\Infrastructure\Arca\Cifrado\CredencialPlataformaRepository;
use App\Facturacion\Infrastructure\Arca\Cifrado\CredencialPlataformaService;
use App\Facturacion\Infrastructure\Arca\Conectividad\ConectividadArcaService;
use App\Facturacion\Infrastructure\Arca\Entorno\ArcaEndpointResolver;
use App\Facturacion\Infrastructure\Arca\Entorno\EntornoArca;
use App\Facturacion\Infrastructure\Arca\Exceptions\ArcaIntegrationException;
use App\Facturacion\Infrastructure\Arca\Padron\CondicionFiscalMapper;
use App\Facturacion\Infrastructure\Arca\Padron\PadronClient;
use App\Facturacion\Infrastructure\Arca\Wsaa\FirmaCms;
use App\Facturacion\Infrastructure\Arca\Wsaa\WsaaClient;
use DateTimeImmutable;
use Tests\Support\FacturacionArca\FakeArcaSoapTransport;
use Tests\Support\FacturacionArca\FakeSoapClientFactory;
use Tests\Support\FacturacionArca\GeneraPfx;
use Tests\Support\FacturacionArca\RespuestasArca;
use Tests\TestCaseMultiTenant;

class ConectividadArcaServiceTest extends TestCaseMultiTenant
{
    use RespuestasArca;

    public function test_suite_completa_ok(): void
    {
        $transporte = new FakeArcaSoapTransport(function ($operacion) {
            return match ($operacion) {
                'loginCms' => (object) ['loginCmsReturn' => $this->loginXml((new DateTimeImmutable)->modify('+600 seconds'))],
                'FEDummy' => (object) ['FEDummyResult' => (object) ['appserver' => 'OK', 'authserver' => 'OK', 'dbserver' => 'OK']],
                'getPersona_v2' => (object) ['personaReturn' => $this->personaActivaResponsableInscripto()],
                default => throw new ArcaIntegrationException("Operación inesperada {$operacion}"),
            };
        });

        $material = new CertificadoMaterial(GeneraPfx::valido()['pfx'], 'clave-secreta');

        $resultados = $this->servicio($transporte)->suite(
            EntornoArca::PRODUCCION,
            $material,
            new Cuit(GeneraPfx::CUIT_VALIDO)
        );

        $this->assertSame(['certificado_vigente', 'wsaa', 'conectividad_wsfe', 'padron'], array_column($resultados, 'check'));

        foreach ($resultados as $resultado) {
            $this->assertTrue($resultado['ok'], $resultado['check'].': '.$resultado['detalle']);
        }
    }

    public function test_certificado_vencido_falla(): void
    {
        $transporte = new FakeArcaSoapTransport(
            (object) ['FEDummyResult' => (object) ['appserver' => 'OK']]
        );

        $material = new CertificadoMaterial(GeneraPfx::vencido()['pfx'], 'clave-secreta');

        $resultados = $this->servicio($transporte)->suite(
            EntornoArca::PRODUCCION,
            $material,
            new Cuit(GeneraPfx::CUIT_VALIDO)
        );

        $this->assertFalse($resultados[0]['ok']);
        $this->assertSame('certificado_vigente', $resultados[0]['check']);
    }

    public function test_conectividad_wsfe_falla_cuando_dummy_falla(): void
    {
        $transporte = new FakeArcaSoapTransport(function ($operacion) {
            return match ($operacion) {
                'loginCms' => (object) ['loginCmsReturn' => $this->loginXml((new DateTimeImmutable)->modify('+600 seconds'))],
                'FEDummy' => throw new ArcaIntegrationException('Timeout de conexión'),
                'getPersona_v2' => (object) ['personaReturn' => $this->personaActivaResponsableInscripto()],
                default => throw new ArcaIntegrationException("Operación inesperada {$operacion}"),
            };
        });

        $material = new CertificadoMaterial(GeneraPfx::valido()['pfx'], 'clave-secreta');

        $resultados = $this->servicio($transporte)->suite(
            EntornoArca::PRODUCCION,
            $material,
            new Cuit(GeneraPfx::CUIT_VALIDO)
        );

        $this->assertFalse($resultados[2]['ok']);
        $this->assertSame('conectividad_wsfe', $resultados[2]['check']);
    }

    public function test_padron_falla_cuando_cuit_no_activo(): void
    {
        $transporte = new FakeArcaSoapTransport(function ($operacion) {
            return match ($operacion) {
                'loginCms' => (object) ['loginCmsReturn' => $this->loginXml((new DateTimeImmutable)->modify('+600 seconds'))],
                'FEDummy' => (object) ['FEDummyResult' => (object) ['appserver' => 'OK']],
                'getPersona_v2' => (object) ['personaReturn' => (object) ['apellido' => 'X', 'nombre' => 'Y', 'estado' => 'BAJA']],
                default => throw new ArcaIntegrationException("Operación inesperada {$operacion}"),
            };
        });

        $material = new CertificadoMaterial(GeneraPfx::valido()['pfx'], 'clave-secreta');

        $resultados = $this->servicio($transporte)->suite(
            EntornoArca::PRODUCCION,
            $material,
            new Cuit(GeneraPfx::CUIT_VALIDO)
        );

        $this->assertFalse($resultados[3]['ok']);
        $this->assertSame('padron', $resultados[3]['check']);
    }

    private function servicio(FakeArcaSoapTransport $transporte): ConectividadArcaService
    {
        $endpoints = new ArcaEndpointResolver(config('services.arca'));

        $credencial = new CredencialPlataformaService(
            new CredencialPlataformaRepository(new CertificadoEncryptor)
        );
        $credencial->guardar(new Cuit(GeneraPfx::CUIT_VALIDO), 'TOKEN_PLATAFORMA', 'SIGN_PLATAFORMA');

        $factoria = new FakeSoapClientFactory($transporte);

        return new ConectividadArcaService(
            new CertificadoService(new CertificadoEncryptor, new PfxParser),
            new WsaaClient($factoria, new FirmaCms, $endpoints),
            $factoria,
            $endpoints,
            new PadronClient($factoria, $endpoints, $credencial, new CondicionFiscalMapper, EntornoArca::PRODUCCION),
        );
    }
}
