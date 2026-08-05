<?php

namespace Tests\Feature\Facturacion;

use App\Enums\VentaStatus;
use App\Facturacion\Application\Contracts\PadronResolver;
use App\Facturacion\Application\Contracts\WsfetResolver;
use App\Facturacion\Domain\Contracts\ComprobanteFiscalRepository;
use App\Models\ComprobanteFiscal as ComprobanteFiscalModel;
use App\Models\ConfiguracionFiscalComercio;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\Venta;
use Tests\Support\FacturacionDomain\FakePadronConsulta;
use Tests\Support\FacturacionDomain\FakePadronResolver;
use Tests\Support\FacturacionDomain\FakeWsfet;
use Tests\Support\FacturacionDomain\FakeWsfetResolver;
use Tests\TestCaseMultiTenant;

/**
 * F9 §11: Nota de Crédito desde el historial. La NC solo se emite sobre un
 * comprobante fiscal emitido y válido (invariante 2, §8); el historial expone
 * cada comprobante (factura y NC) con reimpresión/PDF por comprobante.
 */
class F9_NcDesdeHistorialTest extends TestCaseMultiTenant
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

    public function test_nc_parcial_se_reconstruye_con_su_propio_total(): void
    {
        $this->configuracionLista();
        $this->actingAsAdminA();

        $venta = $this->crearVentaFacturada(cantidad: 2, total: 1600);
        $detalle = DetalleVenta::where('venta_id', $venta->id)->firstOrFail();
        $original = ComprobanteFiscalModel::where('venta_id', $venta->id)->firstOrFail();

        $this->post("/ventas/{$venta->id}/devolver", [
            'items' => [['detalle_id' => $detalle->id, 'cantidad' => 1]],
        ])->assertRedirect();

        $ncModelo = ComprobanteFiscalModel::where('tipo', 'nota_credito')->firstOrFail();
        $this->assertEqualsWithDelta(800.0, (float) $ncModelo->total, 0.01);

        // F9: la reconstrucción desde el ledger usa el desglose persistido del
        // comprobante (§18.1) y no el total de la venta (que seguiría siendo 1600).
        $nc = app(ComprobanteFiscalRepository::class)
            ->buscarPorId((int) $ncModelo->id, 1);

        $this->assertNotNull($nc);
        $this->assertTrue($nc->esNotaCredito());
        $this->assertTrue($nc->esEmitido());
        $this->assertEquals($original->id, $nc->comprobanteOriginalId());
        $this->assertEquals($original->letra, $nc->letra()->value);
        $this->assertEqualsWithDelta(800.0, $nc->total()->valor(), 0.01);
        $this->assertSame($ncModelo->numero_completo, $nc->numeroCompleto());
    }

    public function test_reimprimir_nc_por_comprobante_id_no_altera_ledger(): void
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

        $this->get("/ventas/{$venta->id}/imprimir?comprobante_id={$nc->id}")
            ->assertOk()
            ->assertSee('NOTA DE CRÉDITO');

        $this->get("/ventas/{$venta->id}/imprimir?comprobante_id={$original->id}")
            ->assertOk()
            ->assertSee('FACTURA');

        // Solo lectura: la reimpresión no agrega ni modifica comprobantes.
        $this->assertSame(2, ComprobanteFiscalModel::count());
    }

    public function test_pdf_por_comprobante_descarga_documento_sin_alterar_ledger(): void
    {
        $this->configuracionLista();
        $this->actingAsAdminA();

        $venta = $this->crearVentaFacturada(cantidad: 2, total: 1600);
        $detalle = DetalleVenta::where('venta_id', $venta->id)->firstOrFail();

        $this->post("/ventas/{$venta->id}/devolver", [
            'items' => [['detalle_id' => $detalle->id, 'cantidad' => 1]],
        ])->assertRedirect();

        $nc = ComprobanteFiscalModel::where('tipo', 'nota_credito')->firstOrFail();

        $this->get("/ventas/{$venta->id}/pdf?comprobante_id={$nc->id}")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertSame(2, ComprobanteFiscalModel::count());
    }

    public function test_comprobante_de_otra_venta_es_rechazado(): void
    {
        $this->configuracionLista();
        $this->actingAsAdminA();

        $ventaUno = $this->crearVentaFacturada(cantidad: 1, total: 800);
        $ventaDos = $this->crearVentaFacturada(cantidad: 2, total: 1600);

        $comprobanteDos = ComprobanteFiscalModel::where('venta_id', $ventaDos->id)->firstOrFail();

        $this->get("/ventas/{$ventaUno->id}/imprimir?comprobante_id={$comprobanteDos->id}")
            ->assertNotFound();

        $this->get("/ventas/{$ventaUno->id}/pdf?comprobante_id={$comprobanteDos->id}")
            ->assertNotFound();
    }

    public function test_cancelar_venta_con_comprobante_pendiente_no_emite_nc(): void
    {
        $this->configuracionLista();
        $this->actingAsAdminA();

        // Comprobante que nunca llegó a emitirse (sin CAE): no es un comprobante
        // fiscal válido, por lo que la NC no debe generarse (invariante 2).
        $venta = $this->crearVentaConComprobantePendiente();

        $this->patch("/ventas/{$venta->id}/cancelar", ['motivo' => 'Anulación sin NC'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ComprobanteFiscalModel::where('tipo', 'nota_credito')->count());

        $venta->refresh();
        $this->assertEquals(VentaStatus::CANCELLED, $venta->estado);
    }

    public function test_historial_expone_factura_y_nc_de_la_venta(): void
    {
        $this->configuracionLista();
        $this->actingAsAdminA();

        $venta = $this->crearVentaFacturada(cantidad: 2, total: 1600);
        $original = ComprobanteFiscalModel::where('venta_id', $venta->id)->firstOrFail();
        $detalle = DetalleVenta::where('venta_id', $venta->id)->firstOrFail();

        $this->post("/ventas/{$venta->id}/devolver", [
            'items' => [['detalle_id' => $detalle->id, 'cantidad' => 1]],
        ])->assertRedirect();

        // Orden determinístico: la venta más reciente queda primera en el historial.
        $venta->forceFill(['created_at' => now()->addMinute()])->save();

        $this->get('/ventas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Ventas/Index')
                ->where('ventas.data.0.id', $venta->id)
                ->has('ventas.data.0.fiscal', 2)
                ->where('ventas.data.0.fiscal.0.es_nota_credito', false)
                ->where('ventas.data.0.fiscal.0.estado', 'emitido')
                ->where('ventas.data.0.fiscal.1.es_nota_credito', true)
                ->where('ventas.data.0.fiscal.1.estado', 'emitido')
                ->where('ventas.data.0.fiscal.1.comprobante_original_id', $original->id)
            );
    }

    private function crearVentaFacturada(int $cantidad = 1, int $total = 0): Venta
    {
        $precio = 800;
        $total = $total ?: $cantidad * $precio;

        $this->post('/ventas', [
            'turno_caja_id' => 2,
            'items' => [
                ['id' => 1, 'cantidad' => $cantidad, 'precio_venta' => $precio, 'nombre' => 'Coca Cola 500ml'],
            ],
            'total' => $total,
            'pagos' => [
                ['metodo_pago' => 'DEBITO', 'monto' => $total],
            ],
        ])->assertSessionHasNoErrors();

        return Venta::latest('id')->firstOrFail();
    }

    private function crearVentaConComprobantePendiente(): Venta
    {
        $venta = Venta::create([
            'turno_caja_id' => 2,
            'metodo_pago' => 'EFECTIVO',
            'total' => 800,
            'estado' => 'Completada',
        ]);

        DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_id' => 1,
            'cantidad' => 1,
            'precio_unitario' => 800,
            'subtotal' => 800,
            'alicuota_iva' => 21.0,
        ]);

        ComprobanteFiscalModel::create([
            'venta_id' => $venta->id,
            'comercio_id' => 1,
            'punto_venta' => 1,
            'tipo' => 'factura',
            'letra' => 'B',
            'numero' => 99,
            'neto' => 661.16,
            'iva' => 138.84,
            'total' => 800,
            'estado' => 'pendiente_emision',
        ]);

        return $venta;
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
