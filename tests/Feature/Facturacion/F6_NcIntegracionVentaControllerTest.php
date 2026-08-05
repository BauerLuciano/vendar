<?php

namespace Tests\Feature\Facturacion;

use App\Enums\VentaStatus;
use App\Facturacion\Application\Contracts\PadronResolver;
use App\Facturacion\Application\Contracts\WsfetResolver;
use App\Models\ComprobanteFiscal as ComprobanteFiscalModel;
use App\Models\ConfiguracionFiscalComercio;
use App\Models\Consumidor;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\Venta;
use Tests\Support\FacturacionDomain\FakePadronConsulta;
use Tests\Support\FacturacionDomain\FakePadronResolver;
use Tests\Support\FacturacionDomain\FakeWsfet;
use Tests\Support\FacturacionDomain\FakeWsfetResolver;
use Tests\TestCaseMultiTenant;

class F6_NcIntegracionVentaControllerTest extends TestCaseMultiTenant
{
    private FakeWsfet $wsfet;

    private FakePadronConsulta $padron;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wsfet = new FakeWsfet;
        $this->padron = new FakePadronConsulta;

        $this->app->instance(WsfetResolver::class, new FakeWsfetResolver($this->wsfet));
        $this->app->instance(PadronResolver::class, new FakePadronResolver($this->padron));

