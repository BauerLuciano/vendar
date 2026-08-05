<?php

namespace Tests\Unit\FacturacionDomain;

use App\Facturacion\Domain\Calculators\DesgloseIvaCalculator;
use App\Facturacion\Domain\Entities\ConfiguracionFiscal;
use App\Facturacion\Domain\Entities\Emisor;
use App\Facturacion\Domain\Entities\PuntoVenta;
use App\Facturacion\Domain\Entities\Receptor;
use App\Facturacion\Domain\Exceptions\ComercioNoListoException;
use App\Facturacion\Domain\Exceptions\EmisorNoElegibleException;
use App\Facturacion\Domain\Exceptions\ReceptorNoAptoException;
use App\Facturacion\Domain\Rules\DeterminacionLetraRule;
use App\Facturacion\Domain\Rules\ElegibilidadEmisorRule;
use App\Facturacion\Domain\Services\EmisionService;
use App\Facturacion\Domain\Services\NumeracionService;
use App\Facturacion\Domain\Services\SolicitudEmision;
use App\Facturacion\Domain\ValueObjects\Alicuota;
use App\Facturacion\Domain\ValueObjects\Concepto;
use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Domain\ValueObjects\EstadoComprobante;
use App\Facturacion\Domain\ValueObjects\EstadoModuloFiscal;
use App\Facturacion\Domain\ValueObjects\Importe;
use App\Facturacion\Domain\ValueObjects\LetraComprobante;
use App\Facturacion\Domain\ValueObjects\TipoComprobante;
use PHPUnit\Framework\TestCase;
use Tests\Support\FacturacionDomain\FakeComprobanteFiscalRepository;
use Tests\Support\FacturacionDomain\FakeConfiguracionFiscalRepository;
use Tests\Support\FacturacionDomain\FakeWsfet;

class EmisionServiceTest extends TestCase
{
    private FakeConfiguracionFiscalRepository $configuracion;

    private FakeComprobanteFiscalRepository $repositorio;

    private FakeWsfet $wsfet;

    private EmisionService $service;

    private DesgloseIvaCalculator $desglose;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configuracion = new FakeConfiguracionFiscalRepository($this->configuracionLista());
        $this->repositorio = new FakeComprobanteFiscalRepository;
        $this->wsfet = new FakeWsfet;
        $this->desglose = new DesgloseIvaCalculator;

