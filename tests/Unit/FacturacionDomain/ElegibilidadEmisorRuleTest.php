<?php

namespace Tests\Unit\FacturacionDomain;

use App\Facturacion\Domain\Entities\Emisor;
use App\Facturacion\Domain\Rules\ElegibilidadEmisorRule;
use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Domain\ValueObjects\Cuit;
use PHPUnit\Framework\TestCase;

class ElegibilidadEmisorRuleTest extends TestCase
{
    private ElegibilidadEmisorRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new ElegibilidadEmisorRule;
    }

    private function emisor(CondicionFiscal $condicion): Emisor
    {
        return new Emisor(new Cuit('20123456786'), 'Emisor', $condicion);
    }

    public function test_responsable_inscripto_es_elegible(): void
    {
        $this->assertTrue($this->rule->esElegible($this->emisor(CondicionFiscal::RESPONSABLE_INSCRIPTO)));
    }

    public function test_monotributo_no_es_elegible_y_motivo_es_no_soportado(): void
    {
        $emisor = $this->emisor(CondicionFiscal::MONOTRIBUTO);

        $this->assertFalse($this->rule->esElegible($emisor));
        $this->assertStringContainsString('monotributista', $this->rule->motivoNoElegible($emisor));
    }

    public function test_otras_condiciones_no_son_elegibles(): void
    {
        $this->assertFalse($this->rule->esElegible($this->emisor(CondicionFiscal::CONSUMIDOR_FINAL)));
        $this->assertFalse($this->rule->esElegible($this->emisor(CondicionFiscal::EXENTO)));
    }
}
