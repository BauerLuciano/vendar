<?php

namespace Tests\Feature\Facturacion;

use App\Facturacion\Domain\Calculators\DesgloseIvaCalculator;
use App\Facturacion\Domain\Entities\ComprobanteFiscal;
use App\Facturacion\Domain\Entities\Emisor;
use App\Facturacion\Domain\Entities\PuntoVenta;
use App\Facturacion\Domain\Entities\Receptor;
use App\Facturacion\Domain\Exceptions\FacturacionDomainException;
use App\Facturacion\Domain\ValueObjects\Alicuota;
use App\Facturacion\Domain\ValueObjects\Cae;
use App\Facturacion\Domain\ValueObjects\Concepto;
use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Domain\ValueObjects\Importe;
use App\Facturacion\Domain\ValueObjects\LetraComprobante;
use App\Facturacion\Domain\ValueObjects\TipoComprobante;
use App\Facturacion\Infrastructure\Arca\QrArcaPayloadBuilder;
use App\Facturacion\Infrastructure\Persistence\EloquentComprobanteFiscalRepository;
use App\Models\ComprobanteFiscal as ComprobanteFiscalModel;
use App\Models\ConfiguracionFiscalComercio;
use App\Models\Consumidor;
use App\Models\DetalleVenta;
use App\Models\Venta;
use DateTimeImmutable;
use Tests\TestCaseMultiTenant;

class EloquentComprobanteFiscalRepositoryTest extends TestCaseMultiTenant
{
    private int $ventaId;

    protected function setUp(): void
    {
        parent::setUp();

        $receptor = Consumidor::create([
            'comercio_id' => 1,
            'nombre' => 'Receptor',
            'cuit' => '30500010912',
            'razon_social' => 'Receptor RI',
            'domicilio_fiscal' => 'Calle 123',
        ]);

        $this->ventaId = Venta::create([
            'turno_caja_id' => 1,
            'consumidor_id' => $receptor->id,
            'metodo_pago' => 'EFECTIVO',
            'total' => 1210,
            'estado' => 'Completada',
        ])->id;

        DetalleVenta::create([
            'venta_id' => $this->ventaId,
            'producto_id' => 1,
            'cantidad' => 1,
            'precio_unitario' => 1210,
            'subtotal' => 1210,
            'alicuota_iva' => 21.0,
        ]);

        ConfiguracionFiscalComercio::create([
            'comercio_id' => 1,
            'cuit' => '20123456786',
            'razon_social' => 'Comercio RI',
            'condicion_fiscal' => 'responsable_inscripto',
            'domicilio_fiscal' => 'Calle 1',
            'estado_modulo' => 'listo_para_facturar',
        ]);
    }

    public function test_guarda_y_asigna_id_al_ledger(): void
    {
        $comprobante = $this->comprobante();

        $guardado = (new EloquentComprobanteFiscalRepository(new DesgloseIvaCalculator, new QrArcaPayloadBuilder))->guardar($comprobante);

        $this->assertNotNull($guardado->id());
        $this->assertSame(1, ComprobanteFiscalModel::count());
        $this->assertSame('emitido', ComprobanteFiscalModel::first()->estado);
    }

    public function test_buscar_por_venta_reconstruye_la_entidad(): void
    {
        $repositorio = new EloquentComprobanteFiscalRepository(new DesgloseIvaCalculator, new QrArcaPayloadBuilder);
        $repositorio->guardar($this->comprobante());

        $recuperado = $repositorio->buscarPorVenta($this->ventaId, 1);

        $this->assertNotNull($recuperado);
        $this->assertEquals(LetraComprobante::B, $recuperado->letra());
        $this->assertEquals(TipoComprobante::FACTURA, $recuperado->tipo());
        $this->assertSame(1, $recuperado->numero());
        $this->assertEquals('12345678901234', $recuperado->cae()?->codigo());
        $this->assertTrue($recuperado->esEmitido());
        $this->assertEquals(1000.0, $recuperado->neto()->valor());
        $this->assertEquals(210.0, $recuperado->iva()->valor());
        $this->assertEquals(1210.0, $recuperado->total()->valor());
        $this->assertEquals('Comercio RI', $recuperado->emisor()->razonSocial());
        $this->assertNotNull($recuperado->receptor());
        $this->assertTrue($recuperado->receptor()->cuit()->esIgual(new Cuit('30500010912')));
    }

