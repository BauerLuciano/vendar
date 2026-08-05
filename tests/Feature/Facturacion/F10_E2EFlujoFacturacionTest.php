<?php

namespace Tests\Feature\Facturacion;

use App\Facturacion\Application\Contracts\PadronResolver;
use App\Facturacion\Application\Contracts\WsfetResolver;
use App\Mail\TicketVenta;
use App\Models\ComprobanteFiscal as ComprobanteFiscalModel;
use App\Models\Configuracion;
use App\Models\ConfiguracionFiscalComercio;
use App\Models\Consumidor;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FacturacionDomain\FakePadronConsulta;
use Tests\Support\FacturacionDomain\FakePadronResolver;
use Tests\Support\FacturacionDomain\FakeWsfet;
use Tests\Support\FacturacionDomain\FakeWsfetResolver;
use Tests\TestCaseMultiTenant;

/**
 * F10.2: flujo End-to-End completo del módulo de facturación:
 * Venta → Emisión (CAE) → QR → Persistencia → Historial → Reimpresión → PDF →
 * Email → NC → Reimpresión NC → Historial final. Con QUEUE_CONNECTION=sync el
 * job de email se ejecuta inline al despacharse desde VentaController::store.
 */
class F10_E2EFlujoFacturacionTest extends TestCaseMultiTenant
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

    public function test_flujo_completo_venta_emision_historial_impresion_email_y_nc(): void
    {
        Mail::fake();
        $this->configuracionLista();
        Configuracion::create([
            'clave' => 'ticket_digital_auto_email',
            'valor' => '1',
            'tipo' => 'boolean',
            'grupo' => 'pos',
        ]);
        $this->actingAsAdminA();

        $consumidor = Consumidor::create([
            'comercio_id' => 1,
            'nombre' => 'Cliente',
            'apellido' => 'E2E',
            'email' => 'cliente-e2e@test.com',
            'razon_social' => 'Cliente E2E',
        ]);

        // ── 1. Venta → Emisión → CAE → QR → Persistencia ──────────────────
        $venta = $this->crearVentaFacturada(cantidad: 2, total: 1600, consumidorId: $consumidor->id);

        $original = ComprobanteFiscalModel::where('venta_id', $venta->id)->firstOrFail();
        $this->assertSame('emitido', $original->estado);
        $this->assertNotNull($original->cae);
        $this->assertNotNull($original->vencimiento_cae);
        $this->assertNotNull($original->qr, 'El QR ARCA debe persistirse al emitir con CAE (R.G. 4597/2019).');
        $this->assertSame('0001-00000001', $original->numero_completo);

        // ── 2. Email del ticket con PDF adjunto ────────────────────────────
        Mail::assertSent(TicketVenta::class, fn (TicketVenta $mail) => $mail->hasTo($consumidor->email));

        // ── 3. Historial expone la factura ──────────────────────────────────
        $this->get('/ventas?search='.$venta->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Ventas/Index')
                ->has('ventas.data.0.fiscal', 1)
                ->where('ventas.data.0.fiscal.0.es_nota_credito', false)
                ->where('ventas.data.0.fiscal.0.estado', 'emitido')
                ->where('ventas.data.0.fiscal.0.cae', $original->cae)
            );

        // ── 4. Reimpresión de la factura ───────────────────────────────────
        $this->get("/ventas/{$venta->id}/imprimir?comprobante_id={$original->id}")
            ->assertOk()
            ->assertSee('FACTURA')
            ->assertSee($original->cae);

        // ── 5. PDF de la factura ────────────────────────────────────────────
        $this->get("/ventas/{$venta->id}/pdf?comprobante_id={$original->id}")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertSame(1, ComprobanteFiscalModel::count());

        // ── 6. NC parcial → CAE → QR → Persistencia ────────────────────────
        $detalle = DetalleVenta::where('venta_id', $venta->id)->firstOrFail();

        $this->post("/ventas/{$venta->id}/devolver", [
            'items' => [['detalle_id' => $detalle->id, 'cantidad' => 1]],
        ])->assertRedirect();

        $nc = ComprobanteFiscalModel::where('tipo', 'nota_credito')->firstOrFail();
        $this->assertSame($original->id, $nc->comprobante_original_id);
        $this->assertSame('emitido', $nc->estado);
        $this->assertNotNull($nc->cae);
        $this->assertNotNull($nc->qr, 'La NC emitida debe persistir su QR ARCA.');
        $this->assertEqualsWithDelta(800.0, (float) $nc->total, 0.01);

        // ── 7. Reimpresión de la NC ────────────────────────────────────────
        $this->get("/ventas/{$venta->id}/imprimir?comprobante_id={$nc->id}")
            ->assertOk()
            ->assertSee('NOTA DE CRÉDITO')
            ->assertSee($nc->cae);

        // ── 8. PDF de la NC ─────────────────────────────────────────────────
        $this->get("/ventas/{$venta->id}/pdf?comprobante_id={$nc->id}")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertSame(2, ComprobanteFiscalModel::count());

        // ── 9. Historial final: factura + NC ────────────────────────────────
        $venta->forceFill(['created_at' => now()->addMinute()])->save();

        $this->get('/ventas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Ventas/Index')
                ->where('ventas.data.0.id', $venta->id)
                ->has('ventas.data.0.fiscal', 2)
                ->where('ventas.data.0.fiscal.0.es_nota_credito', false)
                ->where('ventas.data.0.fiscal.1.es_nota_credito', true)
                ->where('ventas.data.0.fiscal.1.comprobante_original_id', $original->id)
            );
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
