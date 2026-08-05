<?php

namespace Tests\Unit\FacturacionDomain;

use App\Facturacion\Domain\Calculators\DesgloseIvaCalculator;
use App\Facturacion\Domain\Calculators\RedondeoCalculator;
use App\Facturacion\Domain\ValueObjects\Alicuota;
use App\Facturacion\Domain\ValueObjects\Importe;
use PHPUnit\Framework\TestCase;

class DesgloseIvaCalculatorTest extends TestCase
{
    private DesgloseIvaCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new DesgloseIvaCalculator;
    }

    public function test_desglosa_linea_con_iva_incluido(): void
    {
        // precio_venta = 1000 con IVA incluido, alícuota 21%.
        $desglose = $this->calculator->desglosarLinea(1, new Importe(1000.0), new Alicuota(21.0));

        $this->assertEquals(826.45, $desglose->neto()->valor());
        $this->assertEquals(173.55, $desglose->iva()->valor());
        $this->assertEquals(1000.0, $desglose->total()->valor());
    }

    public function test_neto_mas_iva_igual_total_sin_redondeo_perdido(): void
    {
        $precios = [1000.0, 1234.56, 99.99, 0.50];
        $cantidades = [1, 2, 3, 7];

        foreach ($precios as $precio) {
            foreach ($cantidades as $cantidad) {
                $desglose = $this->calculator->desglosarLinea($cantidad, new Importe($precio), new Alicuota(21.0));

                $neto = $desglose->neto()->valor();
                $iva = $desglose->iva()->valor();
                $total = $desglose->total()->valor();

                $this->assertEquals(
                    (int) round(($neto + $iva) * 100),
                    (int) round($total * 100),
                    "Fallo para precio {$precio} x {$cantidad}"
                );
                $this->assertEquals(
                    (int) round(RedondeoCalculator::redondear($precio * $cantidad) * 100),
                    (int) round($total * 100)
                );
            }
        }
    }

    public function test_alicuota_10_5(): void
    {
        $desglose = $this->calculator->desglosarLinea(1, new Importe(1000.0), new Alicuota(10.5));

        $this->assertEquals(904.98, $desglose->neto()->valor());
        $this->assertEquals(95.02, $desglose->iva()->valor());
        $this->assertEquals(1000.0, $desglose->total()->valor());
    }

    public function test_alicuota_exenta(): void
    {
        $desglose = $this->calculator->desglosarLinea(2, new Importe(500.0), new Alicuota(0.0));

        $this->assertEquals(1000.0, $desglose->neto()->valor());
        $this->assertEquals(0.0, $desglose->iva()->valor());
        $this->assertEquals(1000.0, $desglose->total()->valor());
    }

    public function test_construye_detalle_con_snapshot_de_alicuota(): void
    {
        $detalle = $this->calculator->construirDetalle(1, new Importe(1000.0), new Alicuota(21.0));

        $this->assertEquals(1, $detalle->cantidad());
        $this->assertEquals(826.45, $detalle->neto()->valor());
        $this->assertEquals(173.55, $detalle->iva()->valor());
        $this->assertEquals(1000.0, $detalle->totalLinea()->valor());
        $this->assertTrue((new Alicuota(21.0))->esIgual($detalle->alicuota()));
    }
}
