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
use SoapVar;

class PadronClientTest extends TestCaseMultiTenant
{
    use RespuestasArca;

    public function test_consultar_persona_activa_ri(): void
    {
        $transporte = new FakeArcaSoapTransport(
            fn () => (object) ['personaReturn' => $this->personaActivaResponsableInscripto()]
        );

        $resultado = $this->cliente($transporte)->consultar(new Cuit(GeneraPfx::CUIT_VALIDO));

        $this->assertSame('responsable_inscripto', $resultado['condicion_fiscal']);
        $this->assertSame('ACTIVO', $resultado['estado']);
        $this->assertSame('PEREZ JUAN', $resultado['nombre']);
        $this->assertSame('ERNESTO CASTELLANO 7, VILLA DOLORES, CORDOBA, 5870', $resultado['domicilio_fiscal']);

        $llamada = $transporte->llamadas[0];
        $this->assertSame('getPersona_v2', $llamada['operacion']);
        $this->assertNull($llamada['cabecera']);

        // getPersona_v2 debe enviarse como UN único argumento estructurado
        // (SoapVar SOAP_ENC_OBJECT), nunca como argumentos posicionales
        // param1/param2 (bug de serialización corregido en PadronClient).
        $this->assertCount(1, $llamada['argumentos']);
        $argumento = $llamada['argumentos'][0];
        $this->assertInstanceOf(SoapVar::class, $argumento);
        $this->assertSame(SOAP_ENC_OBJECT, $argumento->enc_type);
        $this->assertSame([
            'token' => 'TOKEN_PLATAFORMA',
            'sign' => 'SIGN_PLATAFORMA',
            'cuitRepresentada' => GeneraPfx::CUIT_VALIDO,
            'idPersona' => GeneraPfx::CUIT_VALIDO,
        ], $argumento->enc_value);
    }

    public function test_consultar_persona_monotributo(): void
    {
        $transporte = new FakeArcaSoapTransport(
            fn () => (object) ['personaReturn' => $this->personaActivaMonotributo()]
        );

        $resultado = $this->cliente($transporte)->consultar(new Cuit(GeneraPfx::CUIT_VALIDO));

        $this->assertSame('monotributo', $resultado['condicion_fiscal']);
        $this->assertNull($resultado['domicilio_fiscal']);
    }

    public function test_consultar_domicilio_fiscal_con_campos_vacios(): void
    {
        $transporte = new FakeArcaSoapTransport(
            fn () => (object) ['personaReturn' => (object) [
                'datosGenerales' => (object) [
                    'apellido' => 'PEREZ',
                    'nombre' => 'JUAN',
                    'estadoClave' => 'ACTIVO',
                    'domicilioFiscal' => (object) [
                        'direccion' => 'AV SIEMPRE VIVA 742',
                        'localidad' => '',
                        'descripcionProvincia' => null,
                        'codPostal' => '',
                    ],
                ],
            ]]
        );

        $resultado = $this->cliente($transporte)->consultar(new Cuit(GeneraPfx::CUIT_VALIDO));

        $this->assertSame('AV SIEMPRE VIVA 742', $resultado['domicilio_fiscal']);
    }

    public function test_consultar_domicilio_fiscal_ausente_deja_vacio(): void
    {
        $transporte = new FakeArcaSoapTransport(
            fn () => (object) ['personaReturn' => (object) [
                'datosGenerales' => (object) [
                    'apellido' => 'PEREZ',
                    'nombre' => 'JUAN',
                    'estadoClave' => 'ACTIVO',
                ],
            ]]
        );

        $resultado = $this->cliente($transporte)->consultar(new Cuit(GeneraPfx::CUIT_VALIDO));

        $this->assertNull($resultado['domicilio_fiscal']);
    }

    public function test_respuesta_con_contenedor_persona_return(): void
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
            fn () => (object) ['personaReturn' => $this->personaActivaResponsableInscripto()]
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
