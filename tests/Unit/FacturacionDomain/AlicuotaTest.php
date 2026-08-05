<?php

namespace Tests\Unit\FacturacionDomain;

use App\Facturacion\Domain\Exceptions\AlicuotaInvalidaException;
use App\Facturacion\Domain\ValueObjects\Alicuota;
use PHPUnit\Framework\TestCase;

class AlicuotaTest extends TestCase
{
    public function test_valor_y_factor(): void
    {
        $general = new Alicuota(21.0);
        $this->assertEquals(21.0, $general->valor());
        $this->assertEquals(1.21, $general->factor());

        $reducida = new Alicuota(10.5);
        $this->assertEquals(1.105, $reducida->factor());
    }

    public function test_alicuota_general(): void
    {
        $this->assertEquals(21.0, Alicuota::general()->valor());
    }

    public function test_alicuota_exenta(): void
    {
        $exenta = new Alicuota(0.0);
        $this->assertTrue($exenta->esExenta());
        $this->assertEquals(1.0, $exenta->factor());

        $this->assertFalse((new Alicuota(21.0))->esExenta());
    }

    public function test_rechaza_alicuota_negativa(): void
    {
        $this->expectException(AlicuotaInvalidaException::class);
        new Alicuota(-5.0);
    }

    public function test_es_igual_por_valor(): void
    {
        $this->assertTrue((new Alicuota(10.5))->esIgual(new Alicuota(10.5)));
        $this->assertFalse((new Alicuota(21.0))->esIgual(new Alicuota(10.5)));
    }
}
