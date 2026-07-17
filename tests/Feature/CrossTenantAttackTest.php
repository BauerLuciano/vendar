<?php

namespace Tests\Feature;

use Tests\TestCaseMultiTenant;

class CrossTenantAttackTest extends TestCaseMultiTenant
{
    public function test_ct1_update_producto_de_otro_comercio_da_403(): void
    {
        $this->actingAsAdminA();

        $this->post('/productos/11', [
            'nombre' => 'Hackeado',
            'codigo_barras' => '99999999',
            'categoria_id' => 1,
            'marca_id' => 1,
            'proveedor_id' => 1,
            'unidad_medida' => 'Unidad',
            'precio_costo' => 1,
            'precio_venta' => 1,
            'stock_minimo' => 1,
        ])->assertForbidden();
    }

    public function test_ct2_ajuste_stock_producto_de_otro_comercio_da_403(): void
    {
        $this->actingAsAdminA();

        $this->post('/productos/11/ajuste-stock', [
            'sucursal_id' => 1,
            'tipo_ajuste' => 'Sumar',
            'cantidad' => 10,
            'motivo' => 'Test',
        ])->assertForbidden();
    }

    public function test_ct3_status_producto_de_otro_comercio_da_403(): void
    {
        $this->actingAsAdminA();

        $this->patch('/productos/11/status')->assertForbidden();
    }

    public function test_ct4_cancelar_venta_de_otro_comercio_da_403(): void
    {
        $this->actingAsAdminA();

        $this->patch('/ventas/3/cancelar', [
            'motivo' => 'Test cross-tenant',
        ])->assertForbidden();
    }

    public function test_ct5_cobrar_deuda_consumidor_de_otro_comercio_da_403(): void
    {
        $this->actingAsAdminA();

        $this->post('/consumidores/6/cobrar', [
            'pagos' => [['monto' => 100, 'metodo_pago' => 'Efectivo']],
        ])->assertForbidden();
    }

    public function test_ct6_estado_cuenta_consumidor_de_otro_comercio_da_403(): void
    {
        $this->actingAsAdminA();

        $this->get('/consumidores/6/cuenta')->assertForbidden();
    }

    public function test_ct7_actualizar_usuario_de_otro_comercio_da_403(): void
    {
        $this->actingAsAdminA();

        $this->put('/usuarios/'.$this->userB->id, [
            'name' => 'Hackeado',
            'email' => 'hackeado@test.com',
            'branch_id' => 1,
            'rol' => 'Cajero',
        ])->assertForbidden();
    }

    public function test_ct8_eliminar_usuario_de_otro_comercio_da_403(): void
    {
        $this->actingAsAdminA();

        $this->delete('/usuarios/'.$this->userB->id)->assertForbidden();
    }

    public function test_ct9_despachar_transferencia_de_otro_comercio_da_error(): void
    {
        $this->actingAsAdminA();

        $this->post('/transferencias-sugeridas/2/despachar')
            ->assertSessionHas('error');
    }

    public function test_ct10_cerrar_turno_de_otro_comercio_da_404(): void
    {
        $this->actingAsAdminA();

        $this->post('/api/sesiones-caja/3/cerrar', [
            'saldo_final_efectivo_real' => 0,
            'saldo_final_mp_real' => 0,
            'saldo_final_transf_real' => 0,
            'observaciones' => 'Test',
        ])->assertNotFound();
    }
}
