<?php

namespace Tests\Feature\FacturacionArca;

use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Infrastructure\Arca\Cifrado\CertificadoEncryptor;
use App\Facturacion\Infrastructure\Arca\Cifrado\CredencialPlataformaRepository;
use App\Facturacion\Infrastructure\Arca\Cifrado\CredencialPlataformaService;
use App\Models\Configuracion;
use Tests\Support\FacturacionArca\GeneraPfx;
use Tests\TestCaseMultiTenant;

class CredencialPlataformaServiceTest extends TestCaseMultiTenant
{
    private function servicio(): CredencialPlataformaService
    {
        return new CredencialPlataformaService(
            new CredencialPlataformaRepository(new CertificadoEncryptor)
        );
    }

    public function test_guardar_y_leer_roundtrip(): void
    {
        $servicio = $this->servicio();

        $this->assertFalse($servicio->existe());

        $servicio->guardar(new Cuit(GeneraPfx::CUIT_VALIDO), 'TOKEN_PLATAFORMA', 'SIGN_PLATAFORMA');

        $this->assertTrue($servicio->existe());

        $credencial = $servicio->leer();

        $this->assertNotNull($credencial);
        $this->assertSame(GeneraPfx::CUIT_VALIDO, $credencial->cuit->valor());
        $this->assertSame('TOKEN_PLATAFORMA', $credencial->token);
        $this->assertSame('SIGN_PLATAFORMA', $credencial->sign);
        $this->assertSame([
            'token' => 'TOKEN_PLATAFORMA',
            'sign' => 'SIGN_PLATAFORMA',
            'cuitRepresentado' => GeneraPfx::CUIT_VALIDO,
        ], $credencial->authRequest());
    }

    public function test_la_credencial_se_guarda_encriptada(): void
    {
        $this->servicio()->guardar(new Cuit(GeneraPfx::CUIT_VALIDO), 'TOKEN_PLATAFORMA', 'SIGN_PLATAFORMA');

        $config = Configuracion::where('clave', CredencialPlataformaRepository::CLAVE)->first();

        $this->assertNotNull($config);
        $this->assertSame('encriptado', $config->tipo);
        $this->assertSame('arca', $config->grupo);
        $this->assertStringNotContainsString('TOKEN_PLATAFORMA', (string) $config->valor);
    }

    public function test_leer_sin_configurar_devuelve_null(): void
    {
        $this->assertNull($this->servicio()->leer());
    }

    public function test_guardar_reemplaza_la_anterior(): void
    {
        $servicio = $this->servicio();

        $servicio->guardar(new Cuit(GeneraPfx::CUIT_VALIDO), 'TOKEN_1', 'SIGN_1');
        $servicio->guardar(new Cuit(GeneraPfx::CUIT_VALIDO), 'TOKEN_2', 'SIGN_2');

        $this->assertSame('TOKEN_2', $servicio->leer()?->token);
        $this->assertSame(1, Configuracion::where('clave', CredencialPlataformaRepository::CLAVE)->count());
    }
}
