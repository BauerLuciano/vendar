<?php

namespace Tests\Feature\Facturacion;

use App\Facturacion\Application\EmisionVentaService;
use App\Facturacion\Application\Exceptions\EmisionVentaException;
use App\Facturacion\Domain\Calculators\DesgloseIvaCalculator;
use App\Facturacion\Domain\Rules\DeterminacionLetraRule;
use App\Facturacion\Domain\Rules\ElegibilidadEmisorRule;
use App\Facturacion\Domain\Services\NumeracionService;
use App\Facturacion\Domain\ValueObjects\LetraComprobante;
use App\Facturacion\Infrastructure\Arca\Exceptions\CredencialPlataformaNoConfiguradaException;
use App\Facturacion\Infrastructure\Arca\QrArcaPayloadBuilder;
use App\Facturacion\Infrastructure\Persistence\EloquentComprobanteFiscalRepository;
use App\Facturacion\Infrastructure\Persistence\EloquentConfiguracionFiscalRepository;
use App\Models\ComprobanteFiscal as ComprobanteFiscalModel;
use App\Models\ConfiguracionFiscalComercio;
use App\Models\Consumidor;
use App\Models\DetalleVenta;
use App\Models\Venta;
use Tests\Support\FacturacionDomain\FakePadronConsulta;
use Tests\Support\FacturacionDomain\FakePadronResolver;
use Tests\Support\FacturacionDomain\FakeWsfet;
use Tests\Support\FacturacionDomain\FakeWsfetResolver;
use Tests\TestCaseMultiTenant;

class F5_EmisionVentaServiceTest extends TestCaseMultiTenant
{
    private EmisionVentaService $service;

    private FakeWsfet $wsfet;

    private FakePadronConsulta $padron;

    private EloquentComprobanteFiscalRepository $repositorio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wsfet = new FakeWsfet;
        $this->padron = new FakePadronConsulta;

        $this->repositorio = new EloquentComprobanteFiscalRepository(new DesgloseIvaCalculator, new QrArcaPayloadBuilder);