    public function test_buscar_por_venta_respeta_el_comercio(): void
    {
        $repositorio = new EloquentComprobanteFiscalRepository(new DesgloseIvaCalculator, new QrArcaPayloadBuilder);
        $repositorio->guardar($this->comprobante());

        $this->assertNull($repositorio->buscarPorVenta($this->ventaId, 2));
    }

    public function test_listar_por_comercio_no_filtra_datos_de_otro_comercio(): void
    {
        $repositorio = new EloquentComprobanteFiscalRepository(new DesgloseIvaCalculator, new QrArcaPayloadBuilder);
        $repositorio->guardar($this->comprobante());

        $this->assertCount(1, $repositorio->listarPorComercio(1));
        $this->assertCount(0, $repositorio->listarPorComercio(2));
    }

    public function test_el_ledger_es_inmutable(): void
    {
        $repositorio = new EloquentComprobanteFiscalRepository(new DesgloseIvaCalculator, new QrArcaPayloadBuilder);
        $repositorio->guardar($this->comprobante());

        $this->assertSame(1, ComprobanteFiscalModel::where('venta_id', $this->ventaId)->count());

        $repositorio->guardar($this->comprobante(numero: 2));

        $this->assertSame(2, ComprobanteFiscalModel::count());
    }

    public function test_rechaza_reconstruccion_sin_snapshot_de_alicuota(): void
    {
        $ventaSinSnapshot = Venta::create([
            'turno_caja_id' => 1,
            'consumidor_id' => 1,
            'metodo_pago' => 'EFECTIVO',
            'total' => 500,
            'estado' => 'Completada',
        ]);

        DetalleVenta::create([
            'venta_id' => $ventaSinSnapshot->id,
            'producto_id' => 2,
            'cantidad' => 1,
            'precio_unitario' => 500,
            'subtotal' => 500,
        ]);

        ComprobanteFiscalModel::create([
            'venta_id' => $ventaSinSnapshot->id,
            'comercio_id' => 1,
            'punto_venta' => 1,
            'tipo' => 'factura',
            'letra' => 'B',
            'numero' => 9,
            'neto' => 413.22,
            'iva' => 86.78,
            'total' => 500,
            'estado' => 'emitido',
        ]);

        $this->expectException(FacturacionDomainException::class);
        (new EloquentComprobanteFiscalRepository(new DesgloseIvaCalculator, new QrArcaPayloadBuilder))->buscarPorVenta($ventaSinSnapshot->id, 1);
    }

    private function comprobante(int $numero = 1): ComprobanteFiscal
    {
        $emisor = new Emisor(new Cuit('20123456786'), 'Comercio RI', CondicionFiscal::RESPONSABLE_INSCRIPTO);
        $receptor = new Receptor(new Cuit('30500010912'), 'Receptor RI', 'Calle 123');
        $desglose = new DesgloseIvaCalculator;
        $detalle = $desglose->construirDetalle(1, new Importe(1210.0), new Alicuota(21.0));

        return new ComprobanteFiscal(
            comercioId: 1,
            ventaId: $this->ventaId,
            puntoVenta: new PuntoVenta(1),
            tipo: TipoComprobante::FACTURA,
            letra: LetraComprobante::B,
            concepto: Concepto::PRODUCTOS,
            emisor: $emisor,
            cae: new Cae('12345678901234', new DateTimeImmutable('2030-01-01')),
            detalles: [$detalle],
            receptor: $receptor,
            numero: $numero,
        );
    }
}
