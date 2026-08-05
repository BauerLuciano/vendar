<?php

namespace Tests\Feature\Facturacion;

use App\Enums\VentaStatus;
use App\Facturacion\Application\Contracts\ConectividadResolver;
use App\Facturacion\Application\Contracts\PadronResolver;
use App\Facturacion\Application\Contracts\WsfetResolver;
use App\Models\CertificadoFiscal;
use App\Models\ComprobanteFiscal as ComprobanteFiscalModel;
use App\Models\ConfiguracionFiscalComercio;
use App\Models\DetalleVenta;
use App\Models\PendienteNc;
use App\Models\Producto;
use App\Models\Venta;
use Tests\Support\FacturacionDomain\FakeConectividadResolver;
use Tests\Support\FacturacionDomain\FakePadronConsulta;
use Tests\Support\FacturacionDomain\FakePadronResolver;
use Tests\Support\FacturacionDomain\FakeWsfet;
use Tests\Support\FacturacionDomain\FakeWsfetResolver;
use Tests\TestCaseMultiTenant;

class F8_DiagnosticoFiscalTest extends TestCaseMultiTenant
{
    private FakeWsfet $wsfet;

    private FakePadronConsulta $padron;

    private FakeConectividadResolver $conectividad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wsfet = new FakeWsfet;
        $this->padron = new FakePadronConsulta;
        $this->conectividad = new FakeConectividadResolver;

        $this->app->instance(WsfetResolver::class, new FakeWsfetResolver($this->wsfet));
        $this->app->instance(PadronResolver::class, new FakePadronResolver($this->padron));
        $this->app->instance(ConectividadResolver::class, $this->conectividad);

