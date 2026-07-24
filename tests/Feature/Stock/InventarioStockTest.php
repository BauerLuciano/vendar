<?php

namespace Tests\Feature\Stock;

use App\Models\Producto;
use App\Models\IngresoMercaderia;
use App\Models\OrdenCompra;
use Illuminate\Support\Facades\DB;
use Tests\TestCaseMultiTenant;

/**
 * Test 3: Ingreso de mercadería — incrementa stock, PPP, movimiento, lote.
 * Test 4: Recepción de Orden de Compra — incrementa stock, PPP, movimiento, lote.
 */
class InventarioStockTest extends TestCaseMultiTenant
{
    private int $productoId;
    private int $sucursalId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productoId = 80;
        $this->sucursalId = 1;

        DB::table('productos')->updateOrInsert(
            ['id' => $this->productoId],
            [
                'nombre' => 'Test Inventario',
                'codigo_barras' => 'TEST_INV_' . $this->productoId,
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
            'producto_id' => $this->productoId, 'sucursal_id' => $this->sucursalId,
            'fecha_vencimiento' => now()->addMonths(3), 'stock_inicial' => 100, 'stock_actual' => 100,
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

    // ── TEST 3: Ingreso de mercadería ──

    public function test_ingreso_incrementa_stock_fisico(): void
    {
        $this->actingAsAdminA();

        $this->post('/ingresos', [
            'sucursal_id' => $this->sucursalId,
            'fecha_ingreso' => now()->toDateString(),
            'numero_remito' => 'REM-TEST-001',
            'items' => [
                [
                    'producto_id' => $this->productoId,
                    'cantidad' => 50,
                    'costo' => 600,
                    'fecha_vencimiento' => now()->addMonths(6)->toDateString(),
                ],
            ],
        ]);

        $this->assertEquals(150.0, (float) $this->getStock()->cantidad_fisica);
    }

    public function test_ingreso_crea_lote(): void
    {
        $this->actingAsAdminA();

        $fechaVenc = now()->addMonths(3)->toDateString();

        $this->post('/ingresos', [
            'sucursal_id' => $this->sucursalId,
            'fecha_ingreso' => now()->toDateString(),
            'items' => [
                [
                    'producto_id' => $this->productoId,
                    'cantidad' => 30,
                    'costo' => 550,
                    'fecha_vencimiento' => $fechaVenc,
                ],
            ],
        ]);

        $lote = DB::table('lotes')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('fecha_vencimiento', $fechaVenc)
            ->first();

        $this->assertNotNull($lote);
        $this->assertEquals(130.0, (float) $lote->stock_actual);
    }

    public function test_ingreso_registra_movimiento_stock(): void
    {
        $this->actingAsAdminA();

        $this->post('/ingresos', [
            'sucursal_id' => $this->sucursalId,
            'fecha_ingreso' => now()->toDateString(),
            'numero_remito' => 'REM-TEST-002',
            'items' => [
                [
                    'producto_id' => $this->productoId,
                    'cantidad' => 25,
                    'costo' => 500,
                    'fecha_vencimiento' => now()->addMonths(4)->toDateString(),
                ],
            ],
        ]);

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Ingreso Manual')
            ->latest()
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals(100.0, (float) $mov->cantidad_anterior);
        $this->assertEquals(25.0, (float) $mov->cantidad_movimiento);
        $this->assertEquals(125.0, (float) $mov->cantidad_actual);
    }

    public function test_ingreso_actualiza_ppp(): void
    {
        $this->actingAsAdminA();

        $this->post('/ingresos', [
            'sucursal_id' => $this->sucursalId,
            'fecha_ingreso' => now()->toDateString(),
            'items' => [
                [
                    'producto_id' => $this->productoId,
                    'cantidad' => 100,
                    'costo' => 1000,
                    'fecha_vencimiento' => now()->addMonths(6)->toDateString(),
                ],
            ],
        ]);

        $producto = Producto::find($this->productoId);
        $pppEsperado = round((100 * 500 + 100 * 1000) / 200, 2);
        $this->assertEquals($pppEsperado, (float) $producto->precio_costo);
    }

    public function test_ingreso_invariante_lotes_igual_fisica(): void
    {
        $this->actingAsAdminA();

        $this->post('/ingresos', [
            'sucursal_id' => $this->sucursalId,
            'fecha_ingreso' => now()->toDateString(),
            'items' => [
                [
                    'producto_id' => $this->productoId,
                    'cantidad' => 50,
                    'costo' => 600,
                    'fecha_vencimiento' => now()->addMonths(6)->toDateString(),
                ],
            ],
        ]);

        $this->assertEquals($this->sumLotes(), (float) $this->getStock()->cantidad_fisica);
    }

    // ── TEST 4: Recepción de Orden de Compra ──

    public function test_recepcion_oc_incrementa_stock(): void
    {
        $oc = OrdenCompra::create([
            'proveedor_id' => 1,
            'sucursal_id' => $this->sucursalId,
            'user_id' => $this->adminA->id,
            'nro_comprobante' => 'OC-TEST-001',
            'fecha_emision' => now(),
            'estado' => 'Confirmada',
            'total_estimado' => 30000,
        ]);

        DB::table('orden_compra_detalles')->insert([
            'orden_compra_id' => $oc->id,
            'producto_id' => $this->productoId,
            'cantidad_pedida' => 30,
            'cantidad_recibida' => 0,
            'costo_unitario_estimado' => 500,
            'subtotal_estimado' => 15000,
            'fecha_vencimiento' => now()->addMonths(6)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsAdminA();
        $this->post("/ordenes-compra/{$oc->id}/recibir", [
            'items' => [
                [
                    'orden_compra_detalle_id' => DB::table('orden_compra_detalles')->where('orden_compra_id', $oc->id)->first()->id,
                    'cantidad_recibir' => 30,
                ],
            ],
        ]);

        $this->assertEquals(130.0, (float) $this->getStock()->cantidad_fisica);
    }

    public function test_recepcion_oc_registra_movimiento(): void
    {
        $oc = OrdenCompra::create([
            'proveedor_id' => 1,
            'sucursal_id' => $this->sucursalId,
            'user_id' => $this->adminA->id,
            'nro_comprobante' => 'OC-TEST-002',
            'fecha_emision' => now(),
            'estado' => 'Confirmada',
            'total_estimado' => 20000,
        ]);

        DB::table('orden_compra_detalles')->insert([
            'orden_compra_id' => $oc->id,
            'producto_id' => $this->productoId,
            'cantidad_pedida' => 20,
            'cantidad_recibida' => 0,
            'costo_unitario_estimado' => 500,
            'subtotal_estimado' => 10000,
            'fecha_vencimiento' => now()->addMonths(6)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsAdminA();
        $detalleId = DB::table('orden_compra_detalles')->where('orden_compra_id', $oc->id)->first()->id;
        $this->post("/ordenes-compra/{$oc->id}/recibir", [
            'items' => [
                [
                    'orden_compra_detalle_id' => $detalleId,
                    'cantidad_recibir' => 20,
                ],
            ],
        ]);

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo_movimiento', 'Ingreso OC')
            ->latest()
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals(100.0, (float) $mov->cantidad_anterior);
        $this->assertEquals(20.0, (float) $mov->cantidad_movimiento);
        $this->assertEquals(120.0, (float) $mov->cantidad_actual);
    }

    public function test_recepcion_oc_crea_lote(): void
    {
        $fechaVenc = now()->addMonths(3)->toDateString();

        $oc = OrdenCompra::create([
            'proveedor_id' => 1,
            'sucursal_id' => $this->sucursalId,
            'user_id' => $this->adminA->id,
            'nro_comprobante' => 'OC-TEST-003',
            'fecha_emision' => now(),
            'estado' => 'Confirmada',
            'total_estimado' => 10000,
        ]);

        DB::table('orden_compra_detalles')->insert([
            'orden_compra_id' => $oc->id,
            'producto_id' => $this->productoId,
            'cantidad_pedida' => 10,
            'cantidad_recibida' => 0,
            'costo_unitario_estimado' => 500,
            'subtotal_estimado' => 5000,
            'fecha_vencimiento' => $fechaVenc,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsAdminA();
        $detalleId = DB::table('orden_compra_detalles')->where('orden_compra_id', $oc->id)->first()->id;
        $this->post("/ordenes-compra/{$oc->id}/recibir", [
            'items' => [
                [
                    'orden_compra_detalle_id' => $detalleId,
                    'cantidad_recibir' => 10,
                ],
            ],
        ]);

        $lote = DB::table('lotes')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->sucursalId)
            ->where('fecha_vencimiento', $fechaVenc)
            ->first();

        $this->assertNotNull($lote);
        $this->assertEquals(110.0, (float) $lote->stock_actual);
    }

    public function test_recepcion_oc_actualiza_ppp(): void
    {
        $oc = OrdenCompra::create([
            'proveedor_id' => 1,
            'sucursal_id' => $this->sucursalId,
            'user_id' => $this->adminA->id,
            'nro_comprobante' => 'OC-TEST-004',
            'fecha_emision' => now(),
            'estado' => 'Confirmada',
            'total_estimado' => 20000,
        ]);

        DB::table('orden_compra_detalles')->insert([
            'orden_compra_id' => $oc->id,
            'producto_id' => $this->productoId,
            'cantidad_pedida' => 100,
            'cantidad_recibida' => 0,
            'costo_unitario_estimado' => 800,
            'subtotal_estimado' => 80000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsAdminA();
        $detalleId = DB::table('orden_compra_detalles')->where('orden_compra_id', $oc->id)->first()->id;
        $this->post("/ordenes-compra/{$oc->id}/recibir", [
            'items' => [
                [
                    'orden_compra_detalle_id' => $detalleId,
                    'cantidad_recibir' => 100,
                ],
            ],
        ]);

        $producto = Producto::find($this->productoId);
        $pppEsperado = round((100 * 500 + 100 * 800) / 200, 2);
        $this->assertEquals($pppEsperado, (float) $producto->precio_costo);
    }

    public function test_recepcion_oc_invariante_lotes(): void
    {
        $oc = OrdenCompra::create([
            'proveedor_id' => 1,
            'sucursal_id' => $this->sucursalId,
            'user_id' => $this->adminA->id,
            'nro_comprobante' => 'OC-TEST-005',
            'fecha_emision' => now(),
            'estado' => 'Aprobada',
            'total_estimado' => 15000,
        ]);

        DB::table('orden_compra_detalles')->insert([
            'orden_compra_id' => $oc->id,
            'producto_id' => $this->productoId,
            'cantidad_pedida' => 15,
            'cantidad_recibida' => 0,
            'costo_unitario_estimado' => 500,
            'subtotal_estimado' => 7500,
            'fecha_vencimiento' => now()->addMonths(6)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsAdminA();
        $this->post("/ordenes-compra/{$oc->id}/aprobar");

        $this->assertEquals($this->sumLotes(), (float) $this->getStock()->cantidad_fisica);
    }
}
