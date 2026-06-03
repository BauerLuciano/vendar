<?php

namespace Tests\Feature;

use App\Models\Venta;
use Tests\TestCaseMultiTenant;

class Modulo2_VentasTest extends TestCaseMultiTenant
{
    // P2.1.1
    public function test_admin_a_puede_crear_venta_contado(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/ventas', [
            'turno_caja_id' => 2,
            'items' => [
                ['id' => 1, 'cantidad' => 2, 'precio_venta' => 800, 'nombre' => 'Coca Cola 500ml'],
            ],
            'total' => 1600,
            'metodo_pago' => 'Efectivo',
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $response->assertSessionMissing('error');

        $this->assertDatabaseHas('ventas', [
            'turno_caja_id' => 2,
            'total' => 1600,
            'metodo_pago' => 'Efectivo',
            'estado' => 'Completada',
        ]);
    }

    // P2.1.2
    public function test_admin_b_puede_crear_venta_contado(): void
    {
        $this->actingAsAdminB();

        $response = $this->post('/ventas', [
            'turno_caja_id' => 4,
            'items' => [
                ['id' => 11, 'cantidad' => 3, 'precio_venta' => 750, 'nombre' => 'Pepsi 500ml'],
            ],
            'total' => 2250,
            'metodo_pago' => 'Efectivo',
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $response->assertSessionMissing('error');

        $this->assertDatabaseHas('ventas', [
            'turno_caja_id' => 4,
            'total' => 2250,
            'metodo_pago' => 'Efectivo',
            'estado' => 'Completada',
        ]);
    }

    // P2.1.3
    public function test_a_solo_ve_sus_ventas(): void
    {
        $this->actingAsAdminA();

        $ventasA = Venta::whereHas('turno.caja.sucursal', fn ($q) => $q->whereIn('sucursales.id', [1, 2]))
            ->pluck('id');

        $this->assertContains(1, $ventasA);
        $this->assertContains(2, $ventasA);
        $this->assertNotContains(3, $ventasA);
    }

    // P2.2.1
    public function test_admin_a_puede_crear_venta_fiado(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/ventas', [
            'turno_caja_id' => 2,
            'consumidor_id' => $this->consumidorA->id,
            'items' => [
                ['id' => 5, 'cantidad' => 1, 'precio_venta' => 500, 'nombre' => 'Arroz 1kg'],
            ],
            'total' => 500,
            'metodo_pago' => 'Cuenta Corriente',
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $response->assertSessionMissing('error');

        $this->assertDatabaseHas('ventas', [
            'consumidor_id' => $this->consumidorA->id,
            'metodo_pago' => 'Cuenta Corriente',
            'estado' => 'Completada',
        ]);
    }

    // P2.2.2
    public function test_admin_a_no_puede_crear_venta_fiado_con_consumidor_de_b(): void
    {
        $this->actingAsAdminA();

        $this->post('/ventas', [
            'turno_caja_id' => 2,
            'consumidor_id' => $this->consumidorB->id,
            'items' => [
                ['id' => 1, 'cantidad' => 1, 'precio_venta' => 800, 'nombre' => 'Coca Cola 500ml'],
            ],
            'total' => 800,
            'metodo_pago' => 'Cuenta Corriente',
        ])->assertSessionHasErrors();
    }

    // P2.3.1
    public function test_admin_a_puede_cancelar_su_venta(): void
    {
        $this->actingAsAdminA();

        $response = $this->patch('/ventas/1/cancelar', ['motivo' => 'Test cancelación']);
        $response->assertRedirect();

        $this->assertDatabaseHas('ventas', [
            'id' => 1,
            'estado' => 'Cancelada',
            'motivo_anulacion' => 'Test cancelación',
        ]);
    }

    // P2.3.2
    public function test_admin_a_no_puede_cancelar_venta_de_b(): void
    {
        $this->actingAsAdminA();

        $this->patch('/ventas/3/cancelar', ['motivo' => 'Test hack'])->assertForbidden();
    }
}
