<?php

namespace Tests\Unit\FacturacionDomain;

use App\Facturacion\Domain\Services\NumeracionService;
use App\Facturacion\Domain\ValueObjects\TipoComprobante;
use PHPUnit\Framework\TestCase;
use Tests\Support\FacturacionDomain\FakeComprobanteFiscalRepository;

class NumeracionServiceTest extends TestCase
{
    private NumeracionService $service;

    private FakeComprobanteFiscalRepository $repositorio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repositorio = new FakeComprobanteFiscalRepository;
        $this->service = new NumeracionService($this->repositorio);
    }

    public function test_delega_la_numeracion_al_repositorio(): void
    {
        $numero = $this->service->siguiente(7, 4, TipoComprobante::FACTURA);

        $this->assertEquals(1, $numero);
        $this->assertEquals([7, 4, 'factura'], $this->repositorio->ultimaLlamadaNumeracion);
    }

    public function test_es_secuencial_sin_retrocesos(): void
    {
        $a = $this->service->siguiente(7, 4, TipoComprobante::FACTURA);
        $b = $this->service->siguiente(7, 4, TipoComprobante::FACTURA);
        $c = $this->service->siguiente(7, 4, TipoComprobante::FACTURA);

        $this->assertEquals([1, 2, 3], [$a, $b, $c]);
    }

    public function test_nota_credito_usa_su_propia_secuencia(): void
    {
        $this->service->siguiente(7, 4, TipoComprobante::NOTA_CREDITO);

        $this->assertEquals('nota_credito', $this->repositorio->ultimaLlamadaNumeracion[2]);
    }
}