        $this->service = new EmisionVentaService(
            new EloquentConfiguracionFiscalRepository,
            $this->repositorio,
            new ElegibilidadEmisorRule,
            new DeterminacionLetraRule,
            new NumeracionService($this->repositorio),
            new DesgloseIvaCalculator,
            new FakeWsfetResolver($this->wsfet),
            new FakePadronResolver($this->padron),
        );
    }

    public function test_sin_configuracion_no_emite(): void
    {
        $comprobante = $this->service->emitirSiCorresponde($this->venta());

        $this->assertNull($comprobante);
        $this->assertSame(0, $this->wsfet->solicitudes);
        $this->assertSame(0, ComprobanteFiscalModel::count());
    }

    public function test_modulo_no_listo_no_emite(): void
    {
        ConfiguracionFiscalComercio::create([
            'comercio_id' => 1,
            'cuit' => '20123456786',
            'razon_social' => 'Comercio RI',
            'condicion_fiscal' => 'responsable_inscripto',
            'estado_modulo' => 'datos_validados',
        ]);

        $comprobante = $this->service->emitirSiCorresponde($this->venta());

        $this->assertNull($comprobante);
        $this->assertSame(0, $this->wsfet->solicitudes);
    }

    public function test_emisor_no_elegible_no_emite(): void
    {
        $this->configuracionLista(['condicion_fiscal' => 'monotributo']);

        $comprobante = $this->service->emitirSiCorresponde($this->venta());

        $this->assertNull($comprobante);
        $this->assertSame(0, $this->wsfet->solicitudes);
        $this->assertSame(0, $this->padron->llamadas);
    }

    public function test_emite_factura_b_sin_cuit_de_receptor(): void
    {
        $this->configuracionLista();

        $comprobante = $this->service->emitirSiCorresponde($this->venta(consumidorId: $this->consumidorA->id));

        $this->assertNotNull($comprobante);
        $this->assertEquals(LetraComprobante::B, $comprobante->letra());
        $this->assertNotNull($comprobante->cae());
        $this->assertSame(1, $this->wsfet->solicitudes);
        $this->assertSame(0, $this->padron->llamadas);

        $this->assertDatabaseHas('comprobantes_fiscales', [
            'comercio_id' => 1,
            'venta_id' => $comprobante->ventaId(),
            'letra' => 'B',
            'numero' => 1,
            'estado' => 'emitido',
        ]);
    }

    public function test_recargo_agrega_detalle_extra_con_alicuota_configurable(): void
    {
        $this->configuracionLista(['alicuota_iva_recargo' => 10.5]);

        $comprobante = $this->service->emitirSiCorresponde($this->venta(recargo: 80.0));

        $this->assertNotNull($comprobante);
        $detalles = $comprobante->detalles();
        $this->assertCount(2, $detalles);

        $recargo = $detalles[1];
        $this->assertEquals(80.0, $recargo->precioUnitario()->valor());
        $this->assertEquals(10.5, $recargo->alicuota()->valor());
        $this->assertEquals(1290.0, $comprobante->total()->valor());
    }

    public function test_recargo_usa_alicuota_por_defecto_21(): void
    {
        $this->configuracionLista();

        $comprobante = $this->service->emitirSiCorresponde($this->venta(recargo: 80.0));

        $this->assertNotNull($comprobante);
        $this->assertEquals(21.0, $comprobante->detalles()[1]->alicuota()->valor());
        $this->assertEquals(1290.0, $comprobante->total()->valor());
    }

    public function test_detalle_sin_alicuota_lanza_excepcion(): void
    {
        $this->configuracionLista();

        $venta = Venta::create([
            'turno_caja_id' => 2,
            'metodo_pago' => 'EFECTIVO',
            'total' => 1210,
            'recargo_monto' => 0,
            'estado' => 'Completada',
        ]);

        DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_id' => 1,
            'cantidad' => 1,
            'precio_unitario' => 1210,
            'subtotal' => 1210,
            'alicuota_iva' => null,
        ]);

        try {
            $this->service->emitirSiCorresponde($venta);
            $this->fail('Se esperaba EmisionVentaException.');
        } catch (EmisionVentaException $e) {
            $this->assertStringContainsString('alícuota de IVA', $e->getMessage());
        }

        $this->assertSame(0, $this->wsfet->solicitudes);
        $this->assertSame(0, ComprobanteFiscalModel::count());
    }

    public function test_consumidor_con_cuit_emite_factura_a_contra_padron(): void
    {
        $this->configuracionLista();

        $receptor = Consumidor::create([
            'comercio_id' => 1,
            'nombre' => 'Receptor',
            'apellido' => 'RI',
            'cuit' => '30500010912',
            'razon_social' => 'Cliente RI SA',
            'domicilio_fiscal' => 'Calle 123',
        ]);

        $comprobante = $this->service->emitirSiCorresponde($this->venta(consumidorId: $receptor->id));

        $this->assertNotNull($comprobante);
        $this->assertEquals(LetraComprobante::A, $comprobante->letra());
        $this->assertSame(1, $this->padron->llamadas);
        $this->assertSame('30500010912', $this->padron->ultimoCuit?->valor());
    }

    public function test_sin_credencial_de_padron_no_completa_la_venta(): void
    {
        $this->configuracionLista();
        $this->padron->excepcion = new CredencialPlataformaNoConfiguradaException('Sin credencial de plataforma.');

        $receptor = Consumidor::create([
            'comercio_id' => 1,
            'nombre' => 'Receptor',
            'apellido' => 'RI',
            'cuit' => '30500010912',
            'razon_social' => 'Cliente RI SA',
            'domicilio_fiscal' => 'Calle 123',
        ]);

        try {
            $this->service->emitirSiCorresponde($this->venta(consumidorId: $receptor->id));
            $this->fail('Se esperaba EmisionVentaException.');
        } catch (EmisionVentaException $e) {
            $this->assertStringContainsString('credencial de padrón ARCA', $e->getMessage());
        }

        $this->assertSame(0, $this->wsfet->solicitudes);
        $this->assertSame(0, ComprobanteFiscalModel::count());
    }

    public function test_cuit_inactivo_en_padron_no_completa_la_venta(): void
    {
        $this->configuracionLista();
        $this->padron->respuesta = [
            'condicion_fiscal' => 'responsable_inscripto',
            'estado' => 'inactivo',
        ];

        $receptor = Consumidor::create([
            'comercio_id' => 1,
            'nombre' => 'Receptor',
            'apellido' => 'RI',
            'cuit' => '30500010912',
            'razon_social' => 'Cliente RI SA',
            'domicilio_fiscal' => 'Calle 123',
        ]);

        try {
            $this->service->emitirSiCorresponde($this->venta(consumidorId: $receptor->id));
            $this->fail('Se esperaba EmisionVentaException.');
        } catch (EmisionVentaException $e) {
            $this->assertStringContainsString('no está activo', $e->getMessage());
        }

        $this->assertSame(0, $this->wsfet->solicitudes);
    }

    public function test_letra_esperada_devuelve_b_sin_cuit(): void
    {
        $this->configuracionLista();

        $letra = $this->service->letraEsperada(1, $this->consumidorA);

        $this->assertEquals(LetraComprobante::B, $letra);
        $this->assertSame(0, $this->padron->llamadas);
    }

    public function test_letra_esperada_devuelve_a_con_receptor_ri(): void
    {
        $this->configuracionLista();

        $receptor = Consumidor::create([
            'comercio_id' => 1,
            'nombre' => 'Receptor',
            'apellido' => 'RI',
            'cuit' => '30500010912',
            'razon_social' => 'Cliente RI SA',
            'domicilio_fiscal' => 'Calle 123',
        ]);

        $letra = $this->service->letraEsperada(1, $receptor);

        $this->assertEquals(LetraComprobante::A, $letra);
    }

    public function test_letra_esperada_devuelve_null_sin_modulo(): void
    {
        $letra = $this->service->letraEsperada(1, $this->consumidorA);

        $this->assertNull($letra);
    }

    private function configuracionLista(array $extra = []): void
    {
        ConfiguracionFiscalComercio::create(array_merge([
            'comercio_id' => 1,
            'cuit' => '20123456786',
            'razon_social' => 'Comercio RI',
            'condicion_fiscal' => 'responsable_inscripto',
            'domicilio_fiscal' => 'Calle 1',
            'entorno' => 'homologacion',
            'punto_venta_activo' => 1,
            'estado_modulo' => 'listo_para_facturar',
        ], $extra));
    }

    private function venta(?int $consumidorId = null, ?float $recargo = null): Venta
    {
        $venta = Venta::create([
            'turno_caja_id' => 2,
            'consumidor_id' => $consumidorId,
            'metodo_pago' => 'EFECTIVO',
            'total' => 1210.0 + (float) $recargo,
            'recargo_monto' => $recargo ?? 0.0,
            'estado' => 'Completada',
        ]);

        DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_id' => 1,
            'cantidad' => 1,
            'precio_unitario' => 1210,
            'subtotal' => 1210,
            'alicuota_iva' => 21.0,
        ]);

        return $venta;
    }
}
