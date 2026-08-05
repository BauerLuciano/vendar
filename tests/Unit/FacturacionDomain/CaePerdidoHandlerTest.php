<?php

namespace Tests\Unit\FacturacionDomain;

use App\Facturacion\Domain\Calculators\DesgloseIvaCalculator;
use App\Facturacion\Domain\Entities\Emisor;
use App\Facturacion\Domain\Entities\PuntoVenta;
use App\Facturacion\Domain\Services\CaePerdidoHandler;
use App\Facturacion\Domain\Services\NumeracionService;
use App\Facturacion\Domain\Services\SolicitudEmision;
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
use Tests\Support\FacturacionDomain\FakeComprobanteFiscalRepository;
use Tests\Support\FacturacionDomain\FakeWsfet;

class CaePerdidoHandlerTest extends TestCase
{
    private FakeComprobanteFiscalRepository $repositorio;

    private FakeWsfet $wsfet;

    private CaePerdidoHandler $handler;

    private SolicitudEmision $solicitud;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repositorio = new FakeComprobanteFiscalRepository;
        $this->wsfet = new FakeWsfet;

        $this->handler = new CaePerdidoHandler(
            $this->repositorio,
            new NumeracionService($this->repositorio),
            $this->wsfet,
        );

        $desglose = new DesgloseIvaCalculator;
        $emisor = new Emisor(new Cuit('20123456786'), 'Comercio RI', CondicionFiscal::RESPONSABLE_INSCRIPTO);

        $this->solicitud = new SolicitudEmision(
            comercioId: 7,
            ventaId: 10,
            puntoVenta: new PuntoVenta(4),
            tipo: TipoComprobante::FACTURA,
            concepto: Concepto::PRODUCTOS,
            emisor: $emisor,
            detalles: [$desglose->construirDetalle(1, new Importe(1210.0), new Alicuota(21.0))],
        );
    }

    public function test_adopta_el_cae_si_arca_ya_lo_emitio(): void
    {
        $caeExistente = new Cae('99999999999999', new DateTimeImmutable('2030-06-30'));
        $this->wsfet->caeConsulta = $caeExistente;

        $resuelto = $this->handler->resolver($this->solicitud, LetraComprobante::B, 15);

        $this->assertEquals(15, $resuelto->numero());
        $this->assertEquals('99999999999999', $resuelto->cae()?->codigo());
        $this->assertEquals(0, $this->wsfet->solicitudes);
        $this->assertSame($resuelto, $this->repositorio->ultimoGuardado);
    }

    public function test_reenumera_y_solicita_cae_si_arca_no_lo_registra(): void
    {
        $this->wsfet->caeConsulta = null;

        $resuelto = $this->handler->resolver($this->solicitud, LetraComprobante::B, 15);

        $this->assertNotEquals(15, $resuelto->numero());
        $this->assertEquals(1, $resuelto->numero());
        $this->assertEquals(1, $this->wsfet->solicitudes);
        $this->assertEquals('12345678901234', $resuelto->cae()?->codigo());
        $this->assertSame($resuelto, $this->repositorio->ultimoGuardado);
    }

    public function test_el_numero_adoptado_no_se_reutiliza_en_reemision(): void
    {
        $this->wsfet->caeConsulta = null;

        $this->handler->resolver($this->solicitud, LetraComprobante::B, 15);

        $this->assertEquals([15], $this->wsfet->numerosConsultados);
        $this->assertEquals([1], $this->repositorio->numerosGuardados);
    }
}
