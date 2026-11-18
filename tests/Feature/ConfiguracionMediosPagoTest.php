<?php

namespace Tests\Feature;

use App\Enums\PaymentChannel;
use App\Models\PaymentMethodConfiguration;
use Tests\TestCaseMultiTenant;

class ConfiguracionMediosPagoTest extends TestCaseMultiTenant
{
    public function test_admin_a_puede_guardar_un_medio_de_pago_valido(): void
    {
        $response = $this->actingAsAdminA()->post(route('configuracion.metodo-pago.store'), [
            'metodo_pago' => 'TRANSFERENCIA',
            'provider' => '',
            'display_data' => [
                'alias' => 'mi.alias',
                'cvu' => '0000003100000000000000',
                'cbu' => '',
                'banco' => 'Banco Nación',
                'titular' => 'Juan Pérez',
            ],
            'enabled' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $pmc = PaymentMethodConfiguration::where('comercio_id', 1)
            ->where('metodo_pago', 'TRANSFERENCIA')
            ->first();

        $this->assertNotNull($pmc);
        $this->assertSame(PaymentChannel::MANUAL, $pmc->channel);
        $this->assertTrue($pmc->enabled);
        $this->assertSame('mi.alias', $pmc->display_data['alias']);
        $this->assertSame('Juan Pérez', $pmc->display_data['titular']);
    }

    public function test_transferencia_sin_datos_de_display_se_guarda_sin_errores(): void
    {
        $response = $this->actingAsAdminA()->post(route('configuracion.metodo-pago.store'), [
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [],
            'enabled' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payment_method_configurations', [
            'comercio_id' => 1,
            'metodo_pago' => 'TRANSFERENCIA',
            'enabled' => true,
        ]);
    }

    public function test_metodo_de_pago_es_obligatorio_y_no_persiste(): void
    {
        $response = $this->actingAsAdminA()->post(route('configuracion.metodo-pago.store'), [
            'display_data' => [],
            'enabled' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'metodo_pago' => 'Seleccioná un método de pago.',
        ]);

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_medio_de_pago_invalido_devuelve_errores_de_campos_y_no_persiste(): void
    {
        $response = $this->actingAsAdminA()->post(route('configuracion.metodo-pago.store'), [
            'metodo_pago' => 'NO_EXISTE',
            'display_data' => [
                'cvu' => 'no-numerico',
                'cbu' => 'no-numerico',
                'banco' => '123',
                'titular' => '123',
            ],
            'enabled' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'metodo_pago' => 'Seleccioná un método de pago válido.',
            'display_data.cvu' => 'El CVU solo puede contener números.',
            'display_data.cbu' => 'El CBU solo puede contener números.',
            'display_data.banco' => 'El banco solo puede contener letras.',
            'display_data.titular' => 'El nombre del titular solo puede contener letras.',
        ]);

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_display_data_invalido_devuelve_error_general_y_no_persiste(): void
    {
        $response = $this->actingAsAdminA()->post(route('configuracion.metodo-pago.store'), [
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => 'no-es-un-array',
            'enabled' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'display_data' => 'Los datos para mostrar no tienen un formato válido.',
        ]);

        $this->assertDatabaseCount('payment_method_configurations', 0);
    }

    public function test_cajero_no_puede_guardar_medios_de_pago(): void
    {
        $response = $this->actingAsUserA()->post(route('configuracion.metodo-pago.store'), [
            'metodo_pago' => 'TRANSFERENCIA',
            'display_data' => [],
            'enabled' => true,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('payment_method_configurations', 0);
    }
}
