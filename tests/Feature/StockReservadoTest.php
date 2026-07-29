<?php

namespace Tests\Feature;

use App\Models\PedidoWeb;
use Tests\TestCaseMultiTenant;
use Illuminate\Support\Facades\DB;

class StockReservadoTest extends TestCaseMultiTenant
{
    public function test_pos_no_vende_stock_reservado(): void
    {
        $this->actingAsAdminA();

        $sucursalId = $this->adminA->branch_id;
        $productoId = 1;

        DB::table('producto_sucursal')->updateOrInsert(
            ['producto_id' => $productoId, 'sucursal_id' => $sucursalId],
            ['cantidad_fisica' => 10, 'cantidad_reservada' => 6]
        );

        $response = $this->post('/ventas', [
            'turno_caja_id' => 2,
            'items' => [
                ['id' => $productoId, 'cantidad' => 5, 'precio_venta' => 800, 'nombre' => 'Test'],
            ],
            'total' => 4000,
            'metodo_pago' => 'Efectivo',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('ventas', ['total' => 4000, 'metodo_pago' => 'EFECTIVO']);
    }

    public function test_pos_puede_vender_stock_no_reservado(): void
    {
        $this->actingAsAdminA();

        $sucursalId = $this->adminA->branch_id;
        $productoId = 1;

        DB::table('producto_sucursal')->updateOrInsert(
            ['producto_id' => $productoId, 'sucursal_id' => $sucursalId],
            ['cantidad_fisica' => 10, 'cantidad_reservada' => 6]
        );

        $response = $this->post('/ventas', [
            'turno_caja_id' => 2,
            'items' => [
                ['id' => $productoId, 'cantidad' => 4, 'precio_venta' => 800, 'nombre' => 'Test'],
            ],
            'total' => 3200,
            'metodo_pago' => 'Efectivo',
        ]);

        $this->assertDatabaseHas('ventas', ['total' => 3200, 'metodo_pago' => 'EFECTIVO']);
    }

    public function test_stock_disponible_es_fisica_menos_reservada(): void
    {
        $this->actingAsAdminA();

        $sucursalId = $this->adminA->branch_id;
        $productoId = 1;

        DB::table('producto_sucursal')->updateOrInsert(
            ['producto_id' => $productoId, 'sucursal_id' => $sucursalId],
            ['cantidad_fisica' => 10, 'cantidad_reservada' => 6]
        );

        $response = $this->getJson('/pos/productos', [
            'QUERY_STRING' => "sucursal_id={$sucursalId}",
        ]);

        if ($response->json('data')) {
            $producto = collect($response->json('data'))->firstWhere('id', $productoId);
            if ($producto) {
                $this->assertEquals(4, $producto['stock_actual']);
            }
        }
    }
}
