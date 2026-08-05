<?php

namespace Tests\Unit\FacturacionDomain;

use App\Facturacion\Domain\Calculators\RedondeoCalculator;
use PHPUnit\Framework\TestCase;

class RedondeoCalculatorTest extends TestCase
{
    public function test_redondea_a_2_decimales_hacia_arriba(): void
    {
        $this->assertEquals(1.00, RedondeoCalculator::redondear(1.004));
        $this->assertEquals(1.01, RedondeoCalculator::redondear(1.005));
        $this->assertEquals(10.57, RedondeoCalculator::redondear(10.5678));
    }

    public function test_iva_absorbe_el_redondeo(): void
    {
        $totalLinea = 1000.0;
        $neto = 826.45;

        $iva = RedondeoCalculator::ivaDesdeTotal($totalLinea, $neto);

        $this->assertEquals(173.55, $iva);
        $this->assertEquals($totalLinea, $neto + $iva);
    }
}
