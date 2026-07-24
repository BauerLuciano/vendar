<?php

namespace Tests\Feature\Stock;

use App\Models\Producto;
use App\Models\PedidoWeb;
use App\Models\PedidoWebItem;
use Illuminate\Support\Facades\DB;
use Tests\TestCaseMultiTenant;

/**
 * Test 6:  Pedido Web — reserva aumenta cantidad_reservada, movimiento.
 * Test 6b: Cancelación pedido web — reserva liberada, movimiento.
 * Test 7:  Entrega pedido web — baja fisica y reservada, movimiento.
 * Test 8:  Expiración automática — libera reserva, movimiento.
 * Test 9:  Rechazo por webhook — libera reserva, movimiento.
 */
class PedidoWebStockTest extends TestCaseMultiTenant
{
    private int $productoId;
    private int $sucursalId;
    private int $comercioId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productoId = 60;
        $this->sucursalId = 1;
        $this->comercioId = 1;

        DB::table('productos')->updateOrInsert(
            ['id' => $this->productoId],
            [
                'nombre' => 'Test Pedido Web',
                'codigo_barras' => 'TEST_PEDIDO_' . $this->productoId,
                'precio_costo' => 300,
                'precio_venta' => 500,
                'stock_minimo' => 5,
                'unidad_medida' => 'Unidad',
                'estado' => true,
                'categoria_id' => 1,
                'marca_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('producto_sucursal')->updateOrInsert(
            ['producto_id' => $this->productoId, 'sucursal_id' => $this->sucursalId],
            ['cantidad_fisica' => 100, 'cantidad_reservada' => 0, 'created_at' => now(), 'updated_at' => now()]
        );

        DB::table('lotes')->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)->delete();

        DB::table('lotes')->insert([
            'producto_id' => $this->productoId, 'sucursal_id' => $this->sucursalId,
            'fecha_vencimiento' => now()->addMonths(6), 'stock_inicial' => 100, 'stock_actual' => 100,
            'estado_liquidacion' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->delete();
    }

    private function getStock(): object
    {
        return DB::table('producto_sucursal')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->first();
    }

    private function sumLotes(): float
    {
        return (float) DB::table('lotes')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->sum('stock_actual');
    }

    private function crearPedidoWeb(int $cantidad = 10, string $estadoPago = 'pendiente'): PedidoWeb
    {
        $pedido = PedidoWeb::create([
            'comercio_id' => $this->comercioId,
            'sucursal_id' => $this->sucursalId,
            'cliente_nombre' => 'Test Cliente',
            'cliente_telefono' => '111111111',
            'subtotal' => $cantidad * 500,
            'total' => $cantidad * 500,
            'metodo_pago' => 'efectivo',
            'estado_pedido' => 'nuevo',
        ]);

        DB::table('pedidos_web')->where('id', $pedido->id)->update(['estado_pago' => $estadoPago]);

        PedidoWebItem::create([
            'pedido_web_id' => $pedido->id,
            'producto_id' => $this->productoId,
            'cantidad' => $cantidad,
            'precio_unitario' => 500,
            'subtotal' => $cantidad * 500,
        ]);

        return $pedido;
    }

    // ── TEST 6: Reserva de pedido web ──

    public function test_reserva_aumenta_cantidad_reservada(): void
    {
        $pedido = $this->crearPedidoWeb(10);

        $this->actingAsAdminA();

        $this->post('/api/pedidos-web', [
            'comercio_id' => $this->comercioId,
            'sucursal_id' => $this->sucursalId,
            'items' => [['id' => $this->productoId, 'cantidad' => 10]],
            'tipo_entrega' => 'local',
            'metodo_pago' => 'efectivo',
        ]);

        $stock = $this->getStock();
        $this->assertEquals(10.0, (float) $stock->cantidad_reservada);
        $this->assertEquals(100.0, (float) $stock->cantidad_fisica);
    }

    public function test_reserva_no_toca_fisica(): void
    {
        $this->actingAsAdminA();

        $this->post('/api/pedidos-web', [
            'comercio_id' => $this->comercioId,
            'sucursal_id' => $this->sucursalId,
            'items' => [['id' => $this->productoId, 'cantidad' => 5]],
            'tipo_entrega' => 'local',
            'metodo_pago' => 'efectivo',
        ]);

        $this->assertEquals(100.0, (float) $this->getStock()->cantidad_fisica);
    }

    public function test_reserva_registra_movimiento(): void
    {
        $this->actingAsAdminA();

        $this->post('/api/pedidos-web', [
            'comercio_id' => $this->comercioId,
            'sucursal_id' => $this->sucursalId,
            'items' => [['id' => $this->productoId, 'cantidad' => 10]],
            'tipo_entrega' => 'local',
            'metodo_pago' => 'efectivo',
        ]);

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Reserva Pedido Web')
            ->latest()
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals(-10.0, (float) $mov->cantidad_movimiento);
    }

    // ── TEST 6b: Cancelación de pedido web (pre-entrega) ──

    public function test_cancelacion_pedido_libera_reserva(): void
    {
        $pedido = $this->crearPedidoWeb(10);

        $this->actingAsAdminA();
        $this->patch("/pedidos/{$pedido->id}/estado", ['estado_pedido' => 'cancelado']);

        $stock = $this->getStock();
        $this->assertEquals(0.0, (float) $stock->cantidad_reservada);
        $this->assertEquals(100.0, (float) $stock->cantidad_fisica);
    }

    public function test_cancelacion_pedido_registra_movimiento(): void
    {
        $pedido = $this->crearPedidoWeb(10);

        $this->actingAsAdminA();
        $this->patch("/pedidos/{$pedido->id}/estado", ['estado_pedido' => 'cancelado']);

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Liberación Reserva Web')
            ->latest()
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals(0.0, (float) $mov->cantidad_anterior);
        $this->assertEquals(10.0, (float) $mov->cantidad_movimiento);
    }

    public function test_cancelacion_pedido_estado_cancelado(): void
    {
        $pedido = $this->crearPedidoWeb(10);

        $this->actingAsAdminA();
        $this->patch("/pedidos/{$pedido->id}/estado", ['estado_pedido' => 'cancelado']);

        $this->assertDatabaseHas('pedidos_web', [
            'id' => $pedido->id,
            'estado_pedido' => 'cancelado',
        ]);
    }

    // ── TEST 7: Entrega de pedido web ──

    public function test_entrega_baja_fisica_y_reservada(): void
    {
        $pedido = $this->crearPedidoWeb(10);

        DB::table('producto_sucursal')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['cantidad_reservada' => 10]);

        $this->actingAsAdminA();

        $this->patch("/pedidos/{$pedido->id}/estado", ['estado_pedido' => 'entregado']);

        $stock = $this->getStock();
        $this->assertEquals(90.0, (float) $stock->cantidad_fisica);
        $this->assertEquals(0.0, (float) $stock->cantidad_reservada);
    }

