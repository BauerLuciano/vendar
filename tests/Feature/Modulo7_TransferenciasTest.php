<?php

namespace Tests\Feature;

use App\Models\TransferenciaSugerida;
use Tests\TestCaseMultiTenant;

class Modulo7_TransferenciasTest extends TestCaseMultiTenant
{
    // P6.1.1
    public function test_admin_a_puede_ver_transferencias(): void
    {
        $this->actingAsAdminA();

        $response = $this->get('/transferencias-sugeridas');
        $response->assertOk();
    }

    // P6.1.2
    public function test_admin_a_puede_aprobar_transferencia(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/transferencias-sugeridas/1/aprobar');
        $response->assertRedirect();

        $this->assertDatabaseHas('transferencia_sugeridas', [
            'id' => 1,
            'estado' => 'aprobada',
        ]);
    }

    // P6.1.3
    public function test_admin_a_no_puede_aprobar_transferencia_de_b(): void
    {
        $this->actingAsAdminA();

        $transferenciaB = TransferenciaSugerida::findOrFail(2);
        $this->assertEquals(4, $transferenciaB->origen_id);

        $response = $this->post('/transferencias-sugeridas/2/aprobar');
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('transferencia_sugeridas', [
            'id' => 2,
            'estado' => 'pendiente',
        ]);
    }
}