        Producto::find(1)?->update(['alicuota_iva' => 21.0]);
    }

    public function test_cancelar_venta_facturada_emite_nc_total(): void
    {
        $this->configuracionLista();
        $this->actingAsAdminA();

        $venta = $this->crearVentaFacturada();
        $this->assertSame(1, $this->wsfet->solicitudes);

        $original = ComprobanteFiscalModel::where('venta_id', $venta->id)->firstOrFail();

        $this->patch("/ventas/{$venta->id}/cancelar", ['motivo' => 'Anulación F6'])
            ->assertRedirect();

        $nc = ComprobanteFiscalModel::where('tipo', 'nota_credito')->firstOrFail();
        $this->assertSame($original->id, $nc->comprobante_original_id);
        $this->assertSame($original->letra, $nc->letra);
        $this->assertEqualsWithDelta((float) $original->total, (float) $nc->total, 0.01);

        $venta->refresh();
        $this->assertEquals(VentaStatus::CANCELLED, $venta->estado);
    }

    public function test_cancelar_venta_sin_factura_no_emite_nc(): void
    {
        $this->actingAsAdminA();

        $this->patch('/ventas/1/cancelar', ['motivo' => 'Anulación sin factura'])
            ->assertRedirect();

        $this->assertSame(0, ComprobanteFiscalModel::count());
        $this->assertDatabaseHas('ventas', ['id' => 1, 'estado' => 'Cancelada']);
    }

    public function test_cancelar_no_se_concreta_si_la_nc_falla(): void
    {
        $this->configuracionLista();
        $this->actingAsAdminA();

        $venta = $this->crearVentaFacturada();
        $this->wsfet->excepcionAlSolicitar = new \RuntimeException('ARCA no responde la NC.');

        $this->patch("/ventas/{$venta->id}/cancelar", ['motivo' => 'Anulación con NC fallida'])
            ->assertSessionHasErrors('error');

        $venta->refresh();
        $this->assertEquals(VentaStatus::COMPLETED, $venta->estado);
        $this->assertSame(1, ComprobanteFiscalModel::count());
        $this->assertSame(2, $this->wsfet->solicitudes);
    }

    public function test_devolver_venta_facturada_emite_nc_parcial(): void
    {
        $this->configuracionLista();
        $this->actingAsAdminA();

        $venta = $this->crearVentaFacturada(cantidad: 2, total: 1600);

        $detalle = DetalleVenta::where('venta_id', $venta->id)->firstOrFail();
        $original = ComprobanteFiscalModel::where('venta_id', $venta->id)->firstOrFail();

        $this->post("/ventas/{$venta->id}/devolver", [
            'items' => [['detalle_id' => $detalle->id, 'cantidad' => 1]],
        ])->assertRedirect();

        $nc = ComprobanteFiscalModel::where('tipo', 'nota_credito')->firstOrFail();
        $this->assertSame($original->id, $nc->comprobante_original_id);
        $this->assertSame($original->letra, $nc->letra);
        $this->assertEqualsWithDelta(800.0, (float) $nc->total, 0.01);

        $detalle->refresh();
        $this->assertEquals(1, (float) $detalle->cantidad_devuelta);
    }

    public function test_devolver_no_se_concreta_si_la_nc_falla(): void
    {
        $this->configuracionLista();
        $this->actingAsAdminA();

        $venta = $this->crearVentaFacturada(cantidad: 2, total: 1600);
        $this->wsfet->excepcionAlSolicitar = new \RuntimeException('ARCA no responde la NC.');

        $detalle = DetalleVenta::where('venta_id', $venta->id)->firstOrFail();

        $this->post("/ventas/{$venta->id}/devolver", [
            'items' => [['detalle_id' => $detalle->id, 'cantidad' => 1]],
        ])->assertSessionHasErrors('error');

        $detalle->refresh();
        $this->assertEquals(0, (float) $detalle->cantidad_devuelta);

        $venta->refresh();
        $this->assertEquals(VentaStatus::COMPLETED, $venta->estado);
        $this->assertSame(1, ComprobanteFiscalModel::count());
    }

    public function test_cancelar_factura_a_emite_nc_a(): void
    {
        $this->configuracionLista();
        $this->actingAsAdminA();

        $receptor = Consumidor::create([
            'comercio_id' => 1,
            'nombre' => 'Receptor',
            'apellido' => 'RI',
            'cuit' => '30500010912',
            'razon_social' => 'Cliente RI SA',
            'domicilio_fiscal' => 'Calle 123',
        ]);

        $venta = $this->crearVentaFacturada(consumidorId: $receptor->id);
        $this->assertSame(1, $this->padron->llamadas);

        $original = ComprobanteFiscalModel::where('venta_id', $venta->id)->firstOrFail();
        $this->assertSame('A', $original->letra);

        $this->patch("/ventas/{$venta->id}/cancelar", ['motivo' => 'Anulación NC A'])
            ->assertRedirect();

        $nc = ComprobanteFiscalModel::where('tipo', 'nota_credito')->firstOrFail();
        $this->assertSame('A', $nc->letra);
        $this->assertSame($original->id, $nc->comprobante_original_id);
        $this->assertSame(2, $this->padron->llamadas);
    }

    private function crearVentaFacturada(int $cantidad = 1, int $total = 0, ?int $consumidorId = null): Venta
    {
        $precio = 800;
        $total = $total ?: $cantidad * $precio;

        $data = [
            'turno_caja_id' => 2,
            'items' => [
                ['id' => 1, 'cantidad' => $cantidad, 'precio_venta' => $precio, 'nombre' => 'Coca Cola 500ml'],
            ],
            'total' => $total,
            'pagos' => [
                ['metodo_pago' => 'DEBITO', 'monto' => $total],
            ],
        ];

        if ($consumidorId !== null) {
            $data['consumidor_id'] = $consumidorId;
        }

        $this->post('/ventas', $data)->assertSessionHasNoErrors();

        return Venta::latest('id')->firstOrFail();
    }

    private function configuracionLista(): void
    {
        ConfiguracionFiscalComercio::create([
            'comercio_id' => 1,
            'cuit' => '20123456786',
            'razon_social' => 'Comercio RI',
            'condicion_fiscal' => 'responsable_inscripto',
            'domicilio_fiscal' => 'Calle 1',
            'entorno' => 'homologacion',
            'punto_venta_activo' => 1,
            'estado_modulo' => 'listo_para_facturar',
        ]);
    }
}