    public function test_entrega_registra_movimiento(): void
    {
        $pedido = $this->crearPedidoWeb(10);

        DB::table('producto_sucursal')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['cantidad_reservada' => 10]);

        $this->actingAsAdminA();
        $this->patch("/pedidos/{$pedido->id}/estado", ['estado_pedido' => 'entregado']);

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Entrega Pedido Web')
            ->latest()
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals(-10.0, (float) $mov->cantidad_movimiento);
        $this->assertEquals(90.0, (float) $mov->cantidad_actual);
    }

    public function test_entrega_invariante_lotes(): void
    {
        $pedido = $this->crearPedidoWeb(10);

        DB::table('producto_sucursal')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['cantidad_reservada' => 10]);

        $this->actingAsAdminA();
        $this->patch("/pedidos/{$pedido->id}/estado", ['estado_pedido' => 'entregado']);

        $stock = $this->getStock();
        $this->assertEquals(90.0, (float) $stock->cantidad_fisica);
        $this->assertEquals(0.0, (float) $stock->cantidad_reservada);

        $sumLotes = (float) DB::table('lotes')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->sum('stock_actual');
        $this->assertEquals(90.0, $sumLotes);

        $tracking = DB::table('pedido_web_items_lotes')
            ->where('pedido_web_item_id', $pedido->items()->first()->id)
            ->get();
        $this->assertNotEmpty($tracking);
        $this->assertEquals(10.0, (float) $tracking->sum('cantidad'));
    }

    // ── TEST 8: Expiración automática ──

    public function test_expiracion_libera_reserva(): void
    {
        $pedidoId = DB::table('pedidos_web')->insertGetId([
            'comercio_id' => $this->comercioId,
            'sucursal_id' => $this->sucursalId,
            'cliente_nombre' => 'Exp Test',
            'subtotal' => 5000,
            'total' => 5000,
            'metodo_pago' => 'efectivo',
            'estado_pedido' => 'nuevo',
            'estado_pago' => 'pendiente',
            'created_at' => now()->subMinutes(31),
            'updated_at' => now()->subMinutes(31),
        ]);

        DB::table('pedido_web_items')->insert([
            'pedido_web_id' => $pedidoId,
            'producto_id' => $this->productoId,
            'cantidad' => 10,
            'precio_unitario' => 500,
            'subtotal' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('producto_sucursal')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['cantidad_reservada' => 10]);

        $this->assertDatabaseHas('producto_sucursal', [
            'producto_id' => $this->productoId,
            'sucursal_id' => $this->sucursalId,
            'cantidad_reservada' => 10,
        ]);

        $job = new \App\Jobs\ExpirarPedidosPendientes();
        $job->handle();

        $this->assertDatabaseHas('producto_sucursal', [
            'producto_id' => $this->productoId,
            'sucursal_id' => $this->sucursalId,
            'cantidad_reservada' => 0,
        ]);
    }

    public function test_expiracion_registra_movimiento(): void
    {
        $pedidoId = DB::table('pedidos_web')->insertGetId([
            'comercio_id' => $this->comercioId,
            'sucursal_id' => $this->sucursalId,
            'cliente_nombre' => 'Exp Mov Test',
            'subtotal' => 2500,
            'total' => 2500,
            'metodo_pago' => 'efectivo',
            'estado_pedido' => 'nuevo',
            'estado_pago' => 'pendiente',
            'created_at' => now()->subMinutes(31),
            'updated_at' => now()->subMinutes(31),
        ]);

        DB::table('pedido_web_items')->insert([
            'pedido_web_id' => $pedidoId,
            'producto_id' => $this->productoId,
            'cantidad' => 5,
            'precio_unitario' => 500,
            'subtotal' => 2500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('producto_sucursal')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['cantidad_reservada' => 5]);

        $job = new \App\Jobs\ExpirarPedidosPendientes();
        $job->handle();

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Liberación Reserva Web')
            ->latest()
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals(5.0, (float) $mov->cantidad_anterior);
        $this->assertEquals(5.0, (float) $mov->cantidad_movimiento);
    }

    public function test_expiracion_cancela_pedido(): void
    {
        $pedidoId = DB::table('pedidos_web')->insertGetId([
            'comercio_id' => $this->comercioId,
            'sucursal_id' => $this->sucursalId,
            'cliente_nombre' => 'Exp Estado Test',
            'subtotal' => 1000,
            'total' => 1000,
            'metodo_pago' => 'efectivo',
            'estado_pedido' => 'nuevo',
            'estado_pago' => 'pendiente',
            'created_at' => now()->subMinutes(31),
            'updated_at' => now()->subMinutes(31),
        ]);

        DB::table('pedido_web_items')->insert([
            'pedido_web_id' => $pedidoId,
            'producto_id' => $this->productoId,
            'cantidad' => 2,
            'precio_unitario' => 500,
            'subtotal' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $job = new \App\Jobs\ExpirarPedidosPendientes();
        $job->handle();

        $this->assertDatabaseHas('pedidos_web', [
            'id' => $pedidoId,
            'estado_pedido' => 'cancelado',
        ]);
    }

    // ── TEST 9: Rechazo por webhook (tested via direct controller call) ──

    private function simularRechazoWebhook(int $pedidoId, string $gatewayName): void
    {
        $pedido = PedidoWeb::with('comercio')->find($pedidoId);

        DB::table('producto_sucursal')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['cantidad_reservada' => 10]);

        if ($gatewayName === 'mercadopago') {
            $mpTxId = 'test_mp_' . uniqid();

            $gatewayMock = \Mockery::mock(\App\Services\Payment\Contracts\PaymentGateway::class);
            $gatewayMock->shouldReceive('verifyWebhookSignature')->andReturn(true);
            $gatewayMock->shouldReceive('getPaymentStatus')->andReturn(
                new \App\Services\Payment\Contracts\PaymentStatusResponse(
                    gatewayTransactionId: $mpTxId,
                    status: \App\Enums\PaymentStatus::REJECTED,
                    referenceId: (string) $pedido->id,
                    amount: 5000,
                    raw: [],
                )
            );

            $psMock = \Mockery::mock(\App\Services\Payment\PaymentService::class);
            $psMock->shouldReceive('forCommerce')->andReturnSelf();
            $psMock->shouldReceive('gateway')->andReturn($gatewayMock);

            $paymentMock = \Mockery::mock(\App\Models\Payment::class);
            $prMock = \Mockery::mock(\App\Services\Payment\PaymentRecorder::class);
            $prMock->shouldReceive('recordWebhook')->andReturn($paymentMock);

            $request = \Illuminate\Http\Request::create('/test', 'POST', [
                'comercio_id' => $this->comercioId,
                'data' => ['id' => $mpTxId],
            ]);

            $controller = new \App\Http\Controllers\MercadoPagoNotificacionController($psMock, $prMock);
            $controller->notificacion($request);
        } else {
            $viumiUuid = 'test_viumi_' . uniqid();
            DB::table('pedidos_web')->where('id', $pedido->id)->update(['pasarela_payment_id' => $viumiUuid]);
            $pedido = $pedido->fresh(['comercio']);

            $gatewayMock = \Mockery::mock(\App\Services\Payment\Contracts\PaymentGateway::class);
            $gatewayMock->shouldReceive('parseWebhookPayload')->andReturn(
                new \App\Services\Payment\Contracts\WebhookPayload(
                    gatewayTransactionId: $viumiUuid,
                    status: \App\Enums\PaymentStatus::REJECTED,
                    referenceId: (string) $pedido->id,
                    amount: 5000,
                    raw: [],
                )
            );

            $psMock = \Mockery::mock(\App\Services\Payment\PaymentService::class);
            $psMock->shouldReceive('forCommerce')->andReturnSelf();
            $psMock->shouldReceive('gateway')->andReturn($gatewayMock);

            $paymentMock = \Mockery::mock(\App\Models\Payment::class);
            $prMock = \Mockery::mock(\App\Services\Payment\PaymentRecorder::class);
            $prMock->shouldReceive('recordWebhook')->andReturn($paymentMock);

            $request = \Illuminate\Http\Request::create('/test', 'POST', [
                'data' => ['order' => ['uuid' => $viumiUuid]],
            ]);

            $controller = new \App\Http\Controllers\ViumiWebhookController($psMock, $prMock);
            $controller->__invoke($request);
        }
    }

    public function test_webhook_mp_rechazo_libera_reserva(): void
    {
        $pedido = $this->crearPedidoWeb(10, 'pendiente');

        $this->simularRechazoWebhook($pedido->id, 'mercadopago');

        $stock = $this->getStock();
        $this->assertEquals(0.0, (float) $stock->cantidad_reservada);
    }

    public function test_webhook_mp_rechazo_registra_movimiento(): void
    {
        $pedido = $this->crearPedidoWeb(10, 'pendiente');

        $this->simularRechazoWebhook($pedido->id, 'mercadopago');

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Liberación Reserva Web')
            ->latest()
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals(10.0, (float) $mov->cantidad_movimiento);
    }

    public function test_webhook_viumi_rechazo_libera_reserva(): void
    {
        $pedido = $this->crearPedidoWeb(10, 'pendiente');

        $this->simularRechazoWebhook($pedido->id, 'viumi');

        $stock = $this->getStock();
        $this->assertEquals(0.0, (float) $stock->cantidad_reservada);
    }

    public function test_webhook_viumi_rechazo_registra_movimiento(): void
    {
        $pedido = $this->crearPedidoWeb(10, 'pendiente');

        $this->simularRechazoWebhook($pedido->id, 'viumi');

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Liberación Reserva Web')
            ->latest()
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals(10.0, (float) $mov->cantidad_movimiento);
    }

    // ── REEMBOLSO ──

    public function test_reembolso_libera_reserva(): void
    {
        $pedido = $this->crearPedidoWeb(10, 'pagado');
        $pedido->update(['estado_pedido' => 'preparando']);

        DB::table('producto_sucursal')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['cantidad_reservada' => 10]);

        $this->actingAsAdminA();
        $response = $this->patch("/pedidos/{$pedido->id}/pago", ['estado_pago' => 'reembolsado']);
        $response->assertStatus(302);

        $stock = $this->getStock();
        $this->assertEquals(0.0, (float) $stock->cantidad_reservada);
    }

    public function test_reembolso_registra_movimiento(): void
    {
        $pedido = $this->crearPedidoWeb(10, 'pagado');
        $pedido->update(['estado_pedido' => 'preparando']);

        DB::table('producto_sucursal')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['cantidad_reservada' => 10]);

        $this->actingAsAdminA();
        $response = $this->patch("/pedidos/{$pedido->id}/pago", ['estado_pago' => 'reembolsado']);
        $response->assertStatus(302);

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Liberación Reserva Web')
            ->latest()
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals(10.0, (float) $mov->cantidad_movimiento);
    }
}
