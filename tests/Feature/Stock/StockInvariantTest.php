<?php

namespace Tests\Feature\Stock;

use App\Models\Producto;
use App\Models\Venta;
use App\Models\PedidoWeb;
use App\Models\PedidoWebItem;
use Illuminate\Support\Facades\DB;
use Tests\TestCaseMultiTenant;

/**
 * Test 11: Invariantes — después de cada operación, SUM(lotes.stock_actual) == cantidad_fisica.
 * Test 12: movimientos_stock — cada operación genera exactamente 1 movimiento correcto.
 *
 * Tests transversales que validan consistencia global del sistema de stock.
 */
class StockInvariantTest extends TestCaseMultiTenant
{
    private int $productoId;
    private int $sucursalId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productoId = 50;
        $this->sucursalId = 1;

        DB::table('productos')->updateOrInsert(
            ['id' => $this->productoId],
            [
                'nombre' => 'Test Invariantes',
                'codigo_barras' => 'TEST_INVARIANT_' . $this->productoId,
                'precio_costo' => 400,
                'precio_venta' => 700,
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
            ['producto_id' => $this->productoId, 'sucursal_id' => $this->sucursalId,
             'fecha_vencimiento' => now()->addMonths(3), 'stock_inicial' => 50, 'stock_actual' => 50,
             'estado_liquidacion' => false, 'created_at' => now(), 'updated_at' => now()],
            ['producto_id' => $this->productoId, 'sucursal_id' => $this->sucursalId,
             'fecha_vencimiento' => now()->addMonths(6), 'stock_inicial' => 30, 'stock_actual' => 30,
             'estado_liquidacion' => false, 'created_at' => now(), 'updated_at' => now()],
            ['producto_id' => $this->productoId, 'sucursal_id' => $this->sucursalId,
             'fecha_vencimiento' => now()->addMonths(9), 'stock_inicial' => 20, 'stock_actual' => 20,
             'estado_liquidacion' => false, 'created_at' => now(), 'updated_at' => now()],
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

    private function assertInvarianteLotes(): void
    {
        $this->assertEquals(
            $this->sumLotes(),
            (float) $this->getStock()->cantidad_fisica,
            "INVARIANT BROKEN: SUM(lotes.stock_actual)={$this->sumLotes()} != cantidad_fisica=" . $this->getStock()->cantidad_fisica
        );
    }

    private function crearVenta(array $items): Venta
    {
        $this->actingAsAdminA();

        $turno = DB::table('turno_cajas')->where('estado', 'Abierto')
            ->where('sucursal_id', $this->sucursalId)->first();

        $total = array_reduce($items, fn ($sum, $i) => $sum + ($i['precio_venta'] * $i['cantidad']), 0);

        $this->post('/ventas', [
            'turno_caja_id' => $turno->id,
            'items' => $items,
            'total' => $total,
            'metodo_pago' => 'EFECTIVO',
        ]);

        return Venta::latest('id')->first();
    }

    // ══════════════════════════════════════════════
    // TEST 11: INVARIANTES
    // ══════════════════════════════════════════════

    public function test_invariante_estado_inicial(): void
    {
        $this->assertInvarianteLotes();
    }

    public function test_invariante_despues_venta(): void
    {
        $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 15, 'precio_venta' => 700],
        ]);

