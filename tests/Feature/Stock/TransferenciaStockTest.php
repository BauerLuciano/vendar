<?php

namespace Tests\Feature\Stock;

use App\Models\Producto;
use App\Models\TransferenciaSugerida;
use Illuminate\Support\Facades\DB;
use Tests\TestCaseMultiTenant;

/**
 * Test 5: Transferencia entre sucursales — despacho, recepción, rechazo.
 * Verifica que stock se mueve correctamente entre origen y destino,
 * y que un rechazo restaura exactamente el estado inicial.
 */
class TransferenciaStockTest extends TestCaseMultiTenant
{
    private int $productoId;
    private int $origenId;
    private int $destinoId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productoId = 70;
        $this->origenId = 1;
        $this->destinoId = 2;

        DB::table('productos')->updateOrInsert(
            ['id' => $this->productoId],
            [
                'nombre' => 'Test Transferencia',
                'codigo_barras' => 'TEST_TRANS_' . $this->productoId,
                'precio_costo' => 400,
                'precio_venta' => 650,
                'stock_minimo' => 5,
                'unidad_medida' => 'Unidad',
                'estado' => true,
                'categoria_id' => 1,
                'marca_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('producto_sucursal')->where('producto_id', $this->productoId)->delete();

        DB::table('producto_sucursal')->insert([
            ['producto_id' => $this->productoId, 'sucursal_id' => $this->origenId,
             'cantidad_fisica' => 100, 'cantidad_reservada' => 0,
             'created_at' => now(), 'updated_at' => now()],
            ['producto_id' => $this->productoId, 'sucursal_id' => $this->destinoId,
             'cantidad_fisica' => 50, 'cantidad_reservada' => 0,
             'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('lotes')->where('producto_id', $this->productoId)->delete();

        DB::table('lotes')->insert([
            ['producto_id' => $this->productoId, 'sucursal_id' => $this->origenId,
             'fecha_vencimiento' => now()->addMonths(3), 'stock_inicial' => 60, 'stock_actual' => 60,
             'estado_liquidacion' => false, 'created_at' => now(), 'updated_at' => now()],
            ['producto_id' => $this->productoId, 'sucursal_id' => $this->origenId,
             'fecha_vencimiento' => now()->addMonths(6), 'stock_inicial' => 40, 'stock_actual' => 40,
             'estado_liquidacion' => false, 'created_at' => now(), 'updated_at' => now()],
            ['producto_id' => $this->productoId, 'sucursal_id' => $this->destinoId,
             'fecha_vencimiento' => now()->addMonths(4), 'stock_inicial' => 50, 'stock_actual' => 50,
             'estado_liquidacion' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('movimientos_stock')->where('producto_id', $this->productoId)->delete();
    }

    private function getStockOrigen(): object
    {
        return DB::table('producto_sucursal')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->origenId)
            ->first();
    }

    private function getStockDestino(): object
    {
        return DB::table('producto_sucursal')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->destinoId)
            ->first();
    }

    private function sumLotesOrigen(): float
    {
        return (float) DB::table('lotes')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->origenId)
            ->sum('stock_actual');
    }

    private function sumLotesDestino(): float
    {
        return (float) DB::table('lotes')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->destinoId)
            ->sum('stock_actual');
    }

    // ── DESPACHO ──

    public function test_despacho_baja_stock_origen(): void
    {
        $transferencia = TransferenciaSugerida::create([
            'origen_id' => $this->origenId,
            'destino_id' => $this->destinoId,
            'producto_id' => $this->productoId,
            'cantidad' => 20,
            'estado' => 'pendiente',
        ]);

        $this->actingAsAdminA();
        $this->post("/transferencias-sugeridas/{$transferencia->id}/despachar");

        $this->assertEquals(80.0, (float) $this->getStockOrigen()->cantidad_fisica);
    }

    public function test_despacho_baja_lotes_origen_fifo(): void
    {
        $transferencia = TransferenciaSugerida::create([
            'origen_id' => $this->origenId,
            'destino_id' => $this->destinoId,
            'producto_id' => $this->productoId,
            'cantidad' => 70,
            'estado' => 'pendiente',
        ]);

        $this->actingAsAdminA();
        $this->post("/transferencias-sugeridas/{$transferencia->id}/despachar");

        $lotes = DB::table('lotes')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->origenId)
            ->orderBy('fecha_vencimiento')
            ->get();

        $this->assertEquals(0.0, (float) $lotes[0]->stock_actual);
        $this->assertEquals(30.0, (float) $lotes[1]->stock_actual);
    }

    public function test_despacho_registra_movimiento(): void
    {
        $transferencia = TransferenciaSugerida::create([
            'origen_id' => $this->origenId,
            'destino_id' => $this->destinoId,
            'producto_id' => $this->productoId,
            'cantidad' => 25,
            'estado' => 'pendiente',
        ]);

        $this->actingAsAdminA();
        $this->post("/transferencias-sugeridas/{$transferencia->id}/despachar");

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->origenId)
            ->where('tipo_movimiento', 'Transferencia Enviada')
            ->latest()
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals(100.0, (float) $mov->cantidad_anterior);
        $this->assertEquals(-25.0, (float) $mov->cantidad_movimiento);
        $this->assertEquals(75.0, (float) $mov->cantidad_actual);
    }

    public function test_despacho_cambia_estado_en_transito(): void
    {
        $transferencia = TransferenciaSugerida::create([
            'origen_id' => $this->origenId,
            'destino_id' => $this->destinoId,
            'producto_id' => $this->productoId,
            'cantidad' => 10,
            'estado' => 'pendiente',
        ]);

        $this->actingAsAdminA();
        $this->post("/transferencias-sugeridas/{$transferencia->id}/despachar");

        $this->assertDatabaseHas('transferencia_sugeridas', [
            'id' => $transferencia->id,
            'estado' => 'en_transito',
        ]);
    }

    public function test_despacho_guarda_lotes_despacho(): void
    {
        $transferencia = TransferenciaSugerida::create([
            'origen_id' => $this->origenId,
            'destino_id' => $this->destinoId,
            'producto_id' => $this->productoId,
            'cantidad' => 30,
            'estado' => 'pendiente',
        ]);

        $this->actingAsAdminA();
        $this->post("/transferencias-sugeridas/{$transferencia->id}/despachar");

        $t = TransferenciaSugerida::find($transferencia->id);
        $this->assertNotNull($t->lotes_despacho);
        $this->assertGreaterThan(0, count($t->lotes_despacho));
    }

    // ── RECEPCIÓN ──

    public function test_recepcion_aumenta_stock_destino(): void
    {
        $transferencia = TransferenciaSugerida::create([
            'origen_id' => $this->origenId,
            'destino_id' => $this->destinoId,
            'producto_id' => $this->productoId,
            'cantidad' => 20,
            'estado' => 'en_transito',
            'lotes_despacho' => [
                ['lote_id' => 0, 'cantidad' => 20, 'fecha_vencimiento' => now()->addMonths(6)->format('Y-m-d')],
            ],
        ]);

        $this->actingAsAdminA();
        $this->post("/transferencias-sugeridas/{$transferencia->id}/recibir");

        $this->assertEquals(70.0, (float) $this->getStockDestino()->cantidad_fisica);
    }

    public function test_recepcion_registra_movimiento(): void
    {
        $transferencia = TransferenciaSugerida::create([
            'origen_id' => $this->origenId,
            'destino_id' => $this->destinoId,
            'producto_id' => $this->productoId,
            'cantidad' => 15,
            'estado' => 'en_transito',
            'lotes_despacho' => [
                ['lote_id' => 0, 'cantidad' => 15, 'fecha_vencimiento' => now()->addMonths(3)->format('Y-m-d')],
            ],
        ]);

        $this->actingAsAdminA();
        $this->post("/transferencias-sugeridas/{$transferencia->id}/recibir");

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->destinoId)
            ->where('tipo_movimiento', 'Transferencia Recibida')
            ->latest()
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals(50.0, (float) $mov->cantidad_anterior);
        $this->assertEquals(15.0, (float) $mov->cantidad_movimiento);
        $this->assertEquals(65.0, (float) $mov->cantidad_actual);
    }

