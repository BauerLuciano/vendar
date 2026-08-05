<?php

namespace Tests\Unit\FacturacionDomain;

use App\Facturacion\Domain\Calculators\DesgloseIvaCalculator;
use App\Facturacion\Domain\Entities\ComprobanteFiscal;
use App\Facturacion\Domain\Entities\Emisor;
use App\Facturacion\Domain\Entities\PuntoVenta;
use App\Facturacion\Domain\Exceptions\ReglaNotaCreditoException;
use App\Facturacion\Domain\Rules\ReglasNotaCredito;
use App\Facturacion\Domain\ValueObjects\Alicuota;
use App\Facturacion\Domain\ValueObjects\Cae;
use App\Facturacion\Domain\ValueObjects\Concepto;
use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Domain\ValueObjects\Importe;
use App\Facturacion\Domain\ValueObjects\LetraComprobante;
use App\Facturacion\Domain\ValueObjects\TipoComprobante;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ReglasNotaCreditoTest extends TestCase
{
    private ReglasNotaCredito $rule;

    private ComprobanteFiscal $comprobante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule = new ReglasNotaCredito;

        $emisor = new Emisor(new Cuit('20123456786'), 'Emisor RI', CondicionFiscal::RESPONSABLE_INSCRIPTO);
        $desglose = new DesgloseIvaCalculator;
        $detalle = $desglose->construirDetalle(2, new Importe(1000.0), new Alicuota(21.0));
        $cae = new Cae('12345678901234', new DateTimeImmutable('2030-01-01'));

        $this->comprobante = new ComprobanteFiscal(
            comercioId: 1,
            ventaId: 1,
            puntoVenta: new PuntoVenta(1),
            tipo: TipoComprobante::FACTURA,
            letra: LetraComprobante::B,
            concepto: Concepto::PRODUCTOS,
            emisor: $emisor,
            cae: $cae,
            detalles: [$detalle],
            numero: 100,
        );
    }

    public function test_monto_nc_total_igual_total_original(): void
    {
        $monto = $this->rule->montoNcTotal($this->comprobante);

        $this->assertEquals($this->comprobante->total()->valor(), $monto->valor());
        $this->assertEquals(2000.0, $monto->valor());
    }

    public function test_monto_nc_parcial_validacion(): void
    {
        $monto = $this->rule->montoNcParcial($this->comprobante, new Importe(500.0));
        $this->assertEquals(500.0, $monto->valor());
    }

    public function test_monto_nc_parcial_igual_total_es_valido(): void
    {
        $monto = $this->rule->montoNcParcial($this->comprobante, $this->comprobante->total());
        $this->assertEquals(2000.0, $monto->valor());
    }

    public function test_monto_nc_parcial_cero_rechazado(): void
    {
        $this->expectException(ReglaNotaCreditoException::class);
        $this->rule->montoNcParcial($this->comprobante, Importe::cero());
    }

    public function test_monto_nc_parcial_negativo_rechazado(): void
    {
        $this->expectException(ReglaNotaCreditoException::class);
        $this->rule->montoNcParcial($this->comprobante, new Importe(-100.0));
    }

    public function test_monto_nc_parcial_que_supera_total_rechazado(): void
    {
        $this->expectException(ReglaNotaCreditoException::class);
        $this->rule->montoNcParcial($this->comprobante, new Importe(2000.01));
    }
}
