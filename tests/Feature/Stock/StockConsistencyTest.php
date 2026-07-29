<?php

namespace Tests\Feature\Stock;

use App\Models\Producto;
use App\Models\Venta;
use App\Models\DetalleVenta;
use Illuminate\Support\Facades\DB;
use Tests\TestCaseMultiTenant;

/**
 * Test 1: Venta POS — decrementa stock físico, descuenta lotes FIFO, registra movimiento.
 * Test 2: Cancelación de venta — restaura stock y lotes, registra movimiento.
 * Test 10: Overselling — rechaza venta que excede stock disponible.
 */
class StockConsistencyTest extends TestCaseMultiTenant
{
    private int $productoId;
    private int $sucursalId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productoId = 90;
        $this->sucursalId = 1;

        DB::table('productos')->updateOrInsert(
            ['id' => $this->productoId],
            [
                'nombre' => 'Test Venta Stock',
                'codigo_barras' => 'TEST_VENTA_' . $this->productoId,
                'precio_costo' => 500,
                'precio_venta' => 800,
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
             'fecha_vencimiento' => now()->addMonths(3), 'stock_inicial' => 40, 'stock_actual' => 40,
             'estado_liquidacion' => false, 'created_at' => now(), 'updated_at' => now()],
            ['producto_id' => $this->productoId, 'sucursal_id' => $this->sucursalId,
             'fecha_vencimiento' => now()->addMonths(6), 'stock_inicial' => 35, 'stock_actual' => 35,
             'estado_liquidacion' => false, 'created_at' => now(), 'updated_at' => now()],
            ['producto_id' => $this->productoId, 'sucursal_id' => $this->sucursalId,
             'fecha_vencimiento' => now()->addMonths(9), 'stock_inicial' => 25, 'stock_actual' => 25,
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

    private function getLotes(): array
    {
        return DB::table('lotes')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->orderBy('fecha_vencimiento')
            ->get()
            ->toArray();
    }

    private function sumLotes(): float
    {
        return (float) DB::table('lotes')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->sum('stock_actual');
    }

    private function crearVenta(array $items, string $metodoPago = 'EFECTIVO'): Venta
    {
        $this->actingAsAdminA();

        $turno = DB::table('turno_cajas')->where('estado', 'Abierto')
            ->where('sucursal_id', $this->sucursalId)->first();

        $total = array_reduce($items, fn ($sum, $i) => $sum + ($i['precio_venta'] * $i['cantidad']), 0);

        $this->post('/ventas', [
            'turno_caja_id' => $turno->id,
            'items' => $items,
            'total' => $total,
            'metodo_pago' => $metodoPago,
        ]);

        return Venta::latest('id')->first();
    }

    // ── TEST 1: Venta POS decrementa stock y lotes FIFO ──

    public function test_venta_pos_decrementa_stock_fisico(): void
    {
        $antes = $this->getStock();

        $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 10, 'precio_venta' => 800],
        ]);

