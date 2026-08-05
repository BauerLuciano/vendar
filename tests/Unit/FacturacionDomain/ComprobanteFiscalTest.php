<?php

namespace Tests\Unit\FacturacionDomain;

use App\Facturacion\Domain\Calculators\DesgloseIvaCalculator;
use App\Facturacion\Domain\Entities\ComprobanteFiscal;
use App\Facturacion\Domain\Entities\Emisor;
use App\Facturacion\Domain\Entities\PuntoVenta;
use App\Facturacion\Domain\Exceptions\FacturacionDomainException;
use App\Facturacion\Domain\ValueObjects\Alicuota;
use App\Facturacion\Domain\ValueObjects\Cae;
use App\Facturacion\Domain\ValueObjects\Concepto;
use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Domain\ValueObjects\Importe;
use App\Facturacion\Domain\ValueObjects\LetraComprobante;
use App\Facturacion\Domain\ValueObjects\TipoComprobante;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ComprobanteFiscalTest extends TestCase
{
    private function comprobante(TipoComprobante $tipo, LetraComprobante $letra): ComprobanteFiscal
    {
        $emisor = new Emisor(new Cuit('20123456786'), 'Emisor RI', CondicionFiscal::RESPONSABLE_INSCRIPTO);
        $desglose = new DesgloseIvaCalculator;
        $detalle = $desglose->construirDetalle(1, new Importe(1000.0), new Alicuota(21.0));
        $cae = new Cae('12345678901234', new DateTimeImmutable('2030-01-01'));

        return new ComprobanteFiscal(
            comercioId: 1,
            ventaId: 1,
            puntoVenta: new PuntoVenta(1),
            tipo: $tipo,
            letra: $letra,
            concepto: Concepto::PRODUCTOS,
            emisor: $emisor,
            cae: $cae,
            detalles: [$detalle],
            numero: 100,
        );
    }

    public function test_totales_consisten(): void
    {
        $comprobante = $this->comprobante(TipoComprobante::FACTURA, LetraComprobante::B);

        $this->assertEquals(826.45, $comprobante->neto()->valor());
        $this->assertEquals(173.55, $comprobante->iva()->valor());
        $this->assertEquals(1000.0, $comprobante->total()->valor());
        $this->assertEquals(
            $comprobante->neto()->valor() + $comprobante->iva()->valor(),
            $comprobante->total()->valor()
        );
    }

    public function test_codigo_afip_por_tipo_y_letra(): void
    {
        $this->assertEquals(1, $this->comprobante(TipoComprobante::FACTURA, LetraComprobante::A)->tipo()->codigoAfip(LetraComprobante::A));
        $this->assertEquals(6, $this->comprobante(TipoComprobante::FACTURA, LetraComprobante::B)->tipo()->codigoAfip(LetraComprobante::B));
        $this->assertEquals(3, $this->comprobante(TipoComprobante::NOTA_CREDITO, LetraComprobante::A)->tipo()->codigoAfip(LetraComprobante::A));
        $this->assertEquals(8, $this->comprobante(TipoComprobante::NOTA_CREDITO, LetraComprobante::B)->tipo()->codigoAfip(LetraComprobante::B));
    }

    public function test_es_emitido_por_defecto(): void
    {
        $comprobante = $this->comprobante(TipoComprobante::FACTURA, LetraComprobante::B);
        $this->assertTrue($comprobante->esEmitido());
    }

    public function test_nota_credito_se_reconoce(): void
    {
        $this->assertTrue($this->comprobante(TipoComprobante::NOTA_CREDITO, LetraComprobante::A)->esNotaCredito());
        $this->assertFalse($this->comprobante(TipoComprobante::FACTURA, LetraComprobante::A)->esNotaCredito());
    }

    public function test_rechaza_comprobante_sin_detalles(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $emisor = new Emisor(new Cuit('20123456786'), 'Emisor RI', CondicionFiscal::RESPONSABLE_INSCRIPTO);
        $cae = new Cae('12345678901234', new DateTimeImmutable('2030-01-01'));

        new ComprobanteFiscal(
            comercioId: 1,
            ventaId: 1,
            puntoVenta: new PuntoVenta(1),
            tipo: TipoComprobante::FACTURA,
            letra: LetraComprobante::B,
            concepto: Concepto::PRODUCTOS,
            emisor: $emisor,
            cae: $cae,
            detalles: [],
        );
    }

    public function test_rechaza_punto_de_venta_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PuntoVenta(0);
    }

    public function test_cae_rechaza_longitud_incorrecta(): void
    {
        $this->expectException(FacturacionDomainException::class);
        new Cae('123', new DateTimeImmutable('2030-01-01'));
    }
}
