<?php

namespace Tests\Feature;

use App\Enums\PaymentChannel;
use App\Models\PaymentMethodConfiguration;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_ct11_index_configuracion_no_muestra_pmc_de_otro_comercio(): void
    {
        PaymentMethodConfiguration::create([
            'comercio_id' => 1,
            'metodo_pago' => 'TRANSFERENCIA',
            'provider' => 'transfer-a',
            'channel' => PaymentChannel::MANUAL,
            'display_data' => [
                'alias' => 'alias.a',
                'titular' => 'Titular A',
            ],
            'enabled' => true,
        ]);

        $this->actingAsAdminB()
            ->get(route('configuracion.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Configuracion/Index')
                ->has('paymentMethodConfigs', 0));
    }

    public function test_ct12_eliminar_pmc_de_otro_comercio_da_403(): void
    {
        $pmc = PaymentMethodConfiguration::create([
            'comercio_id' => 1,
            'metodo_pago' => 'TRANSFERENCIA',
            'provider' => 'transfer-a',
            'channel' => PaymentChannel::MANUAL,
            'display_data' => [
                'alias' => 'alias.a',
                'titular' => 'Titular A',
            ],
            'enabled' => true,
        ]);

        $this->actingAsAdminB()
            ->delete(route('configuracion.metodo-pago.destroy', $pmc->id))
            ->assertForbidden();

        $this->assertDatabaseHas('payment_method_configurations', [
            'id' => $pmc->id,
            'comercio_id' => 1,
        ]);
    }

    public function test_ct13_store_pmc_crea_en_el_comercio_del_admin_autenticado(): void
    {
        $pmcA = PaymentMethodConfiguration::create([
            'comercio_id' => 1,
            'metodo_pago' => 'TRANSFERENCIA',
            'provider' => 'transfer-a',
            'channel' => PaymentChannel::MANUAL,
            'display_data' => [
                'alias' => 'alias.a',
                'titular' => 'Titular A',
            ],
            'enabled' => true,
        ]);

        $response = $this->actingAsAdminB()->post(route('configuracion.metodo-pago.store'), [
            'metodo_pago' => 'TRANSFERENCIA',
            'provider' => 'transfer-b',
            'display_data' => [
                'alias' => 'alias.b',
                'titular' => 'Titular B',
            ],
            'enabled' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payment_method_configurations', [
            'comercio_id' => 2,
            'metodo_pago' => 'TRANSFERENCIA',
            'provider' => 'transfer-b',
        ]);

        $pmcA->refresh();
        $this->assertSame('alias.a', $pmcA->display_data['alias']);
        $this->assertSame('Titular A', $pmcA->display_data['titular']);
    }
}