        $despues = $this->getStock();
        $this->assertEquals(90.0, (float) $despues->cantidad_fisica);
        $this->assertEquals($antes->cantidad_fisica - 10, (float) $despues->cantidad_fisica);
    }

    public function test_venta_pos_no_toca_reservada(): void
    {
        $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 5, 'precio_venta' => 800],
        ]);

        $stock = $this->getStock();
        $this->assertEquals(0.0, (float) $stock->cantidad_reservada);
    }

    public function test_venta_pos_registra_movimiento_stock(): void
    {
        $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 10, 'precio_venta' => 800],
        ]);

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Venta')
            ->latest()
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals(100.0, (float) $mov->cantidad_anterior);
        $this->assertEquals(-10.0, (float) $mov->cantidad_movimiento);
        $this->assertEquals(90.0, (float) $mov->cantidad_actual);
    }

    public function test_venta_pos_descuenta_lotes_fifo(): void
    {
        $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 50, 'precio_venta' => 800],
        ]);

        $lotes = $this->getLotes();

        $this->assertEquals(0.0, (float) $lotes[0]->stock_actual);
        $this->assertEquals(25.0, (float) $lotes[1]->stock_actual);
        $this->assertEquals(25.0, (float) $lotes[2]->stock_actual);
    }

    public function test_venta_pos_crea_detalle_venta_lote(): void
    {
        $venta = $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 15, 'precio_venta' => 800],
        ]);

        $detalle = DetalleVenta::where('venta_id', $venta->id)->first();
        $this->assertNotNull($detalle);

        $pivotes = DB::table('detalle_venta_lote')
            ->where('detalle_venta_id', $detalle->id)
            ->get();

        $this->assertGreaterThan(0, $pivotes->count());
        $sumaPivotes = $pivotes->sum('cantidad');
        $this->assertEquals(15.0, (float) $sumaPivotes);
    }

    public function test_venta_pos_invariante_lotes_igual_fisica(): void
    {
        $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 10, 'precio_venta' => 800],
        ]);

        $this->assertEquals($this->sumLotes(), (float) $this->getStock()->cantidad_fisica);
    }

    public function test_venta_pos_multiples_items(): void
    {
        $producto2Id = 91;
        DB::table('productos')->updateOrInsert(
            ['id' => $producto2Id],
            [
                'nombre' => 'Test Venta 2',
                'codigo_barras' => 'TEST_VENTA_91',
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
        DB::table('lotes')->where('producto_id', $producto2Id)
            ->where('sucursal_id', $this->sucursalId)->delete();
        DB::table('lotes')->insert([
            'producto_id' => $producto2Id, 'sucursal_id' => $this->sucursalId,
            'fecha_vencimiento' => now()->addMonths(6), 'stock_inicial' => 80, 'stock_actual' => 80,
            'estado_liquidacion' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 10, 'precio_venta' => 800],
            ['id' => $producto2Id, 'cantidad' => 20, 'precio_venta' => 500],
        ]);

        $this->assertEquals(90.0, (float) $this->getStock()->cantidad_fisica);
        $this->assertEquals(60.0, (float) DB::table('producto_sucursal')
            ->where('producto_id', $producto2Id)
            ->where('sucursal_id', $this->sucursalId)
            ->first()->cantidad_fisica);
    }

    // ── TEST 2: Cancelación de venta restaura stock ──

    public function test_cancelacion_venta_restaura_stock_fisico(): void
    {
        $venta = $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 10, 'precio_venta' => 800],
        ]);

        $this->actingAsAdminA();
        $this->patch("/ventas/{$venta->id}/cancelar", ['motivo' => 'Test cancelación']);

        $this->assertEquals(100.0, (float) $this->getStock()->cantidad_fisica);
    }

    public function test_cancelacion_venta_restaura_lotes(): void
    {
        $venta = $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 50, 'precio_venta' => 800],
        ]);

        $this->actingAsAdminA();
        $this->patch("/ventas/{$venta->id}/cancelar", ['motivo' => 'Test cancelación']);

        $lotes = $this->getLotes();
        $this->assertEquals(40.0, (float) $lotes[0]->stock_actual);
        $this->assertEquals(35.0, (float) $lotes[1]->stock_actual);
        $this->assertEquals(25.0, (float) $lotes[2]->stock_actual);
    }

    public function test_cancelacion_venta_registra_movimiento(): void
    {
        $venta = $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 10, 'precio_venta' => 800],
        ]);

        $this->actingAsAdminA();
        $this->patch("/ventas/{$venta->id}/cancelar", ['motivo' => 'Test cancelación']);

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Cancelación Venta')
            ->latest()
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals(90.0, (float) $mov->cantidad_anterior);
        $this->assertEquals(10.0, (float) $mov->cantidad_movimiento);
        $this->assertEquals(100.0, (float) $mov->cantidad_actual);
    }

    public function test_cancelacion_venta_invariante_lotes(): void
    {
        $venta = $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 10, 'precio_venta' => 800],
        ]);

        $this->actingAsAdminA();
        $this->patch("/ventas/{$venta->id}/cancelar", ['motivo' => 'Test cancelación']);

        $this->assertEquals($this->sumLotes(), (float) $this->getStock()->cantidad_fisica);
    }

    public function test_cancelacion_venta_cambia_estado(): void
    {
        $venta = $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 5, 'precio_venta' => 800],
        ]);

        $this->actingAsAdminA();
        $this->patch("/ventas/{$venta->id}/cancelar", ['motivo' => 'Test']);

        $this->assertDatabaseHas('ventas', ['id' => $venta->id, 'estado' => 'Cancelada']);
    }

    // ── TEST 10: Overselling ──

    public function test_overselling_rechaza_venta_supera_stock(): void
    {
        $this->actingAsAdminA();

        $turno = DB::table('turno_cajas')->where('estado', 'Abierto')
            ->where('sucursal_id', $this->sucursalId)->first();

        $this->post('/ventas', [
            'turno_caja_id' => $turno->id,
            'items' => [['id' => $this->productoId, 'cantidad' => 110, 'precio_venta' => 800]],
            'total' => 88000,
            'metodo_pago' => 'EFECTIVO',
        ]);

        $this->assertEquals(100.0, (float) $this->getStock()->cantidad_fisica);
    }

    public function test_venta_justo_en_stock_maximo_permitida(): void
    {
        $this->crearVenta([
            ['id' => $this->productoId, 'cantidad' => 100, 'precio_venta' => 800],
        ]);

        $this->assertEquals(0.0, (float) $this->getStock()->cantidad_fisica);
    }

    public function test_overselling_unidad_extra_rechazada(): void
    {
        $this->actingAsAdminA();

        $turno = DB::table('turno_cajas')->where('estado', 'Abierto')
            ->where('sucursal_id', $this->sucursalId)->first();

        $this->post('/ventas', [
            'turno_caja_id' => $turno->id,
            'items' => [['id' => $this->productoId, 'cantidad' => 101, 'precio_venta' => 800]],
            'total' => 80800,
            'metodo_pago' => 'EFECTIVO',
        ]);

        $this->assertEquals(100.0, (float) $this->getStock()->cantidad_fisica);
    }
}
