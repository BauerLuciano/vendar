<?php

namespace Tests\Feature;

use App\Models\PedidoWeb;
use Tests\TestCaseMultiTenant;
use Illuminate\Support\Facades\DB;

class WebhookSeguridadTest extends TestCaseMultiTenant
{
    public function test_mercadopago_webhook_requiere_firma(): void
    {
        $response = $this->postJson('/api/mercadopago/notificacion', [
            'data' => ['id' => 'fake_payment_123'],
            'comercio_id' => 1,
        ]);

        $this->assertNotEquals(200, $response->getStatusCode(), 'El webhook no debería aceptar peticiones sin firma válida');
    }

    public function test_viumi_webhook_rechaza_pedido_no_encontrado(): void
    {
        $response = $this->postJson('/api/webhook/viumi', [
            'data' => [
                'order' => ['uuid' => 'nonexistent-uuid-'.uniqid()],
            ],
        ]);

        $response->assertStatus(404);
    }

    public function test_viumi_webhook_rechaza_payload_invalido(): void
    {
        $pedido = PedidoWeb::where('estado_pago', 'pendiente')->first();
        if (!$pedido) {
            $this->markTestSkipped('No hay pedidos pendientes en QA seeder');
        }

        $response = $this->postJson('/api/webhook/viumi', [
            'data' => [
                'order' => ['uuid' => $pedido->pasarela_payment_id],
            ],
        ]);

        $response->assertStatus(400);
    }

    public function test_stock_liberacion_registra_movimiento(): void
    {
        $userId = DB::table('users')->value('id');
        if (!$userId) {
            $this->markTestSkipped('No hay usuarios en QA seeder');
        }

        $pedido = PedidoWeb::with('items')->where('estado_pago', 'pagado')
            ->where('estado_pedido', '!=', 'cancelado')->first();

        if (!$pedido) {
            $this->markTestSkipped('No hay pedidos pagados para testear');
        }

        $movimientosAntes = DB::table('movimientos_stock')
            ->where('sucursal_id', $pedido->sucursal_id)
            ->count();

        foreach ($pedido->items as $item) {
            DB::table('producto_sucursal')
                ->where('sucursal_id', $pedido->sucursal_id)
                ->where('producto_id', $item->producto_id)
                ->update(['cantidad_reservada' => DB::raw('cantidad_reservada + ' . $item->cantidad)]);
        }

        DB::table('movimientos_stock')->insert([
            'producto_id'       => $pedido->items->first()->producto_id,
            'sucursal_id'       => $pedido->sucursal_id,
            'user_id'           => $userId,
            'tipo_movimiento'   => 'Liberación Reserva',
            'cantidad_anterior' => 10,
            'cantidad_movimiento' => 0,
            'cantidad_actual'   => 10,
            'motivo'            => "Test - Pedido web #{$pedido->id}",
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $movimientosDespues = DB::table('movimientos_stock')
            ->where('sucursal_id', $pedido->sucursal_id)
            ->count();

        $this->assertGreaterThan($movimientosAntes, $movimientosDespues);
    }

    public function test_entrega_pedido_web_crea_movimiento_stock(): void
    {
        $this->actingAsAdminA();

        $sucursalId = $this->adminA->branch_id;
        $productoId = 1;

        DB::table('producto_sucursal')->updateOrInsert(
            ['producto_id' => $productoId, 'sucursal_id' => $sucursalId],
            ['cantidad_fisica' => 10, 'cantidad_reservada' => 3]
        );

        $pedido = PedidoWeb::forceCreate([
            'comercio_id'         => 1,
            'sucursal_id'         => $sucursalId,
            'consumidor_id'       => DB::table('consumidores')->value('id'),
            'cliente_nombre'      => 'Test Cliente',
            'estado_pago'         => 'pagado',
            'estado_pedido'       => 'preparando',
            'tipo_entrega'        => 'local',
            'metodo_pago'         => 'efectivo',
            'subtotal'            => 2400,
            'costo_envio'         => 0,
            'total'               => 2400,
            'pasarela_payment_id' => 'test_uuid_'.uniqid(),
        ]);

        DB::table('pedido_web_items')->insert([
            'pedido_web_id' => $pedido->id,
            'producto_id'   => $productoId,
            'cantidad'       => 3,
            'precio_unitario'=> 800,
            'subtotal'       => 2400,
        ]);

        $movimientosAntes = DB::table('movimientos_stock')
            ->where('sucursal_id', $sucursalId)
            ->count();

        $response = $this->patch(route('pedidos.estado', $pedido->id), [
            'estado_pedido' => 'entregado',
        ]);

        $movimientosDespues = DB::table('movimientos_stock')
            ->where('sucursal_id', $sucursalId)
            ->where('tipo_movimiento', 'Pedido Web Entregado')
            ->count();

        $this->assertGreaterThan($movimientosAntes, $movimientosDespues);

        $ps = DB::table('producto_sucursal')
            ->where('producto_id', $productoId)
            ->where('sucursal_id', $sucursalId)
            ->first();
        $this->assertEquals(7, (int) $ps->cantidad_fisica);
        $this->assertEquals(0, (int) $ps->cantidad_reservada);
    }
}
