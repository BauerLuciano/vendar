<?php

namespace Tests\Feature;

use App\Models\DetalleVenta;
use App\Models\MovimientoCaja;
use App\Models\Venta;
use Tests\TestCaseMultiTenant;

class F4_CorreccionesBaseTest extends TestCaseMultiTenant
{
    // F4 §10: el recargo debe entrar al total, a los pagos y a los movimientos de caja
    public function test_recargo_entra_al_total_pagos_y_movimientos_de_caja(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/ventas', [
            'turno_caja_id' => 2,
            'items' => [
                ['id' => 1, 'cantidad' => 1, 'precio_venta' => 800, 'nombre' => 'Coca Cola 500ml'],
            ],
            'total' => 800,
            'pagos' => [
                [
                    'metodo_pago' => 'DEBITO',
                    'monto' => 800,
                    'tipo_tarjeta' => 'DEBITO',
                    'recargo_porcentaje' => 10,
                    'recargo_monto' => 80,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $response->assertSessionMissing('error');

        $venta = Venta::latest('id')->first();
        $this->assertNotNull($venta);
        $this->assertEquals(880.0, (float) $venta->total);
        $this->assertEquals(80.0, (float) $venta->recargo_monto);
        $this->assertEquals(880.0, (float) $venta->pagos[0]['monto']);

        $movimiento = MovimientoCaja::where('turno_caja_id', 2)
            ->where('concepto', 'VENTA_MOSTRADOR')
            ->where('metodo_pago', 'DEBITO')
            ->where('descripcion', 'like', "%#{$venta->id}%")
            ->first();
        $this->assertNotNull($movimiento);
        $this->assertEquals(880.0, (float) $movimiento->monto);
    }

    // F4 regresión: sin recargo, totales y movimientos intactos
    public function test_venta_sin_recargo_no_altera_total_ni_movimientos(): void
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

        $venta = Venta::where('total', 1600)->where('metodo_pago', 'MULTIPLE')->first();
        $this->assertNotNull($venta);
        $this->assertEquals(1000, (float) $venta->pagos[0]['monto']);
        $this->assertEquals(600, (float) $venta->pagos[1]['monto']);

        $movimientoEfectivo = MovimientoCaja::where('turno_caja_id', 2)
            ->where('metodo_pago', 'EFECTIVO')
            ->where('descripcion', 'like', "%#{$venta->id}%")
            ->first();
        $movimientoDebito = MovimientoCaja::where('turno_caja_id', 2)
            ->where('metodo_pago', 'DEBITO')
            ->where('descripcion', 'like', "%#{$venta->id}%")
            ->first();
        $this->assertEquals(1000, (float) $movimientoEfectivo->monto);
        $this->assertEquals(600, (float) $movimientoDebito->monto);
    }

    // F4: cancelar() debe persistir cantidad_devuelta en cada detalle
    public function test_cancelar_persiste_cantidad_devuelta(): void
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

        $venta = Venta::latest('id')->first();
        $this->assertNotNull($venta);

        $this->patch("/ventas/{$venta->id}/cancelar", ['motivo' => 'Test F4 anulación'])->assertRedirect();

        $detalle = DetalleVenta::where('venta_id', $venta->id)->first();
        $this->assertNotNull($detalle);
        $this->assertEquals(2, (float) $detalle->cantidad_devuelta);

        $this->assertDatabaseHas('ventas', [
            'id' => $venta->id,
            'estado' => 'Cancelada',
        ]);
    }

    // F4: solo usuarios con permiso "anular ventas" pueden anular/devolver
    public function test_cajero_no_puede_cancelar_ni_devolver(): void
    {
        $this->actingAsUserA();

        $this->patch('/ventas/1/cancelar', ['motivo' => 'Test hack'])->assertForbidden();
        $this->post('/ventas/1/devolver', [
            'items' => [['detalle_id' => 1, 'cantidad' => 1]],
        ])->assertForbidden();
    }

    public function test_admin_con_permiso_puede_cancelar(): void
    {
        $this->actingAsAdminA();

        $this->patch('/ventas/1/cancelar', ['motivo' => 'Test F4 permiso'])->assertRedirect();

        $this->assertDatabaseHas('ventas', [
            'id' => 1,
            'estado' => 'Cancelada',
        ]);
    }

    // F4: validación sintáctica de CUIT del consumidor
    public function test_consumidor_con_cuit_invalido_rechazado(): void
    {
        $this->actingAsAdminA();

        $this->post('/clientes', [
            'nombre' => 'Juan',
            'apellido' => 'Perez',
            'cuit' => '20123456785',
            'limite_cuenta_corriente' => 0,
        ])->assertSessionHasErrors('cuit');
    }

    public function test_consumidor_con_cuit_valido_persistido(): void
    {
        $this->actingAsAdminA();

        $this->post('/clientes', [
            'nombre' => 'Juan',
            'apellido' => 'Perez',
            'cuit' => '20123456786',
            'tipo_documento' => 'CUIT',
            'razon_social' => 'Perez Juan SA',
            'domicilio_fiscal' => 'Av. Siempreviva 742',
            'limite_cuenta_corriente' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('consumidores', [
            'nombre' => 'Juan',
            'apellido' => 'Perez',
            'cuit' => '20123456786',
            'tipo_documento' => 'CUIT',
        ]);
    }
}
