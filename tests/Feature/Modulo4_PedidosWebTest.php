<?php

namespace Tests\Feature;

use App\Models\PedidoWeb;
use App\Models\Sucursal;
use Tests\TestCaseMultiTenant;

class Modulo4_PedidosWebTest extends TestCaseMultiTenant
{
    // P5.1.1
    public function test_admin_a_puede_crear_pedido_web(): void
    {
        $this->actingAsAdminA();

        $response = $this->postJson('/api/pedidos-web', [
            'comercio_id' => 1,
            'sucursal_id' => 1,
            'items' => [
                ['id' => 1, 'cantidad' => 2, 'precio' => 800, 'nombre' => 'Coca Cola 500ml'],
            ],
            'tipo_entrega' => 'local',
            'metodo_pago' => 'efectivo',
            'total_productos' => 1600,
            'total_final' => 1600,
        ]);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('pedidos_web', [
            'comercio_id' => 1,
            'sucursal_id' => 1,
            'total' => '1600.00',
            'metodo_pago' => 'efectivo',
            'estado_pedido' => 'nuevo',
        ]);
    }

    // P5.1.2
    public function test_a_solo_ve_sus_pedidos(): void
    {
        $this->actingAsAdminA();

        $sucursalIdsA = Sucursal::where('comercio_id', 1)->pluck('id');
        $ids = PedidoWeb::whereIn('sucursal_id', $sucursalIdsA)->pluck('id');

        $this->assertContains(1, $ids);
        $this->assertNotContains(2, $ids);
    }

    // P5.2.1
    public function test_admin_a_puede_actualizar_estado_pedido(): void
    {
        $this->actingAsAdminA();

        $response = $this->patch('/pedidos/1/estado', ['estado_pedido' => 'preparando']);
        $response->assertRedirect();

        $this->assertDatabaseHas('pedidos_web', [
            'id' => 1,
            'estado_pedido' => 'preparando',
        ]);
    }

    // P5.2.2
    public function test_admin_a_no_puede_actualizar_estado_pedido_de_b(): void
    {
        $this->actingAsAdminA();

        $this->patch('/pedidos/2/estado', ['estado_pedido' => 'preparando'])->assertNotFound();
    }

    // P5.2.3
    public function test_admin_a_no_puede_actualizar_pago_pedido_de_b(): void
    {
        $this->actingAsAdminA();

        $this->patch('/pedidos/2/pago', ['estado_pago' => 'pagado'])->assertNotFound();
    }
}
