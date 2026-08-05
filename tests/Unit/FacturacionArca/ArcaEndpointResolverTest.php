<?php

namespace Tests\Unit\FacturacionArca;

use App\Facturacion\Infrastructure\Arca\Entorno\ArcaEndpointResolver;
use App\Facturacion\Infrastructure\Arca\Entorno\EntornoArca;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ArcaEndpointResolverTest extends TestCase
{
    private function config(): array
    {
        return [
            'wsaa' => [
                'wsdl_produccion' => 'https://wsaa.test/LoginCms?WSDL',
                'wsdl_homologacion' => 'https://wsaahomo.test/LoginCms?WSDL',
                'ttl_segundos' => 600,
            ],
            'wsfe' => [
                'wsdl_produccion' => 'https://wsfe.test/wsfe?WSDL',
                'wsdl_homologacion' => 'https://wfehomo.test/wsfe?WSDL',
                'namespace_auth' => 'http://ar.gov.afip.digifed.wsfe/',
            ],
            'padron' => [
                'wsdl_produccion' => 'https://padron.test?wsdl',
                'wsdl_homologacion' => 'https://padronhomo.test?wsdl',
                'namespace_auth' => 'http://impl.batch.wsaa.afip.gov.ar/',
            ],
            'soap' => ['exceptions' => true, 'connection_timeout' => 30],
        ];
    }

    public function test_resuelve_wsdl_por_servicio_y_entorno(): void
    {
        $resolver = new ArcaEndpointResolver($this->config());

        $this->assertSame('https://wsaa.test/LoginCms?WSDL', $resolver->wsdlWsaa(EntornoArca::PRODUCCION));
        $this->assertSame('https://wsaahomo.test/LoginCms?WSDL', $resolver->wsdlWsaa(EntornoArca::HOMOLOGACION));
        $this->assertSame('https://wsfe.test/wsfe?WSDL', $resolver->wsdlWsfe(EntornoArca::PRODUCCION));
        $this->assertSame('https://padron.test?wsdl', $resolver->wsdlPadron(EntornoArca::PRODUCCION));
    }

    public function test_falta_wsdl_lanza_invalid_argument(): void
    {
        $config = $this->config();
        unset($config['wsfe']['wsdl_produccion']);

        $resolver = new ArcaEndpointResolver($config);

        $this->expectException(InvalidArgumentException::class);

        $resolver->wsdlWsfe(EntornoArca::PRODUCCION);
    }

    public function test_ttl_y_opciones_soap(): void
    {
        $resolver = new ArcaEndpointResolver($this->config());

        $this->assertSame(600, $resolver->ttlWsaa());
        $this->assertSame(['exceptions' => true, 'connection_timeout' => 30], $resolver->opcionesSoap());
    }

    public function test_namespaces_de_autenticacion(): void
    {
        $resolver = new ArcaEndpointResolver($this->config());

        $this->assertSame('http://ar.gov.afip.digifed.wsfe/', $resolver->namespaceAuthWsfe());
        $this->assertSame('http://impl.batch.wsaa.afip.gov.ar/', $resolver->namespaceAuthPadron());
    }

    public function test_entorno_desde_es_valido_o_lanza(): void
    {
        $this->assertSame(EntornoArca::PRODUCCION, EntornoArca::desde('produccion'));
        $this->assertSame(EntornoArca::HOMOLOGACION, EntornoArca::desde('homologacion'));

        $this->expectException(InvalidArgumentException::class);
        EntornoArca::desde('sandbox');
    }
}