        Producto::find(1)?->update(['alicuota_iva' => 21.0]);
    }

    public function test_diagnostico_muestra_estado_inicial_sin_datos(): void
    {
        $this->actingAsAdminA()
            ->get(route('configuracion.fiscal.diagnostico'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facturacion/Diagnostico')
                ->where('diagnostico.indicador', 'incompleto')
                ->where('diagnostico.estado_modulo', 'sin_datos')
                ->has('diagnostico.items', 6)
                ->where('diagnostico.items.0.ok', false)
                ->where('diagnostico.items.1.ok', false)
                ->where('diagnostico.items.2.ok', false)
                ->where('diagnostico.items.3.ok', false)
                ->where('diagnostico.items.4.ok', null)
                ->where('diagnostico.items.5.ok', false)
                ->has('pendientes', 0)
            );
    }

    public function test_diagnostico_listo_para_facturar_muestra_indicador_verde(): void
    {
        $this->configuracionLista();

        $this->actingAsAdminA()
            ->get(route('configuracion.fiscal.diagnostico'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facturacion/Diagnostico')
                ->where('diagnostico.indicador', 'listo')
                ->where('diagnostico.estado_modulo', 'listo_para_facturar')
                ->where('diagnostico.items.0.ok', true)
                ->where('diagnostico.items.1.ok', true)
                ->where('diagnostico.items.2.ok', true)
                ->where('diagnostico.items.3.ok', true)
                ->where('diagnostico.items.5.ok', true)
            );
    }

    public function test_diagnostico_no_soportado_muestra_indicador_rojo(): void
    {
        ConfiguracionFiscalComercio::create([
            'comercio_id' => 1,
            'cuit' => '20123456786',
            'razon_social' => 'Monotributista',
            'condicion_fiscal' => 'monotributo',
            'entorno' => 'produccion',
            'estado_modulo' => 'no_soportado',
        ]);

        $this->actingAsAdminA()
            ->get(route('configuracion.fiscal.diagnostico'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facturacion/Diagnostico')
                ->where('diagnostico.indicador', 'no_posible')
                ->where('diagnostico.items.0.ok', false)
            );
    }

    public function test_probar_conexion_desde_diagnostico_ejecuta_la_suite(): void
    {
        $this->configuracionLista();

        $this->actingAsAdminA()
            ->from(route('configuracion.fiscal.diagnostico'))
            ->post(route('configuracion.fiscal.diagnostico.probar-conexion'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->conectividad->llamadas);
        $this->assertNotNull(session('facturacion.resultado_conexion'));
    }

    public function test_cancelar_con_nc_fallida_registra_pendiente(): void
    {
        $this->configuracionLista();
        $this->actingAsAdminA();

        $venta = $this->crearVentaFacturada();
        $this->wsfet->excepcionAlSolicitar = new \RuntimeException('ARCA no responde la NC.');

        $this->patch("/ventas/{$venta->id}/cancelar", ['motivo' => 'Anulación con NC fallida'])
            ->assertSessionHasErrors('error');

        $venta->refresh();
        $this->assertEquals(VentaStatus::COMPLETED, $venta->estado);

        $this->assertDatabaseHas('nc_pendientes', [
            'venta_id' => $venta->id,
            'tipo_operacion' => 'anulacion',
            'estado' => 'pendiente',
            'intentos' => 1,
        ]);
    }

    public function test_diagnostico_lista_los_pendientes_registrados(): void
    {
        $this->configuracionLista();
        $this->actingAsAdminA();

        $venta = $this->crearVentaFacturada();
        $this->wsfet->excepcionAlSolicitar = new \RuntimeException('ARCA no responde la NC.');

        $this->patch("/ventas/{$venta->id}/cancelar", ['motivo' => 'Anulación con NC fallida']);

        $this->get(route('configuracion.fiscal.diagnostico'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facturacion/Diagnostico')
                ->has('pendientes', 1)
                ->where('pendientes.0.venta_id', $venta->id)
                ->where('pendientes.0.tipo_operacion', 'anulacion')
                ->where('pendientes.0.intentos', 1)
                ->where('pendientes.0.motivo_fallo', 'ARCA no responde la NC.')
            );
    }

    public function test_reintento_anulacion_completa_resuelve_pendiente(): void
    {
        $this->configuracionLista();
        $this->actingAsAdminA();

        $venta = $this->crearVentaFacturada();
        $this->wsfet->excepcionAlSolicitar = new \RuntimeException('ARCA no responde la NC.');

        $this->patch("/ventas/{$venta->id}/cancelar", ['motivo' => 'Anulación con NC fallida']);

        $pendienteId = PendienteNc::where('venta_id', $venta->id)->value('id');

        $this->wsfet->excepcionAlSolicitar = null;

        $this->post(route('configuracion.fiscal.diagnostico.reintentar', ['pendiente' => $pendienteId]))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $venta->refresh();
        $this->assertEquals(VentaStatus::CANCELLED, $venta->estado);

        $this->assertSame(1, ComprobanteFiscalModel::where('tipo', 'nota_credito')->count());

        $this->assertDatabaseHas('nc_pendientes', [
            'id' => $pendienteId,
            'estado' => 'resuelto',
        ]);
    }

    public function test_reintento_anulacion_fallido_incrementa_intentos(): void
    {
        $this->configuracionLista();
        $this->actingAsAdminA();

        $venta = $this->crearVentaFacturada();
        $this->wsfet->excepcionAlSolicitar = new \RuntimeException('ARCA no responde la NC.');

        $this->patch("/ventas/{$venta->id}/cancelar", ['motivo' => 'Anulación con NC fallida']);

        $pendienteId = PendienteNc::where('venta_id', $venta->id)->value('id');

        $this->post(route('configuracion.fiscal.diagnostico.reintentar', ['pendiente' => $pendienteId]))
            ->assertRedirect()
            ->assertSessionHasErrors('reintento');

        $venta->refresh();
        $this->assertEquals(VentaStatus::COMPLETED, $venta->estado);

        $this->assertDatabaseHas('nc_pendientes', [
            'id' => $pendienteId,
            'estado' => 'pendiente',
            'intentos' => 2,
        ]);

        $this->assertSame(0, ComprobanteFiscalModel::where('tipo', 'nota_credito')->count());
    }

    public function test_reintento_devolucion_completa_resuelve_pendiente(): void
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

        $pendienteId = PendienteNc::where('venta_id', $venta->id)->value('id');

        $this->wsfet->excepcionAlSolicitar = null;

        $this->post(route('configuracion.fiscal.diagnostico.reintentar', ['pendiente' => $pendienteId]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $detalle->refresh();
        $this->assertEquals(1, (float) $detalle->cantidad_devuelta);

        $nc = ComprobanteFiscalModel::where('tipo', 'nota_credito')->firstOrFail();
        $this->assertEqualsWithDelta(800.0, (float) $nc->total, 0.01);

        $this->assertDatabaseHas('nc_pendientes', [
            'id' => $pendienteId,
            'estado' => 'resuelto',
        ]);
    }

    public function test_multi_tenant_no_permite_reintentar_pendiente_de_otro_comercio(): void
    {
        $this->configuracionLista();
        $this->actingAsAdminA();

        $venta = $this->crearVentaFacturada();
        $this->wsfet->excepcionAlSolicitar = new \RuntimeException('ARCA no responde la NC.');

        $this->patch("/ventas/{$venta->id}/cancelar", ['motivo' => 'Anulación con NC fallida']);

        $pendienteId = PendienteNc::where('venta_id', $venta->id)->value('id');

        $this->wsfet->excepcionAlSolicitar = null;

        $this->actingAsAdminB()
            ->post(route('configuracion.fiscal.diagnostico.reintentar', ['pendiente' => $pendienteId]))
            ->assertRedirect()
            ->assertSessionHasErrors('reintento');

        $venta->refresh();
        $this->assertEquals(VentaStatus::COMPLETED, $venta->estado);

        $this->assertDatabaseHas('nc_pendientes', [
            'id' => $pendienteId,
            'estado' => 'pendiente',
            'intentos' => 1,
        ]);
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

    private function configuracionLista(): void
    {
        $certificado = CertificadoFiscal::create([
            'comercio_id' => 1,
            'entorno' => 'homologacion',
            'archivo_pfx' => 'datos-encriptados',
            'password_pfx' => 'encriptado',
            'distinguished_name' => '/CN=CUIT 20123456786',
            'numero_serie' => '1234',
            'vigencia_desde' => now()->subDay(),
            'vigencia_hasta' => now()->addYear(),
        ]);

        ConfiguracionFiscalComercio::create([
            'comercio_id' => 1,
            'cuit' => '20123456786',
            'razon_social' => 'Comercio RI',
            'condicion_fiscal' => 'responsable_inscripto',
            'domicilio_fiscal' => 'Calle 1',
            'entorno' => 'homologacion',
            'punto_venta_activo' => 1,
            'certificado_id' => $certificado->id,
            'estado_modulo' => 'listo_para_facturar',
        ]);
    }
}
