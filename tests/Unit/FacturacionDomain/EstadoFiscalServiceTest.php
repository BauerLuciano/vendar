<?php

namespace Tests\Unit\FacturacionDomain;

use App\Facturacion\Domain\Exceptions\FacturacionDomainException;
use App\Facturacion\Domain\Services\EstadoFiscalService;
use App\Facturacion\Domain\ValueObjects\EstadoModuloFiscal;
use PHPUnit\Framework\TestCase;

class EstadoFiscalServiceTest extends TestCase
{
    private EstadoFiscalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EstadoFiscalService;
    }

    public function test_avanza_en_la_secuencia_normal(): void
    {
        $this->assertEquals(EstadoModuloFiscal::DATOS_CARGADOS, $this->service->avanzar(EstadoModuloFiscal::SIN_DATOS));
        $this->assertEquals(EstadoModuloFiscal::DATOS_VALIDADOS, $this->service->avanzar(EstadoModuloFiscal::DATOS_CARGADOS));
        $this->assertEquals(EstadoModuloFiscal::CERT_CARGADO, $this->service->avanzar(EstadoModuloFiscal::DATOS_VALIDADOS));
        $this->assertEquals(EstadoModuloFiscal::PV_HABILITADO, $this->service->avanzar(EstadoModuloFiscal::CERT_CARGADO));
        $this->assertEquals(EstadoModuloFiscal::LISTO_PARA_FACTURAR, $this->service->avanzar(EstadoModuloFiscal::PV_HABILITADO));
    }

    public function test_listo_para_facturar_es_terminal_de_la_secuencia(): void
    {
        $this->expectException(FacturacionDomainException::class);
        $this->service->avanzar(EstadoModuloFiscal::LISTO_PARA_FACTURAR);
    }

    public function test_no_se_puede_avanzar_desde_un_estado_de_falla(): void
    {
        $this->expectException(FacturacionDomainException::class);
        $this->service->avanzar(EstadoModuloFiscal::CUIT_INACTIVO);
    }

    public function test_fallar_lleva_a_estado_recuperable(): void
    {
        $estado = $this->service->fallar(EstadoModuloFiscal::PV_HABILITADO, EstadoModuloFiscal::ERROR_INTEGRACION);

        $this->assertEquals(EstadoModuloFiscal::ERROR_INTEGRACION, $estado);
        $this->assertTrue($this->service->esEstadoDeFalla($estado));
    }

    public function test_fallar_rechaza_un_estado_que_no_es_falla(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->fallar(EstadoModuloFiscal::PV_HABILITADO, EstadoModuloFiscal::PV_HABILITADO);
    }

    public function test_marcar_no_soportado_es_terminal(): void
    {
        $estado = $this->service->marcarNoSoportado();

        $this->assertEquals(EstadoModuloFiscal::NO_SOPORTADO, $estado);
        $this->assertTrue($this->service->esTerminal($estado));
        $this->assertFalse($estado->esListoParaFacturar());
    }

    public function test_reanudar_desde_falla_devuelve_el_estado(): void
    {
        $this->assertEquals(
            EstadoModuloFiscal::CERTIFICADO_VENCIDO,
            $this->service->reanudar(EstadoModuloFiscal::CERTIFICADO_VENCIDO)
        );
    }

    public function test_reanudar_desde_no_soportado_lanza_excepcion(): void
    {
        $this->expectException(FacturacionDomainException::class);
        $this->service->reanudar(EstadoModuloFiscal::NO_SOPORTADO);
    }
}
