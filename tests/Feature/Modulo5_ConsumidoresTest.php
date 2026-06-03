<?php

namespace Tests\Feature;

use App\Models\Consumidor;
use Tests\TestCaseMultiTenant;

class Modulo5_ConsumidoresTest extends TestCaseMultiTenant
{
    // P4.1.1
    public function test_admin_a_puede_crear_consumidor(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/clientes', [
            'nombre' => 'Roberto',
            'apellido' => 'González',
            'documento' => '12345678',
            'limite_cuenta_corriente' => 5000,
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('consumidores', [
            'nombre' => 'Roberto',
            'apellido' => 'González',
            'documento' => '12345678',
            'comercio_id' => 1,
        ]);
    }

    // P4.1.2
    public function test_admin_b_puede_crear_consumidor(): void
    {
        $this->actingAsAdminB();

        $response = $this->post('/clientes', [
            'nombre' => 'Lucía',
            'apellido' => 'Mendoza',
            'documento' => '87654321',
            'limite_cuenta_corriente' => 3000,
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('consumidores', [
            'nombre' => 'Lucía',
            'apellido' => 'Mendoza',
            'documento' => '87654321',
            'comercio_id' => 2,
        ]);
    }

    // P4.2.1
    public function test_a_solo_ve_sus_consumidores(): void
    {
        $this->actingAsAdminA();

        $ids = Consumidor::where('comercio_id', 1)->pluck('id');

        $this->assertContains($this->consumidorA->id, $ids);
        $this->assertNotContains($this->consumidorB->id, $ids);
    }

    // P4.2.2
    public function test_admin_a_puede_ver_cuenta_de_su_consumidor(): void
    {
        $this->actingAsAdminA();

        $response = $this->get('/consumidores/' . $this->consumidorA->id . '/cuenta');
        $response->assertOk();
        $response->assertJsonIsArray();
    }

    // P4.2.3
    public function test_admin_a_no_puede_ver_cuenta_de_consumidor_de_b(): void
    {
        $this->actingAsAdminA();

        $this->get('/consumidores/' . $this->consumidorB->id . '/cuenta')->assertForbidden();
    }

    // P4.3.1
    public function test_admin_a_puede_cobrar_deuda(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/consumidores/' . $this->consumidorA->id . '/cobrar', [
            'pagos' => [
                ['monto' => 1000, 'metodo_pago' => 'Efectivo'],
            ],
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
    }

    // P4.3.2
    public function test_admin_a_no_puede_cobrar_deuda_de_consumidor_de_b(): void
    {
        $this->actingAsAdminA();

        $this->post('/consumidores/' . $this->consumidorB->id . '/cobrar', [
            'pagos' => [
                ['monto' => 500, 'metodo_pago' => 'Efectivo'],
            ],
        ])->assertForbidden();
    }
}