        $this->service = new EmisionService(
            $this->configuracion,
            $this->repositorio,
            new ElegibilidadEmisorRule,
            new DeterminacionLetraRule,
            new NumeracionService($this->repositorio),
            $this->wsfet,
        );
    }

    public function test_sin_configuracion_lanza_comercio_no_listo(): void
    {
        $this->configuracion->configuracion = null;

        $this->expectException(ComercioNoListoException::class);
        $this->service->emitir($this->solicitud());
    }

    public function test_configuracion_no_lista_lanza_comercio_no_listo(): void
    {
        $this->configuracion->configuracion = $this->configuracionConEstado(EstadoModuloFiscal::SIN_DATOS);

        $this->expectException(ComercioNoListoException::class);
        $this->service->emitir($this->solicitud());
    }

    public function test_emisor_monotributo_lanza_emisor_no_elegible(): void
    {
        $emisor = new Emisor(new Cuit('20123456786'), 'Emisor Mono', CondicionFiscal::MONOTRIBUTO);

        $this->expectException(EmisorNoElegibleException::class);
        $this->service->emitir($this->solicitud($emisor));
    }

    public function test_factura_a_con_receptor_ri_sin_datos_fiscales_lanza_receptor_no_apto(): void
    {
        $receptor = new Receptor(
            cuit: null,
            razonSocial: 'Receptor',
            domicilioFiscal: null,
            condicionFiscal: CondicionFiscal::RESPONSABLE_INSCRIPTO,
        );

        $this->expectException(ReceptorNoAptoException::class);
        $this->service->emitir($this->solicitud(receptor: $receptor));
    }

    public function test_receptor_monotributo_resuelve_factura_b(): void
    {
        $receptor = new Receptor(
            cuit: new Cuit('20123456786'),
            razonSocial: 'Receptor',
            domicilioFiscal: 'Calle 1',
            condicionFiscal: CondicionFiscal::MONOTRIBUTO,
        );

        $comprobante = $this->service->emitir($this->solicitud(receptor: $receptor));

        $this->assertEquals(LetraComprobante::B, $comprobante->letra());
    }

    public function test_emite_factura_a_a_receptor_ri(): void
    {
        $comprobante = $this->service->emitir($this->solicitud(receptor: $this->receptorRi()));

        $this->assertEquals(LetraComprobante::A, $comprobante->letra());
        $this->assertTrue($comprobante->esEmitido());
        $this->assertEquals(1, $comprobante->numero());
        $this->assertNotNull($comprobante->cae());
        $this->assertSame($comprobante, $this->repositorio->ultimoGuardado);
    }

    public function test_emite_factura_b_a_consumidor_final(): void
    {
        $comprobante = $this->service->emitir($this->solicitud());

        $this->assertEquals(LetraComprobante::B, $comprobante->letra());
        $this->assertEquals(TipoComprobante::FACTURA, $comprobante->tipo());
        $this->assertEquals(1, $comprobante->numero());
        $this->assertNotNull($comprobante->cae());
    }

    public function test_el_cae_se_persiste_en_el_ledger(): void
    {
        $this->service->emitir($this->solicitud());

        $this->assertEquals('12345678901234', $this->repositorio->ultimoGuardado->cae()?->codigo());
        $this->assertEquals(EstadoComprobante::EMITIDO, $this->repositorio->ultimoGuardado->estado());
    }

    public function test_persiste_totales_desglosados(): void
    {
        $this->service->emitir($this->solicitud());

        $this->assertEquals(1000.0, $this->repositorio->ultimoGuardado->neto()->valor());
        $this->assertEquals(210.0, $this->repositorio->ultimoGuardado->iva()->valor());
        $this->assertEquals(1210.0, $this->repositorio->ultimoGuardado->total()->valor());
    }

    private function solicitud(?Emisor $emisor = null, ?Receptor $receptor = null): SolicitudEmision
    {
        return new SolicitudEmision(
            comercioId: 7,
            ventaId: 10,
            puntoVenta: new PuntoVenta(4),
            tipo: TipoComprobante::FACTURA,
            concepto: Concepto::PRODUCTOS,
            emisor: $emisor ?? $this->emisorRi(),
            detalles: [$this->detalle()],
            receptor: $receptor,
        );
    }

    private function emisorRi(): Emisor
    {
        return new Emisor(new Cuit('20123456786'), 'Comercio RI', CondicionFiscal::RESPONSABLE_INSCRIPTO);
    }

    private function receptorRi(): Receptor
    {
        return new Receptor(
            cuit: new Cuit('30500010912'),
            razonSocial: 'Receptor RI',
            domicilioFiscal: 'Calle 123',
            condicionFiscal: CondicionFiscal::RESPONSABLE_INSCRIPTO,
        );
    }

    private function detalle()
    {
        return $this->desglose->construirDetalle(1, new Importe(1210.0), new Alicuota(21.0));
    }

    private function configuracionLista(): ConfiguracionFiscal
    {
        return $this->configuracionConEstado(EstadoModuloFiscal::LISTO_PARA_FACTURAR);
    }

    private function configuracionConEstado(EstadoModuloFiscal $estado): ConfiguracionFiscal
    {
        return new ConfiguracionFiscal(
            comercioId: 7,
            cuit: new Cuit('20123456786'),
            razonSocial: 'Comercio RI',
            condicionFiscal: CondicionFiscal::RESPONSABLE_INSCRIPTO,
            domicilioFiscal: 'Calle 1',
            entorno: 'homologacion',
            puntoVentaActivo: 4,
            estadoModulo: $estado,
        );
    }
}
