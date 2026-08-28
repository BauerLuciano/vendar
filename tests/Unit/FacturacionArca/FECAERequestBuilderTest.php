<?php

namespace Tests\Unit\FacturacionArca;

use App\Facturacion\Domain\Calculators\DesgloseIvaCalculator;
use App\Facturacion\Domain\Entities\ComprobanteFiscal;
use App\Facturacion\Domain\Entities\Emisor;
use App\Facturacion\Domain\Entities\PuntoVenta;
use App\Facturacion\Domain\Entities\Receptor;
use App\Facturacion\Domain\ValueObjects\Alicuota;
use App\Facturacion\Domain\ValueObjects\Cae;
use App\Facturacion\Domain\ValueObjects\Concepto;
use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Domain\ValueObjects\Importe;
use App\Facturacion\Domain\ValueObjects\LetraComprobante;
use App\Facturacion\Domain\ValueObjects\TipoComprobante;
use App\Facturacion\Infrastructure\Arca\Wsfe\FECAERequestBuilder;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\FacturacionArca\GeneraPfx;

class FECAERequestBuilderTest extends TestCase
{
    private FECAERequestBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new FECAERequestBuilder;
    }

    private function comprobante(
        array $detalles,
        TipoComprobante $tipo = TipoComprobante::FACTURA,
        LetraComprobante $letra = LetraComprobante::B,
        ?Receptor $receptor = null,
        int $numero = 100,
    ): ComprobanteFiscal {
        $emisor = new Emisor(new Cuit(GeneraPfx::CUIT_VALIDO), 'Emisor RI', CondicionFiscal::RESPONSABLE_INSCRIPTO);
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
            detalles: $detalles,
            receptor: $receptor,
            numero: $numero,
            comprobanteOriginalId: $tipo->esNotaCredito() ? 42 : null,
        );
    }

    private function detalle21(float $precio): object
    {
        return (new DesgloseIvaCalculator)->construirDetalle(1, new Importe($precio), new Alicuota(21.0));
    }

    public function test_factura_b_consumidor_final(): void
    {
        $request = $this->builder->construir(
            $this->comprobante([$this->detalle21(1000.0)]),
            new DateTimeImmutable('2026-08-03 10:00:00')
        );

        $detalle = $request['FeDetReq']['FECAEDetRequest'];

        $this->assertSame(1, $request['FeCabReq']['CantReg']);
        $this->assertSame(1, $request['FeCabReq']['PtoVta']);
        $this->assertSame(6, $request['FeCabReq']['CbteTipo']);

        $this->assertSame(1, $detalle['Concepto']);
        $this->assertSame(96, $detalle['DocTipo']);
        $this->assertSame(0, $detalle['DocNro']);
        $this->assertSame(100, $detalle['CbteDesde']);
        $this->assertSame(100, $detalle['CbteHasta']);
        $this->assertSame('20260803', $detalle['CbteFch']);
        $this->assertSame('PES', $detalle['MonId']);
        $this->assertSame(1, $detalle['MonCotiz']);
        $this->assertSame(5, $detalle['CondicionIVAReceptorId']);

        $this->assertEqualsWithDelta(826.45, $detalle['ImpNeto'], 0.01);
        $this->assertEqualsWithDelta(173.55, $detalle['ImpIVA'], 0.01);
        $this->assertEqualsWithDelta(1000.0, $detalle['ImpTotal'], 0.01);

        $this->assertSame(5, $detalle['Iva']['AlicIva'][0]['Id']);
        $this->assertEqualsWithDelta(826.45, $detalle['Iva']['AlicIva'][0]['BaseImp'], 0.01);
        $this->assertEqualsWithDelta(173.55, $detalle['Iva']['AlicIva'][0]['Importe'], 0.01);
    }

    public function test_factura_a_con_receptor(): void
    {
        $receptor = new Receptor(
            new Cuit(GeneraPfx::CUIT_VALIDO),
            'Receptor RI SA',
            'Av. Siempre Viva 742',
            CondicionFiscal::RESPONSABLE_INSCRIPTO
        );

        $request = $this->builder->construir(
            $this->comprobante([$this->detalle21(1000.0)], TipoComprobante::FACTURA, LetraComprobante::A, $receptor),
            new DateTimeImmutable('2026-08-03 10:00:00')
        );

        $detalle = $request['FeDetReq']['FECAEDetRequest'];

        $this->assertSame(1, $request['FeCabReq']['CbteTipo']);
        $this->assertSame(80, $detalle['DocTipo']);
        $this->assertSame((int) GeneraPfx::CUIT_VALIDO, $detalle['DocNro']);
        $this->assertSame(1, $detalle['CondicionIVAReceptorId']);
    }

    public function test_factura_b_sobre_el_umbral_rg4444_sin_receptor_lanza(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RG 4291/4444');

        $this->builder->construir(
            $this->comprobante([$this->detalle21(15000.0)]),
            new DateTimeImmutable('2026-08-03 10:00:00')
        );
    }

    public function test_factura_b_sobre_el_umbral_rg4444_con_receptor_es_valida(): void
    {
        $receptor = new Receptor(
            new Cuit(GeneraPfx::CUIT_VALIDO),
            'Consumidor',
            'Domicilio 123',
            CondicionFiscal::CONSUMIDOR_FINAL
        );

        $request = $this->builder->construir(
            $this->comprobante([$this->detalle21(15000.0)], TipoComprobante::FACTURA, LetraComprobante::B, $receptor),
            new DateTimeImmutable('2026-08-03 10:00:00')
        );

        $detalle = $request['FeDetReq']['FECAEDetRequest'];

        $this->assertSame(80, $detalle['DocTipo']);
        $this->assertSame((int) GeneraPfx::CUIT_VALIDO, $detalle['DocNro']);
    }

    public function test_factura_b_sobre_el_umbral_con_umbral_configurable(): void
    {
        $builder = new FECAERequestBuilder(tipoDocConsumidorFinal: 96, montoMaximoB: 500.0);

        $this->expectException(InvalidArgumentException::class);

        $builder->construir(
            $this->comprobante([$this->detalle21(1000.0)]),
            new DateTimeImmutable('2026-08-03 10:00:00')
        );
    }

    public function test_factura_a_sobre_el_umbral_no_aplica_la_regla_de_clase_b(): void
    {
        $request = $this->builder->construir(
            $this->comprobante([$this->detalle21(15000.0)], TipoComprobante::FACTURA, LetraComprobante::A, null),
            new DateTimeImmutable('2026-08-03 10:00:00')
        );

        $detalle = $request['FeDetReq']['FECAEDetRequest'];

        $this->assertSame(96, $detalle['DocTipo']);
        $this->assertSame(0, $detalle['DocNro']);
    }

    public function test_condicion_iva_receptor_segun_condicion_fiscal(): void
    {
        $porCondicion = [
            CondicionFiscal::RESPONSABLE_INSCRIPTO->value => 1,
            CondicionFiscal::EXENTO->value => 4,
            CondicionFiscal::CONSUMIDOR_FINAL->value => 5,
            CondicionFiscal::MONOTRIBUTO->value => 6,
            CondicionFiscal::NO_ALCANZADO->value => 15,
        ];

        foreach ($porCondicion as $valor => $esperado) {
            $condicion = CondicionFiscal::from($valor);
            $receptor = new Receptor(
                new Cuit(GeneraPfx::CUIT_VALIDO),
                'Receptor',
                'Domicilio 123',
                $condicion
            );

            $request = $this->builder->construir(
                $this->comprobante([$this->detalle21(1000.0)], TipoComprobante::FACTURA, LetraComprobante::B, $receptor),
                new DateTimeImmutable('2026-08-03 10:00:00')
            );

            $this->assertSame(
                $esperado,
                $request['FeDetReq']['FECAEDetRequest']['CondicionIVAReceptorId'],
                "CondicionFiscal {$condicion->value}"
            );
        }
    }

    public function test_receptor_sin_condicion_se_trata_como_consumidor_final(): void
    {
        $receptor = new Receptor(new Cuit(GeneraPfx::CUIT_VALIDO), 'Receptor', 'Domicilio 123', null);

        $request = $this->builder->construir(
            $this->comprobante([$this->detalle21(1000.0)], TipoComprobante::FACTURA, LetraComprobante::B, $receptor),
            new DateTimeImmutable('2026-08-03 10:00:00')
        );

        $this->assertSame(5, $request['FeDetReq']['FECAEDetRequest']['CondicionIVAReceptorId']);
    }

    public function test_agrupa_por_alicuota(): void
    {
        $request = $this->builder->construir(
            $this->comprobante([
                $this->detalle21(1210.45),
                $this->detalle21(790.55),
                (new DesgloseIvaCalculator)->construirDetalle(1, new Importe(500.0), new Alicuota(10.5)),
            ]),
            new DateTimeImmutable('2026-08-03 10:00:00')
        );

        $detalle = $request['FeDetReq']['FECAEDetRequest'];
        $alicuotas = $detalle['Iva']['AlicIva'];

        $this->assertCount(2, $alicuotas);

        $porId = [];
        foreach ($alicuotas as $alicuota) {
            $porId[$alicuota['Id']] = $alicuota;
        }

        $this->assertEqualsWithDelta(1653.72, $porId[5]['BaseImp'], 0.01);
        $this->assertEqualsWithDelta(347.28, $porId[5]['Importe'], 0.01);
        $this->assertEqualsWithDelta(452.49, $porId[4]['BaseImp'], 0.01);
        $this->assertEqualsWithDelta(47.51, $porId[4]['Importe'], 0.01);

        $this->assertEqualsWithDelta(
            round($detalle['ImpNeto'] + $detalle['ImpOpEx'] + $detalle['ImpIVA'], 2),
            $detalle['ImpTotal'],
            0.01
        );
    }

    public function test_exenta_va_en_imp_op_ex_y_no_en_el_iva(): void
    {
        $request = $this->builder->construir(
            $this->comprobante([
                $this->detalle21(1210.0),
                (new DesgloseIvaCalculator)->construirDetalle(1, new Importe(500.0), new Alicuota(0.0)),
            ]),
            new DateTimeImmutable('2026-08-03 10:00:00')
        );

        $detalle = $request['FeDetReq']['FECAEDetRequest'];

        $this->assertEqualsWithDelta(500.0, $detalle['ImpOpEx'], 0.01);
        $this->assertCount(1, $detalle['Iva']['AlicIva']);
        $this->assertSame(5, $detalle['Iva']['AlicIva'][0]['Id']);
        $this->assertEqualsWithDelta(1000.0, $detalle['Iva']['AlicIva'][0]['BaseImp'], 0.01);
        $this->assertEqualsWithDelta(210.0, $detalle['ImpIVA'], 0.01);
    }

    public function test_nota_credito_incluye_cmp_asoc(): void
    {
        $request = $this->builder->construir(
            $this->comprobante([$this->detalle21(1000.0)], TipoComprobante::NOTA_CREDITO),
            new DateTimeImmutable('2026-08-03 10:00:00'),
            ['tipo' => 1, 'ptoVta' => 1, 'nro' => 50]
        );

        $detalle = $request['FeDetReq']['FECAEDetRequest'];

        $this->assertSame(8, $request['FeCabReq']['CbteTipo']);
        $this->assertSame(1, $detalle['CmpAsoc']['CmpAsoc'][0]['Tipo']);
        $this->assertSame(1, $detalle['CmpAsoc']['CmpAsoc'][0]['PtoVta']);
        $this->assertSame(50, $detalle['CmpAsoc']['CmpAsoc'][0]['Nro']);
    }

    public function test_alicuota_sin_id_afip_lanza(): void
    {
        $detalle = (new DesgloseIvaCalculator)->construirDetalle(1, new Importe(100.0), new Alicuota(15.0));

        $this->expectException(InvalidArgumentException::class);

        $this->builder->construir(
            $this->comprobante([$detalle]),
            new DateTimeImmutable('2026-08-03 10:00:00')
        );
    }

    public function test_totales_coinciden_con_el_dominio(): void
    {
        $comprobante = $this->comprobante([$this->detalle21(1000.0)]);

        $request = $this->builder->construir(
            $comprobante,
            new DateTimeImmutable('2026-08-03 10:00:00')
        );

        $detalle = $request['FeDetReq']['FECAEDetRequest'];

        $this->assertEqualsWithDelta($comprobante->total()->valor(), $detalle['ImpTotal'], 0.01);
        $this->assertEqualsWithDelta($comprobante->neto()->valor(), $detalle['ImpNeto'], 0.01);
        $this->assertEqualsWithDelta($comprobante->iva()->valor(), $detalle['ImpIVA'], 0.01);
    }
}
