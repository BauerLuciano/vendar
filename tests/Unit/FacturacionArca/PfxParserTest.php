<?php

namespace Tests\Unit\FacturacionArca;

use App\Facturacion\Infrastructure\Arca\Certificado\PfxParser;
use App\Facturacion\Infrastructure\Arca\Exceptions\CertificadoInvalidoException;
use PHPUnit\Framework\TestCase;
use Tests\Support\FacturacionArca\GeneraPfx;

class PfxParserTest extends TestCase
{
    private PfxParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new PfxParser;
    }

    public function test_parsea_pfx_valido(): void
    {
        $pfx = GeneraPfx::valido();

        $datos = $this->parser->parsear($pfx['pfx'], $pfx['password']);

        $this->assertTrue($datos->vigente());
        $this->assertStringContainsString('-----BEGIN CERTIFICATE-----', $datos->certPem());
        $this->assertStringContainsString('-----BEGIN PRIVATE KEY-----', $datos->pkeyPem());
        $this->assertNotSame('', $datos->numeroSerie());
        $this->assertStringContainsString(GeneraPfx::CUIT_VALIDO, $datos->distinguishedName());
        $this->assertLessThanOrEqual(new \DateTimeImmutable, $datos->vigenciaDesde());
        $this->assertGreaterThan(new \DateTimeImmutable, $datos->vigenciaHasta());
    }

    public function test_password_incorrecta_lanza(): void
    {
        $pfx = GeneraPfx::valido();

        $this->expectException(CertificadoInvalidoException::class);

        $this->parser->parsear($pfx['pfx'], 'password-incorrecta');
    }

    public function test_pfx_vencido_no_vigente(): void
    {
        $pfx = GeneraPfx::vencido();

        $datos = $this->parser->parsear($pfx['pfx'], $pfx['password']);

        $this->assertFalse($datos->vigente());
    }
}