        $this->assertInvarianteLotes();
    }

    public function test_invariante_despues_cancelacion(): void
    {
        $venta = $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 15, 'precio_venta' => 700],
        ]);

        $this->actingAsAdminA();
        $this->patch("/ventas/{$venta->id}/cancelar", ['motivo' => 'Test']);

        $this->assertInvarianteLotes();
    }

    public function test_invariante_venta_y_cancelacion_ciclo(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $venta = $this->crearVenta([
                ['id' => $this->productoId, 'cantidad' => 5, 'precio_venta' => 700],
            ]);

            $this->actingAsAdminA();
            $this->patch("/ventas/{$venta->id}/cancelar", ['motivo' => "Ciclo {$i}"]);
        }

        $this->assertInvarianteLotes();
        $this->assertEquals(100.0, (float) $this->getStock()->cantidad_fisica);
    }

    public function test_invariante_despues_venta_grande(): void
    {
        $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 95, 'precio_venta' => 700],
        ]);

        $this->assertInvarianteLotes();
        $this->assertEquals(5.0, (float) $this->getStock()->cantidad_fisica);
    }

    public function test_invariante_stock_cero(): void
    {
        $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 100, 'precio_venta' => 700],
        ]);

        $this->assertInvarianteLotes();
        $this->assertEquals(0.0, (float) $this->getStock()->cantidad_fisica);
        $this->assertEquals(0.0, $this->sumLotes());
    }

    public function test_invariante_reserva_no_afecta(): void
    {
        $this->actingAsAdminA();

        $this->post('/api/pedidos-web', [
            'comercio_id' => 1,
            'sucursal_id' => $this->sucursalId,
            'items' => [['id' => $this->productoId, 'cantidad' => 20]],
            'tipo_entrega' => 'local',
            'metodo_pago' => 'efectivo',
        ]);

        $this->assertInvarianteLotes();
    }

    public function test_invariante_entrega_pedido_web(): void
    {
        $pedidoId = DB::table('pedidos_web')->insertGetId([
            'comercio_id' => 1, 'sucursal_id' => $this->sucursalId,
            'cliente_nombre' => 'Inv Test', 'cliente_telefono' => '111111111',
            'subtotal' => 7000, 'total' => 7000,
            'metodo_pago' => 'efectivo', 'estado_pedido' => 'nuevo', 'estado_pago' => 'pagado',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('pedido_web_items')->insert([
            'pedido_web_id' => $pedidoId, 'producto_id' => $this->productoId,
            'cantidad' => 10, 'precio_unitario' => 700, 'subtotal' => 7000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('producto_sucursal')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['cantidad_reservada' => 10]);

        $this->actingAsAdminA();
        $this->patch("/pedidos/{$pedidoId}/estado", ['estado_pedido' => 'entregado']);

        $stock = $this->getStock();
        $this->assertEquals(90.0, (float) $stock->cantidad_fisica);
        $this->assertEquals(0.0, (float) $stock->cantidad_reservada);

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Entrega Pedido Web')
            ->latest()->first();
        $this->assertNotNull($mov);

        $this->assertInvarianteLotes();
    }

    public function test_invariante_cancelacion_post_entrega(): void
    {
        $pedido = PedidoWeb::create([
            'comercio_id' => 1, 'sucursal_id' => $this->sucursalId,
            'cliente_nombre' => 'Inv Cancel Post', 'subtotal' => 7000, 'total' => 7000,
            'metodo_pago' => 'efectivo', 'estado_pedido' => 'entregado', 'estado_pago' => 'pagado',
        ]);
        PedidoWebItem::create([
            'pedido_web_id' => $pedido->id, 'producto_id' => $this->productoId,
            'cantidad' => 10, 'precio_unitario' => 700, 'subtotal' => 7000,
        ]);

        DB::table('producto_sucursal')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['cantidad_fisica' => 90, 'cantidad_reservada' => 0]);

        $this->actingAsAdminA();
        $this->patch("/pedidos/{$pedido->id}/estado", ['estado_pedido' => 'cancelado']);

        $this->assertInvarianteLotes();
    }

    // ══════════════════════════════════════════════
    // TEST 12: MOVIMIENTOS_STOCK EXACTITUD
    // ══════════════════════════════════════════════

    public function test_movimiento_venta_cantidad_anterior_correcta(): void
    {
        $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 10, 'precio_venta' => 700],
        ]);

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Venta')
            ->latest()->first();

        $this->assertEquals(100.0, (float) $mov->cantidad_anterior);
    }

    public function test_movimiento_venta_cantidad_movimiento_correcta(): void
    {
        $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 10, 'precio_venta' => 700],
        ]);

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Venta')
            ->latest()->first();

        $this->assertEquals(-10.0, (float) $mov->cantidad_movimiento);
    }

    public function test_movimiento_venta_cantidad_actual_correcta(): void
    {
        $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 10, 'precio_venta' => 700],
        ]);

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Venta')
            ->latest()->first();

        $this->assertEquals(90.0, (float) $mov->cantidad_actual);
    }

    public function test_movimiento_venta_multiples_uno_por_item(): void
    {
        $producto2Id = 51;
        DB::table('productos')->updateOrInsert(
            ['id' => $producto2Id],
            [
                'nombre' => 'Test Inv 2',
                'codigo_barras' => 'TEST_INVARIANT_51',
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
            ['producto_id' => $producto2Id, 'sucursal_id' => $this->sucursalId],
            ['cantidad_fisica' => 80, 'cantidad_reservada' => 0, 'created_at' => now(), 'updated_at' => now()]
        );

        $antes = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->count();

        $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 10, 'precio_venta' => 700],
            ['id' => $producto2Id, 'cantidad' => 5, 'precio_venta' => 500],
        ]);

        $despues = DB::table('movimientos_stock')
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Venta')
            ->count();

        $this->assertEquals($antes + 2, $despues);
    }

    public function test_movimiento_cancelacion_cadena_correcta(): void
    {
        $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 20, 'precio_venta' => 700],
        ]);

        $movVenta = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Venta')
            ->latest()->first();

        $this->actingAsAdminA();
        $this->patch("/ventas/" . Venta::latest('id')->first()->id . "/cancelar", ['motivo' => 'Test']);

        $movCancel = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Cancelación Venta')
            ->latest()->first();

        $this->assertEquals($movVenta->cantidad_actual, (float) $movCancel->cantidad_anterior);
        $this->assertEquals($movVenta->cantidad_anterior, (float) $movCancel->cantidad_actual);
        $this->assertEquals(-1 * $movVenta->cantidad_movimiento, (float) $movCancel->cantidad_movimiento);
    }

    public function test_movimiento_reserva_cadena_correcta(): void
    {
        $this->actingAsAdminA();

        $this->post('/api/pedidos-web', [
            'comercio_id' => 1,
            'sucursal_id' => $this->sucursalId,
            'items' => [['id' => $this->productoId, 'cantidad' => 10]],
            'tipo_entrega' => 'local',
            'metodo_pago' => 'efectivo',
        ]);

        $movReserva = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Reserva Pedido Web')
            ->latest()->first();

        $this->assertNotNull($movReserva);
        $this->assertEquals(-10.0, (float) $movReserva->cantidad_movimiento);

        $pedido = PedidoWeb::latest('id')->first();
        $this->patch("/pedidos/{$pedido->id}/estado", ['estado_pedido' => 'cancelado']);

        $movLibera = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Liberación Reserva Web')
            ->latest()->first();

        $this->assertNotNull($movLibera);
        $this->assertEquals(10.0, (float) $movLibera->cantidad_movimiento);
    }

    public function test_movimiento_ingreso_cadena_correcta(): void
    {
        $this->actingAsAdminA();

        $this->post('/ingresos', [
            'sucursal_id' => $this->sucursalId,
            'fecha_ingreso' => now()->toDateString(),
            'items' => [
                ['producto_id' => $this->productoId, 'cantidad' => 30, 'costo' => 600,
                 'fecha_vencimiento' => now()->addMonths(6)->toDateString()],
            ],
        ]);

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Ingreso Manual')
            ->latest()->first();

        $this->assertNotNull($mov);
        $this->assertEquals(100.0, (float) $mov->cantidad_anterior);
        $this->assertEquals(30.0, (float) $mov->cantidad_movimiento);
        $this->assertEquals(130.0, (float) $mov->cantidad_actual);
    }

    public function test_movimiento_transferencia_despacho_correcto(): void
    {
        $this->actingAsAdminA();

        $this->post('/ingresos', [
            'sucursal_id' => 2,
            'fecha_ingreso' => now()->toDateString(),
            'items' => [
                ['producto_id' => $this->productoId, 'cantidad' => 50, 'costo' => 400,
                 'fecha_vencimiento' => now()->addMonths(6)->toDateString()],
            ],
        ]);

        $stockAntes = DB::table('producto_sucursal')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', 2)
            ->first();

        $transferencia = \App\Models\TransferenciaSugerida::create([
            'origen_id' => 2, 'destino_id' => 1,
            'producto_id' => $this->productoId,
            'cantidad' => 10, 'estado' => 'pendiente',
        ]);

        $this->post("/transferencias-sugeridas/{$transferencia->id}/despachar");

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', 2)
            ->where('tipo_movimiento', 'Transferencia Enviada')
            ->latest()->first();

        $this->assertNotNull($mov);
        $this->assertEquals((float) $stockAntes->cantidad_fisica, (float) $mov->cantidad_anterior);
        $this->assertEquals(-10.0, (float) $mov->cantidad_movimiento);
        $this->assertEquals((float) $stockAntes->cantidad_fisica - 10, (float) $mov->cantidad_actual);
    }

    public function test_movimiento_expiracion_correcto(): void
    {
        $pedidoId = DB::table('pedidos_web')->insertGetId([
            'comercio_id' => 1, 'sucursal_id' => $this->sucursalId,
            'cliente_nombre' => 'Mov Exp', 'cliente_telefono' => '111111111',
            'subtotal' => 3500, 'total' => 3500,
            'metodo_pago' => 'efectivo', 'estado_pedido' => 'nuevo', 'estado_pago' => 'pendiente',
            'created_at' => now()->subMinutes(31), 'updated_at' => now()->subMinutes(31),
        ]);
        DB::table('pedido_web_items')->insert([
            'pedido_web_id' => $pedidoId, 'producto_id' => $this->productoId,
            'cantidad' => 5, 'precio_unitario' => 700, 'subtotal' => 3500,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('producto_sucursal')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['cantidad_reservada' => 5]);

        $antes = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->count();

        $job = new \App\Jobs\ExpirarPedidosPendientes();
        $job->handle();

        $despues = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->count();

        $this->assertEquals($antes + 1, $despues);

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Liberación Reserva Web')
            ->latest()->first();

        $this->assertEquals(5.0, (float) $mov->cantidad_movimiento);
    }

    // ── TESTS DE CONSISTENCIA TRANSVERSAL ──

    public function test_stock_disponible_nunca_negativo(): void
    {
        $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 100, 'precio_venta' => 700],
        ]);

        $stock = $this->getStock();
        $disponible = (float) $stock->cantidad_fisica - (float) $stock->cantidad_reservada;
        $this->assertGreaterThanOrEqual(0, $disponible);
    }

    public function test_lotes_suman_igual_fisica_multiples_operaciones(): void
    {
        $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 30, 'precio_venta' => 700],
        ]);

        $this->actingAsAdminA();
        $this->post('/ingresos', [
            'sucursal_id' => $this->sucursalId,
            'fecha_ingreso' => now()->toDateString(),
            'items' => [
                ['producto_id' => $this->productoId, 'cantidad' => 20, 'costo' => 500,
                 'fecha_vencimiento' => now()->addMonths(12)->toDateString()],
            ],
        ]);

        $this->assertInvarianteLotes();
    }

    public function test_reserva_luego_venta_invariante(): void
    {
        $this->actingAsAdminA();

        $this->post('/api/pedidos-web', [
            'comercio_id' => 1,
            'sucursal_id' => $this->sucursalId,
            'items' => [['id' => $this->productoId, 'cantidad' => 20]],
            'tipo_entrega' => 'local',
            'metodo_pago' => 'efectivo',
        ]);

        $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 30, 'precio_venta' => 700],
        ]);

        $this->assertInvarianteLotes();
    }

    public function test_cancelacion_venta_y_liberacion_reserva(): void
    {
        $venta = $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 10, 'precio_venta' => 700],
        ]);

        $this->actingAsAdminA();
        $this->patch("/ventas/{$venta->id}/cancelar", ['motivo' => 'Test']);

        $this->post('/api/pedidos-web', [
            'comercio_id' => 1,
            'sucursal_id' => $this->sucursalId,
            'items' => [['id' => $this->productoId, 'cantidad' => 15]],
            'tipo_entrega' => 'local',
            'metodo_pago' => 'efectivo',
        ]);

        $this->assertInvarianteLotes();
        $this->assertEquals(100.0, (float) $this->getStock()->cantidad_fisica);
    }
}
