<?php

namespace Tests\Feature\Facturacion;

use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Domain\ValueObjects\EstadoModuloFiscal;
use App\Facturacion\Infrastructure\Persistence\EloquentConfiguracionFiscalRepository;
use App\Models\ConfiguracionFiscalComercio;
use Tests\TestCaseMultiTenant;

class EloquentConfiguracionFiscalRepositoryTest extends TestCaseMultiTenant
{
    public function test_mapea_la_configuracion_del_comercio(): void
    {
        ConfiguracionFiscalComercio::create([
            'comercio_id' => 1,
            'cuit' => '20123456786',
            'razon_social' => 'Comercio RI',
            'condicion_fiscal' => 'responsable_inscripto',
            'domicilio_fiscal' => 'Calle 123',
            'entorno' => 'homologacion',
            'punto_venta_activo' => 4,
            'estado_modulo' => 'listo_para_facturar',
        ]);

        $config = (new EloquentConfiguracionFiscalRepository)->buscarPorComercio(1);

        $this->assertNotNull($config);
        $this->assertSame(1, $config->comercioId());
        $this->assertTrue($config->cuit()->esIgual(new Cuit('20123456786')));
        $this->assertSame('Comercio RI', $config->razonSocial());
        $this->assertEquals(CondicionFiscal::RESPONSABLE_INSCRIPTO, $config->condicionFiscal());
        $this->assertSame('Calle 123', $config->domicilioFiscal());
        $this->assertSame('homologacion', $config->entorno());
        $this->assertSame(4, $config->puntoVentaActivo());
        $this->assertEquals(EstadoModuloFiscal::LISTO_PARA_FACTURAR, $config->estadoModulo());
        $this->assertTrue($config->estaListoParaFacturar());
    }

    public function test_estado_sin_datos_se_mapea_por_defecto(): void
    {
        ConfiguracionFiscalComercio::create(['comercio_id' => 1]);

        $config = (new EloquentConfiguracionFiscalRepository)->buscarPorComercio(1);

        $this->assertNotNull($config);
        $this->assertEquals(EstadoModuloFiscal::SIN_DATOS, $config->estadoModulo());
        $this->assertNull($config->cuit());
        $this->assertFalse($config->estaListoParaFacturar());
    }

    public function test_devuelve_null_si_el_comercio_no_tiene_configuracion(): void
    {
        $this->assertNull((new EloquentConfiguracionFiscalRepository)->buscarPorComercio(999));
    }

    public function test_no_filtra_la_configuracion_de_otro_comercio(): void
    {
        ConfiguracionFiscalComercio::create(['comercio_id' => 1]);

        $this->assertNull((new EloquentConfiguracionFiscalRepository)->buscarPorComercio(2));
    }
}