    public function test_recepcion_invariante_lotes_destino(): void
    {
        $transferencia = TransferenciaSugerida::create([
            'origen_id' => $this->origenId,
            'destino_id' => $this->destinoId,
            'producto_id' => $this->productoId,
            'cantidad' => 20,
            'estado' => 'en_transito',
            'lotes_despacho' => [
                ['lote_id' => 0, 'cantidad' => 20, 'fecha_vencimiento' => now()->addMonths(6)->format('Y-m-d')],
            ],
        ]);

        $this->actingAsAdminA();
        $this->post("/transferencias-sugeridas/{$transferencia->id}/recibir");

        $this->assertEquals($this->sumLotesDestino(), (float) $this->getStockDestino()->cantidad_fisica);
    }

    // ── RECHAZO ──

    private function prepararParaRechazo(int $cantidad): array
    {
        $lote = DB::table('lotes')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->origenId)
            ->where('stock_actual', '>', 0)
            ->first();

        $fechaVenc = $lote->fecha_vencimiento;

        DB::table('producto_sucursal')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->origenId)
            ->update(['cantidad_fisica' => 100 - $cantidad]);

        DB::table('lotes')
            ->where('id', $lote->id)
            ->decrement('stock_actual', $cantidad);

        $transferencia = TransferenciaSugerida::create([
            'origen_id' => $this->origenId,
            'destino_id' => $this->destinoId,
            'producto_id' => $this->productoId,
            'cantidad' => $cantidad,
            'estado' => 'en_transito',
            'lotes_despacho' => [
                ['lote_id' => $lote->id, 'cantidad' => $cantidad, 'fecha_vencimiento' => $fechaVenc],
            ],
        ]);

        return ['transferencia' => $transferencia, 'loteId' => $lote->id, 'fechaVenc' => $fechaVenc];
    }

    public function test_rechazo_restaura_stock_origen(): void
    {
        ['transferencia' => $transferencia] = $this->prepararParaRechazo(20);

        $this->actingAsAdminA();
        app(\App\Http\Controllers\TransferenciaSugeridaController::class)->rechazar($transferencia);

        $this->assertEquals(100.0, (float) $this->getStockOrigen()->cantidad_fisica);
    }

    public function test_rechazo_restaura_lotes_origen(): void
    {
        $lotesOriginales = DB::table('lotes')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->origenId)
            ->orderBy('fecha_vencimiento')
            ->get()
            ->map(fn ($l) => (float) $l->stock_actual)
            ->toArray();

        ['transferencia' => $transferencia] = $this->prepararParaRechazo(20);

        $this->actingAsAdminA();
        app(\App\Http\Controllers\TransferenciaSugeridaController::class)->rechazar($transferencia);

        $lotesDespues = DB::table('lotes')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->origenId)
            ->orderBy('fecha_vencimiento')
            ->get()
            ->map(fn ($l) => (float) $l->stock_actual)
            ->toArray();

        $this->assertEquals($lotesOriginales, $lotesDespues);
    }

    public function test_rechazo_registra_movimiento(): void
    {
        ['transferencia' => $transferencia] = $this->prepararParaRechazo(20);

        $this->actingAsAdminA();
        app(\App\Http\Controllers\TransferenciaSugeridaController::class)->rechazar($transferencia);

        $mov = DB::table('movimientos_stock')
            ->where('producto_id', $this->productoId)
            ->where('sucursal_id', $this->origenId)
            ->where('tipo_movimiento', 'Transferencia Rechazada')
            ->latest()
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals(80.0, (float) $mov->cantidad_anterior);
        $this->assertEquals(20.0, (float) $mov->cantidad_movimiento);
        $this->assertEquals(100.0, (float) $mov->cantidad_actual);
    }

    public function test_rechazo_estado_rechazada(): void
    {
        ['transferencia' => $transferencia] = $this->prepararParaRechazo(10);

        $this->actingAsAdminA();
        app(\App\Http\Controllers\TransferenciaSugeridaController::class)->rechazar($transferencia);

        $this->assertDatabaseHas('transferencia_sugeridas', [
            'id' => $transferencia->id,
            'estado' => 'rechazada',
        ]);
    }

    public function test_rechazo_invariante_lotes_origen(): void
    {
        ['transferencia' => $transferencia] = $this->prepararParaRechazo(20);

        $this->actingAsAdminA();
        app(\App\Http\Controllers\TransferenciaSugeridaController::class)->rechazar($transferencia);

        $this->assertEquals($this->sumLotesOrigen(), (float) $this->getStockOrigen()->cantidad_fisica);
    }

    // ── FLUJO COMPLETO: despacho → recepción → verificar ──

    public function test_flujo_completo_despacho_recepcion(): void
    {
        $origenAntes = $this->getStockOrigen();
        $destinoAntes = $this->getStockDestino();

        $transferencia = TransferenciaSugerida::create([
            'origen_id' => $this->origenId,
            'destino_id' => $this->destinoId,
            'producto_id' => $this->productoId,
            'cantidad' => 20,
            'estado' => 'pendiente',
        ]);

        $this->actingAsAdminA();

        $this->post("/transferencias-sugeridas/{$transferencia->id}/despachar");

        $this->assertEquals(
            (float) $origenAntes->cantidad_fisica - 20,
            (float) $this->getStockOrigen()->cantidad_fisica
        );

        $t = TransferenciaSugerida::find($transferencia->id);
        $t->update(['lotes_despacho' => [
            ['lote_id' => 0, 'cantidad' => 20, 'fecha_vencimiento' => now()->addMonths(6)->format('Y-m-d')],
        ]]);

        $this->post("/transferencias-sugeridas/{$transferencia->id}/recibir");

        $this->assertEquals(
            (float) $destinoAntes->cantidad_fisica + 20,
            (float) $this->getStockDestino()->cantidad_fisica
        );

        $this->assertDatabaseHas('transferencia_sugeridas', [
            'id' => $transferencia->id,
            'estado' => 'recibida',
        ]);
    }
}
