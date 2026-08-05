<?php

namespace Tests\Feature\FacturacionArca;

use App\Facturacion\Infrastructure\Arca\Certificado\CertificadoMaterial;
use App\Facturacion\Infrastructure\Arca\Certificado\CertificadoService;
use App\Facturacion\Infrastructure\Arca\Certificado\PfxParser;
use App\Facturacion\Infrastructure\Arca\Cifrado\CertificadoEncryptor;
use App\Facturacion\Infrastructure\Arca\Exceptions\CertificadoInvalidoException;
use App\Facturacion\Infrastructure\Arca\Wsaa\FirmaCms;
use App\Models\CertificadoFiscal;
use Tests\Support\FacturacionArca\GeneraPfx;
use Tests\TestCaseMultiTenant;

class CertificadoServiceTest extends TestCaseMultiTenant
{
    private function servicio(): CertificadoService
    {
        return new CertificadoService(new CertificadoEncryptor, new PfxParser);
    }

    public function test_almacenar_y_recuperar_material(): void
    {
        $pfx = GeneraPfx::valido();

        $modelo = $this->servicio()->almacenar(1, 'produccion', $pfx['pfx'], $pfx['password']);

        $this->assertInstanceOf(CertificadoFiscal::class, $modelo);
        $this->assertNotSame($pfx['pfx'], $modelo->archivo_pfx);
        $this->assertNotSame($pfx['password'], $modelo->password_pfx);
        $this->assertNotNull($modelo->numero_serie);
        $this->assertStringContainsString(GeneraPfx::CUIT_VALIDO, (string) $modelo->distinguished_name);
        $this->assertGreaterThan(now()->subDay(), $modelo->vigencia_hasta);

        $material = $this->servicio()->materialDelModelo($modelo);

        $datos = $this->servicio()->vigenciaDelMaterial($material);
        $this->assertTrue($datos->vigente());

        $cms = (new FirmaCms)->firmar($material, 'mensaje-de-prueba');
        $this->assertNotSame('', $cms);
    }

    public function test_almacenar_pfx_vencido_lanza(): void
    {
        $pfx = GeneraPfx::vencido();

        $this->expectException(CertificadoInvalidoException::class);

        $this->servicio()->almacenar(1, 'produccion', $pfx['pfx'], $pfx['password']);
    }

    public function test_material_para_devuelve_el_mas_reciente(): void
    {
        $pfx = GeneraPfx::valido();
        $servicio = $this->servicio();

        $servicio->almacenar(1, 'produccion', $pfx['pfx'], $pfx['password']);
        $segundo = $servicio->almacenar(1, 'produccion', $pfx['pfx'], $pfx['password']);

        $material = $servicio->materialPara(1, 'produccion');

        $this->assertInstanceOf(CertificadoMaterial::class, $material);
        $this->assertSame($segundo->id, CertificadoFiscal::where('comercio_id', 1)->latest('id')->first()?->id);
    }

    public function test_material_para_sin_certificado_lanza(): void
    {
        $this->expectException(CertificadoInvalidoException::class);

        $this->servicio()->materialPara(1, 'produccion');
    }
}
