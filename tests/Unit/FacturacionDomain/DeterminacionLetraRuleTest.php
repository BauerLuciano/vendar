<?php

namespace Tests\Unit\FacturacionDomain;

use App\Facturacion\Domain\Entities\Emisor;
use App\Facturacion\Domain\Entities\Receptor;
use App\Facturacion\Domain\Exceptions\EmisorNoElegibleException;
use App\Facturacion\Domain\Rules\DeterminacionLetraRule;
use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Domain\ValueObjects\LetraComprobante;
use PHPUnit\Framework\TestCase;

class DeterminacionLetraRuleTest extends TestCase
{
    private DeterminacionLetraRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new DeterminacionLetraRule;
    }

    private function emisorRi(): Emisor
    {
        return new Emisor(new Cuit('20123456786'), 'Kiosco RI S.A.', CondicionFiscal::RESPONSABLE_INSCRIPTO);
    }

    private function receptorRi(): Receptor
    {
        return new Receptor(
            new Cuit('30500010912'),
            'Cliente RI',
            'Calle 123',
            CondicionFiscal::RESPONSABLE_INSCRIPTO
        );
    }

    public function test_ri_a_ri_es_factura_a(): void
    {
        $letra = $this->rule->determinar($this->emisorRi(), $this->receptorRi());
        $this->assertEquals(LetraComprobante::A, $letra);
    }

    public function test_ri_a_monotributo_es_factura_b(): void
    {
        $receptor = new Receptor(
            new Cuit('20123456786'),
            'Cliente Monotributo',
            null,
            CondicionFiscal::MONOTRIBUTO
        );

        $letra = $this->rule->determinar($this->emisorRi(), $receptor);
        $this->assertEquals(LetraComprobante::B, $letra);
    }

    public function test_ri_a_consumidor_final_es_factura_b(): void
    {
        $receptor = new Receptor(null, 'Consumidor', null, CondicionFiscal::CONSUMIDOR_FINAL);

        $letra = $this->rule->determinar($this->emisorRi(), $receptor);
        $this->assertEquals(LetraComprobante::B, $letra);
    }

    public function test_ri_sin_receptor_es_factura_b(): void
    {
        $letra = $this->rule->determinar($this->emisorRi(), null);
        $this->assertEquals(LetraComprobante::B, $letra);
    }

    public function test_emisor_monotributo_no_emite(): void
    {
        $emisorMonotributo = new Emisor(
            new Cuit('20123456786'),
            'Kiosco Mono',
            CondicionFiscal::MONOTRIBUTO
        );

        $this->expectException(EmisorNoElegibleException::class);
        $this->rule->determinar($emisorMonotributo, null);
    }
}
