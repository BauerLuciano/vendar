<?php

namespace Tests\Feature\FacturacionArca;

use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Infrastructure\Arca\Cifrado\CertificadoEncryptor;
use App\Facturacion\Infrastructure\Arca\Cifrado\CredencialPlataformaRepository;
use App\Facturacion\Infrastructure\Arca\Cifrado\CredencialPlataformaService;
use App\Facturacion\Infrastructure\Arca\Entorno\ArcaEndpointResolver;
use App\Facturacion\Infrastructure\Arca\Entorno\EntornoArca;
use App\Facturacion\Infrastructure\Arca\Exceptions\ArcaIntegrationException;
use App\Facturacion\Infrastructure\Arca\Exceptions\CredencialPlataformaNoConfiguradaException;
use App\Facturacion\Infrastructure\Arca\Padron\CondicionFiscalMapper;
use App\Facturacion\Infrastructure\Arca\Padron\PadronClient;
use Tests\Support\FacturacionArca\FakeArcaSoapTransport;
use Tests\Support\FacturacionArca\FakeSoapClientFactory;
use Tests\Support\FacturacionArca\GeneraPfx;
use Tests\Support\FacturacionArca\RespuestasArca;
use Tests\TestCaseMultiTenant;

class PadronClientTest extends TestCaseMultiTenant
{
    use RespuestasArca;

    public function test_consultar_persona_activa_ri(): void
    {
        $transporte = new FakeArcaSoapTransport(
            fn () => (object) ['getPersonaReturn' => $this->personaActivaResponsableInscripto()]
        );

        $resultado = $this->cliente($transporte)->consultar(new Cuit(GeneraPfx::CUIT_VALIDO));

        $this->assertSame('responsable_inscripto', $resultado['condicion_fiscal']);
        $this->assertSame('ACTIVO', $resultado['estado']);
        $this->assertSame('PEREZ JUAN', $resultado['nombre']);

        $this->assertInstanceOf(\SoapHeader::class, $transporte->llamadas[0]['cabecera']);
        $this->assertSame('authRequest', $transporte->llamadas[0]['cabecera']->name);
    }

    public function test_consultar_persona_monotributo(): void
    {
        $transporte = new FakeArcaSoapTransport(
            fn () => (object) ['getPersonaReturn' => $this->personaActivaMonotributo()]
        );

        $resultado = $this->cliente($transporte)->consultar(new Cuit(GeneraPfx::CUIT_VALIDO));

        $this->assertSame('monotributo', $resultado['condicion_fiscal']);
    }

    public function test_respuesta_con_persona_return_alternativo(): void
    {
        $transporte = new FakeArcaSoapTransport(
            fn () => (object) ['personaReturn' => $this->personaActivaResponsableInscripto()]
        );

        $resultado = $this->cliente($transporte)->consultar(new Cuit(GeneraPfx::CUIT_VALIDO));

        $this->assertSame('responsable_inscripto', $resultado['condicion_fiscal']);
    }

    public function test_respuesta_sin_persona_lanza(): void
    {
        $transporte = new FakeArcaSoapTransport((object) []);

        $this->expectException(ArcaIntegrationException::class);

        $this->cliente($transporte)->consultar(new Cuit(GeneraPfx::CUIT_VALIDO));
    }

    public function test_consultar_sin_credencial_lanza(): void
    {
        $transporte = new FakeArcaSoapTransport(
            fn () => (object) ['getPersonaReturn' => $this->personaActivaResponsableInscripto()]
        );

        $cliente = new PadronClient(
            new FakeSoapClientFactory($transporte),
            new ArcaEndpointResolver(config('services.arca')),
            new CredencialPlataformaService(new CredencialPlataformaRepository(new CertificadoEncryptor)),
            new CondicionFiscalMapper,
            EntornoArca::PRODUCCION,
        );

        $this->expectException(CredencialPlataformaNoConfiguradaException::class);

        $cliente->consultar(new Cuit(GeneraPfx::CUIT_VALIDO));
    }

    private function cliente(FakeArcaSoapTransport $transporte): PadronClient
    {
        $credencial = new CredencialPlataformaService(
            new CredencialPlataformaRepository(new CertificadoEncryptor)
        );
        $credencial->guardar(new Cuit(GeneraPfx::CUIT_VALIDO), 'TOKEN_PLATAFORMA', 'SIGN_PLATAFORMA');

        return new PadronClient(
            new FakeSoapClientFactory($transporte),
            new ArcaEndpointResolver(config('services.arca')),
            $credencial,
            new CondicionFiscalMapper,
            EntornoArca::PRODUCCION,
        );
    }
}
