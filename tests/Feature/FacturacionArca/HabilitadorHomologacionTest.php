<?php

namespace Tests\Feature\FacturacionArca;

use App\Facturacion\Infrastructure\Arca\Entorno\EntornoArca;
use App\Facturacion\Infrastructure\Arca\Entorno\HabilitadorHomologacion;
use App\Facturacion\Infrastructure\Arca\Exceptions\EntornoRestringidoException;
use App\Models\User;
use Tests\TestCaseMultiTenant;

class HabilitadorHomologacionTest extends TestCaseMultiTenant
{
    public function test_produccion_se_permite_a_cualquier_usuario(): void
    {
        $habilitador = new HabilitadorHomologacion;

        $habilitador->verificar(EntornoArca::PRODUCCION, $this->userA);

        $this->assertTrue(true);
    }

    public function test_homologacion_con_usuario_sin_rol_lanza(): void
    {
        $this->expectException(EntornoRestringidoException::class);

        (new HabilitadorHomologacion)->verificar(EntornoArca::HOMOLOGACION, $this->userA);
    }

    public function test_homologacion_sin_usuario_lanza(): void
    {
        $this->expectException(EntornoRestringidoException::class);

        (new HabilitadorHomologacion)->verificar('homologacion');
    }

    public function test_homologacion_con_superadmin_se_permite(): void
    {
        (new HabilitadorHomologacion)->verificar(EntornoArca::HOMOLOGACION, $this->adminA);

        $this->assertTrue(true);
    }

    public function test_homologacion_con_admin_global_se_permite(): void
    {
        $adminGlobal = User::factory()->create();
        $adminGlobal->assignRole(HabilitadorHomologacion::ROL_ADMIN_GLOBAL);

        (new HabilitadorHomologacion)->verificar('homologacion', $adminGlobal);

        $this->assertTrue(true);
    }
}
