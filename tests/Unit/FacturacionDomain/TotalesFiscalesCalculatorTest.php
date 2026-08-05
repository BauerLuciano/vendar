<?php

namespace Tests\Unit\FacturacionDomain;

use App\Facturacion\Domain\Calculators\DesgloseIvaCalculator;
use App\Facturacion\Domain\Calculators\TotalesFiscalesCalculator;
use App\Facturacion\Domain\Entities\DetalleFiscal;
use App\Facturacion\Domain\ValueObjects\Alicuota;
use App\Facturacion\Domain\ValueObjects\Importe;
use PHPUnit\Framework\TestCase;

class TotalesFiscalesCalculatorTest extends TestCase
{
    private TotalesFiscalesCalculator $calculator;

    private DesgloseIvaCalculator $desglose;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TotalesFiscalesCalculator;
        $this->desglose = new DesgloseIvaCalculator;
    }

    private function detalle(float $cantidad, float $precio, float $alicuota): DetalleFiscal
    {
        return $this->desglose->construirDetalle($cantidad, new Importe($precio), new Alicuota($alicuota));
    }

    public function test_totales_neto_mas_iva_igual_total(): void
    {
        $detalles = [
            $this->detalle(1, 1000.0, 21.0),
            $this->detalle(2, 500.0, 10.5),
            $this->detalle(3, 100.0, 0.0),
        ];

        $totales = $this->calculator->calcular($detalles);

        $this->assertEquals(
            $totales->neto()->valor() + $totales->iva()->valor(),
            $totales->total()->valor()
        );
    }

    public function test_total_igual_suma_de_lineas(): void
    {
        $detalles = [
            $this->detalle(1, 1000.0, 21.0),
            $this->detalle(2, 500.0, 21.0),
        ];

        $totales = $this->calculator->calcular($detalles);

        $this->assertEquals(2000.0, $totales->total()->valor());
    }

    public function test_vacio_da_cero(): void
    {
        $totales = $this->calculator->calcular([]);

        $this->assertEquals(0.0, $totales->neto()->valor());
        $this->assertEquals(0.0, $totales->iva()->valor());
        $this->assertEquals(0.0, $totales->total()->valor());
    }

    public function test_calcular_desde_desgloses(): void
    {
        $desgloses = [
            $this->desglose->desglosarLinea(1, new Importe(1000.0), new Alicuota(21.0)),
            $this->desglose->desglosarLinea(1, new Importe(500.0), new Alicuota(21.0)),
        ];

        $totales = $this->calculator->calcularDesdeDesgloses($desgloses);

        $this->assertEquals(1500.0, $totales->total()->valor());
        $this->assertEquals(
            $totales->neto()->valor() + $totales->iva()->valor(),
            $totales->total()->valor()
        );
    }
}
