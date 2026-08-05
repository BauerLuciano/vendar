<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\CuentaCorriente;
use App\Models\MovimientoCaja;
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
            'metodo_pago' => 'EFECTIVO',
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
            'metodo_pago' => 'EFECTIVO',
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
            'metodo_pago' => 'CUENTA_CORRIENTE',
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

    // P2.4.1 — Pago múltiple (Efectivo + Débito)
    public function test_admin_a_puede_crear_venta_pago_multiple(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/ventas', [
            'turno_caja_id' => 2,
            'items' => [
                ['id' => 1, 'cantidad' => 2, 'precio_venta' => 800, 'nombre' => 'Coca Cola 500ml'],
            ],
            'total' => 1600,
            'pagos' => [
                ['metodo_pago' => 'EFECTIVO', 'monto' => 1000],
                ['metodo_pago' => 'DEBITO', 'monto' => 600],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $response->assertSessionMissing('error');

        $venta = Venta::where('total', 1600)->where('metodo_pago', 'MULTIPLE')->first();
        $this->assertNotNull($venta);
        $this->assertNotNull($venta->pagos);
        $this->assertCount(2, $venta->pagos);
        $this->assertEquals(1000, $venta->pagos[0]['monto']);
        $this->assertEquals(600, $venta->pagos[1]['monto']);

        $movimientos = MovimientoCaja::where('turno_caja_id', 2)
            ->where('concepto', 'VENTA_MOSTRADOR')
            ->where('descripcion', 'like', "%#{$venta->id}%")
            ->get();
        $this->assertCount(2, $movimientos);
        $this->assertEquals(1000, $movimientos->where('metodo_pago', 'EFECTIVO')->first()->monto);
        $this->assertEquals(600, $movimientos->where('metodo_pago', 'DEBITO')->first()->monto);
    }

    // P2.4.2 — Pago múltiple con Cuenta Corriente + Efectivo
    public function test_admin_a_puede_crear_venta_fiado_y_efectivo(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/ventas', [
            'turno_caja_id' => 2,
            'consumidor_id' => $this->consumidorA->id,
            'items' => [
                ['id' => 5, 'cantidad' => 1, 'precio_venta' => 500, 'nombre' => 'Arroz 1kg'],
            ],
            'total' => 500,
            'pagos' => [
                ['metodo_pago' => 'CUENTA_CORRIENTE', 'monto' => 300],
                ['metodo_pago' => 'EFECTIVO', 'monto' => 200],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $response->assertSessionMissing('error');

        $venta = Venta::where('metodo_pago', 'MULTIPLE')->latest()->first();
        $this->assertNotNull($venta);
        $this->assertCount(2, $venta->pagos);
        $this->assertEquals('CUENTA_CORRIENTE', $venta->pagos[0]['metodo_pago']);
        $this->assertEquals('EFECTIVO', $venta->pagos[1]['metodo_pago']);

        $cuenta = CuentaCorriente::where('consumidor_id', $this->consumidorA->id)->first();
        $this->assertEquals(2300 + 300, (float) $cuenta->fresh()->saldo_deudor);

        $movimientoCaja = MovimientoCaja::where('turno_caja_id', 2)
            ->where('metodo_pago', 'EFECTIVO')
            ->where('descripcion', 'like', "%#{$venta->id}%")
            ->first();
        $this->assertNotNull($movimientoCaja);
        $this->assertEquals(200, $movimientoCaja->monto);
    }

    // P2.4.3 — Error si suma de pagos no coincide con total
    public function test_admin_a_no_puede_crear_venta_con_pagos_incorrectos(): void
    {
        $this->actingAsAdminA();

        $this->post('/ventas', [
            'turno_caja_id' => 2,
            'items' => [
                ['id' => 1, 'cantidad' => 2, 'precio_venta' => 800, 'nombre' => 'Coca Cola 500ml'],
            ],
            'total' => 1600,
            'pagos' => [
                ['metodo_pago' => 'EFECTIVO', 'monto' => 1000],
                ['metodo_pago' => 'DEBITO', 'monto' => 500],
            ],
        ])->assertSessionHasErrors();
    }

    // Auditoría F10 (H3.2): un producto de otro comercio no puede venderse,
    // aunque el stock negativo esté habilitado (evita usar su alícuota en el CAE).
    public function test_admin_a_no_puede_vender_producto_de_comercio_b(): void
    {
        Configuracion::where('clave', 'permitir_stock_negativo')
            ->update(['valor' => '1']);
        $this->actingAsAdminA();

        $this->post('/ventas', [
            'turno_caja_id' => 2,
            'items' => [
                ['id' => 11, 'cantidad' => 1, 'precio_venta' => 750, 'nombre' => 'Pepsi 500ml'],
            ],
            'total' => 750,
            'metodo_pago' => 'Efectivo',
        ])->assertSessionHasErrors();

        $this->assertDatabaseMissing('ventas', [
            'turno_caja_id' => 2,
            'total' => 750,
        ]);
    }
}
