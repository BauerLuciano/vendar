<?php

namespace Tests\Feature\Facturacion;

use App\Enums\VentaStatus;
use App\Facturacion\Application\Contracts\PadronResolver;
use App\Facturacion\Application\Contracts\WsfetResolver;
use App\Facturacion\Infrastructure\Arca\Exceptions\CredencialPlataformaNoConfiguradaException;
use App\Models\ComprobanteFiscal as ComprobanteFiscalModel;
use App\Models\ConfiguracionFiscalComercio;
use App\Models\Consumidor;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Http\UploadedFile;
use Tests\Support\FacturacionDomain\FakePadronConsulta;
use Tests\Support\FacturacionDomain\FakePadronResolver;
use Tests\Support\FacturacionDomain\FakeWsfet;
use Tests\Support\FacturacionDomain\FakeWsfetResolver;
use Tests\TestCaseMultiTenant;

class F5_IntegracionVentaControllerTest extends TestCaseMultiTenant
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

    public function test_store_emite_comprobante_para_venta_directa(): void
    {
        $this->configuracionLista();
        $this->actingAsAdminA();

        $response = $this->post('/ventas', [
            'turno_caja_id' => 2,
            'items' => [
                ['id' => 1, 'cantidad' => 1, 'precio_venta' => 800, 'nombre' => 'Coca Cola 500ml'],
            ],
            'total' => 800,
            'pagos' => [
                ['metodo_pago' => 'DEBITO', 'monto' => 800],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('error');

        $venta = Venta::latest('id')->first();
        $this->assertNotNull($venta);
        $this->assertEquals(VentaStatus::COMPLETED, $venta->estado);

        $this->assertSame(1, $this->wsfet->solicitudes);
        $this->assertDatabaseHas('comprobantes_fiscales', [
            'comercio_id' => 1,
            'venta_id' => $venta->id,
            'letra' => 'B',
            'estado' => 'emitido',
        ]);
    }

    public function test_store_sin_modulo_no_emite_y_venta_funciona_como_antes(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/ventas', [
            'turno_caja_id' => 2,
            'items' => [
                ['id' => 1, 'cantidad' => 1, 'precio_venta' => 800, 'nombre' => 'Coca Cola 500ml'],
            ],
            'total' => 800,
            'pagos' => [
                ['metodo_pago' => 'DEBITO', 'monto' => 800],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('error');
        $this->assertSame(0, $this->wsfet->solicitudes);
        $this->assertSame(0, ComprobanteFiscalModel::count());

        $this->assertDatabaseHas('ventas', [
            'estado' => 'Completada',
        ]);
    }

    public function test_store_revierte_la_venta_si_la_emision_falla(): void
    {
        $this->configuracionLista();
        $this->wsfet->excepcionAlSolicitar = new \RuntimeException('ARCA no responde.');
        $this->actingAsAdminA();

        $response = $this->post('/ventas', [
            'turno_caja_id' => 2,
            'items' => [
                ['id' => 1, 'cantidad' => 1, 'precio_venta' => 800, 'nombre' => 'Coca Cola 500ml'],
            ],
            'total' => 800,
            'pagos' => [
                ['metodo_pago' => 'DEBITO', 'monto' => 800],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');

        $this->assertSame(0, ComprobanteFiscalModel::count());
        $this->assertSame(3, Venta::count());
    }

    public function test_store_no_completa_venta_sin_credencial_de_padron(): void
    {
        $this->configuracionLista();
        $this->padron->excepcion = new CredencialPlataformaNoConfiguradaException('Sin credencial de plataforma.');
        $this->actingAsAdminA();

        $receptor = Consumidor::create([
            'comercio_id' => 1,
            'nombre' => 'Receptor',
            'apellido' => 'RI',
            'cuit' => '30500010912',
            'razon_social' => 'Cliente RI SA',
            'domicilio_fiscal' => 'Calle 123',
        ]);

        $response = $this->post('/ventas', [
            'turno_caja_id' => 2,
            'consumidor_id' => $receptor->id,
            'items' => [
                ['id' => 1, 'cantidad' => 1, 'precio_venta' => 800, 'nombre' => 'Coca Cola 500ml'],
            ],
            'total' => 800,
            'pagos' => [
                ['metodo_pago' => 'DEBITO', 'monto' => 800],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
        $this->assertStringContainsString(
            'credencial de padrón ARCA',
            $response->getSession()->get('errors')->first('error')
        );

        $this->assertSame(0, ComprobanteFiscalModel::count());
        $this->assertSame(3, Venta::count());
    }

    public function test_importacion_sin_columna_alicuota_usa_21_con_advertencia(): void
    {
        $this->actingAsAdminA();

        $csv = "nombre,codigo_barras,precio_costo,precio_venta,stock_minimo\nProducto Importado,77999900001,100,150,5\n";
        $archivo = UploadedFile::fake()->createWithContent('productos.csv', $csv);

        $response = $this->post('/productos/importar', ['archivo' => $archivo]);

        $response->assertOk();
        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertSame(1, $json['resumen']['creados']);
        $this->assertSame(1, $json['resumen']['warnings']);
        $this->assertStringContainsString('21%', $json['warnings'][0]['mensaje']);

        $this->assertDatabaseHas('productos', [
            'codigo_barras' => '77999900001',
            'alicuota_iva' => 21.0,
        ]);
    }

    public function test_importacion_con_columna_alicuota_respeta_el_valor(): void
    {
        $this->actingAsAdminA();

        $csv = "nombre,codigo_barras,precio_costo,precio_venta,stock_minimo,alicuota_iva\nProducto Con IVA,77999900002,100,150,5,10.5\n";
        $archivo = UploadedFile::fake()->createWithContent('productos.csv', $csv);

        $response = $this->post('/productos/importar', ['archivo' => $archivo]);

        $response->assertOk();
        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertSame(0, $json['resumen']['warnings']);

        $this->assertDatabaseHas('productos', [
            'codigo_barras' => '77999900002',
            'alicuota_iva' => 10.5,
        ]);
    }

    public function test_store_producto_requiere_alicuota(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/productos', [
            'nombre' => 'Producto Test',
            'codigo_barras' => '779999000001',
            'unidad_medida' => 'Unidad',
            'precio_costo' => 100,
            'precio_venta' => 150,
            'stock_minimo' => 5,
        ]);

        $response->assertSessionHasErrors('alicuota_iva');
    }

    public function test_store_producto_con_alicuota_persistido(): void
    {
        $this->actingAsAdminA();

        $response = $this->post('/productos', [
            'nombre' => 'Producto Con IVA',
            'codigo_barras' => '779999000002',
            'unidad_medida' => 'Unidad',
            'precio_costo' => 100,
            'precio_venta' => 150,
            'alicuota_iva' => 10.5,
            'stock_minimo' => 5,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('productos', [
            'codigo_barras' => '779999000002',
            'alicuota_iva' => 10.5,
        ]);
    }

    public function test_letra_esperada_devuelve_b_sin_receptor(): void
    {
        $this->configuracionLista();
        $this->actingAsAdminA();

        $response = $this->getJson('/pos/letra-esperada');

        $response->assertOk();
        $response->assertJson(['letra' => 'B']);
        $this->assertSame(0, $this->padron->llamadas);
    }

    public function test_letra_esperada_devuelve_a_con_receptor_ri(): void
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

        $response = $this->getJson('/pos/letra-esperada?consumidor_id='.$receptor->id);

        $response->assertOk();
        $response->assertJson(['letra' => 'A']);
        $this->assertSame(1, $this->padron->llamadas);
    }

    public function test_letra_esperada_devuelve_error_sin_credencial_de_padron(): void
    {
        $this->configuracionLista();
        $this->padron->excepcion = new CredencialPlataformaNoConfiguradaException('Sin credencial de plataforma.');
        $this->actingAsAdminA();

        $receptor = Consumidor::create([
            'comercio_id' => 1,
            'nombre' => 'Receptor',
            'apellido' => 'RI',
            'cuit' => '30500010912',
            'razon_social' => 'Cliente RI SA',
            'domicilio_fiscal' => 'Calle 123',
        ]);

        $response = $this->getJson('/pos/letra-esperada?consumidor_id='.$receptor->id);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
        $this->assertStringContainsString(
            'credencial de padrón ARCA',
            $response->json('error')
        );
    }

    public function test_crear_cliente_guarda_datos_fiscales_con_cuit_valido(): void
    {
        $this->actingAsAdminA();

        $response = $this->postJson('/pos/crear-cliente', [
            'nombre' => 'Cliente',
            'apellido' => 'Empresa',
            'cuit' => '30500010912',
            'razon_social' => 'Cliente RI SA',
            'domicilio_fiscal' => 'Calle 123',
        ]);

        $response->assertOk();
        $this->assertSame('30500010912', $response->json('cuit'));

        $this->assertDatabaseHas('consumidores', [
            'cuit' => '30500010912',
            'razon_social' => 'Cliente RI SA',
            'domicilio_fiscal' => 'Calle 123',
        ]);
    }

    public function test_crear_cliente_rechaza_cuit_invalido(): void
    {
        $this->actingAsAdminA();

        $response = $this->postJson('/pos/crear-cliente', [
            'nombre' => 'Cliente',
            'cuit' => '11111111111',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('cuit');

        $this->assertDatabaseMissing('consumidores', [
            'cuit' => '11111111111',
        ]);
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
