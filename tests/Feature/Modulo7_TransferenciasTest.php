<?php

namespace Tests\Feature;

use App\Models\TransferenciaSugerida;
use Tests\TestCaseMultiTenant;

class Modulo7_TransferenciasTest extends TestCaseMultiTenant
{
    public function test_admin_a_puede_ver_transferencias(): void
    {
        $this->actingAsAdminA();

        $response = $this->get('/transferencias-sugeridas');
        $response->assertOk();
    }

    public function test_admin_a_puede_despachar_transferencia_pendiente(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/transferencias-sugeridas/1/despachar');
        $response->assertRedirect();

        $this->assertDatabaseHas('transferencia_sugeridas', [
            'id' => 1,
            'estado' => 'en_transito',
        ]);
    }

    public function test_admin_a_no_puede_despachar_transferencia_de_b(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/transferencias-sugeridas/2/despachar');
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('transferencia_sugeridas', [
            'id' => 2,
            'estado' => 'pendiente',
        ]);
    }

    public function test_admin_a_no_puede_cancelar_transferencia_ya_despachada(): void
    {
        $this->actingAsAdminA();

        // ID 1 fue despachada en el test anterior (estado=en_transito)
        $this->post('/transferencias-sugeridas/1/cancelar');
        $this->assertSessionHas('error');

        $this->assertDatabaseHas('transferencia_sugeridas', [
            'id' => 1,
            'estado' => 'en_transito',
        ]);
    }
}
