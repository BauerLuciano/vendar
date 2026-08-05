<?php

namespace Tests\Feature\Facturacion;

use App\Facturacion\Domain\Calculators\DesgloseIvaCalculator;
use App\Facturacion\Domain\Entities\Emisor;
use App\Facturacion\Domain\Entities\PuntoVenta;
use App\Facturacion\Domain\Entities\Receptor;
use App\Facturacion\Domain\Exceptions\ComercioNoListoException;
use App\Facturacion\Domain\Rules\DeterminacionLetraRule;
use App\Facturacion\Domain\Rules\ElegibilidadEmisorRule;
use App\Facturacion\Domain\Services\EmisionService;
use App\Facturacion\Domain\Services\NumeracionService;
use App\Facturacion\Domain\Services\SolicitudEmision;
use App\Facturacion\Domain\ValueObjects\Alicuota;
use App\Facturacion\Domain\ValueObjects\Concepto;
use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Domain\ValueObjects\Importe;
use App\Facturacion\Domain\ValueObjects\LetraComprobante;
use App\Facturacion\Domain\ValueObjects\TipoComprobante;
use App\Facturacion\Infrastructure\Arca\QrArcaPayloadBuilder;
use App\Facturacion\Infrastructure\Persistence\EloquentComprobanteFiscalRepository;
use App\Facturacion\Infrastructure\Persistence\EloquentConfiguracionFiscalRepository;
use App\Models\ComprobanteFiscal as ComprobanteFiscalModel;
use App\Models\ConfiguracionFiscalComercio;
use App\Models\Consumidor;
use App\Models\ControlSecuenciaFiscal;
use App\Models\DetalleVenta;
use App\Models\Venta;
use Tests\Support\FacturacionDomain\FakeWsfet;
use Tests\TestCaseMultiTenant;

class EmisionServiceIntegracionTest extends TestCaseMultiTenant
{
    private int $ventaId;

    private FakeWsfet $wsfet;

    private EmisionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $receptor = Consumidor::create([
            'comercio_id' => 1,
            'nombre' => 'Receptor',
            'cuit' => '30500010912',
            'razon_social' => 'Receptor RI',
            'domicilio_fiscal' => 'Calle 123',
        ]);

        $this->ventaId = Venta::create([
            'turno_caja_id' => 1,
            'consumidor_id' => $receptor->id,
            'metodo_pago' => 'EFECTIVO',
            'total' => 1210,
            'estado' => 'Completada',
        ])->id;

        DetalleVenta::create([
            'venta_id' => $this->ventaId,
            'producto_id' => 1,
            'cantidad' => 1,
            'precio_unitario' => 1210,
            'subtotal' => 1210,
            'alicuota_iva' => 21.0,
        ]);

        $this->wsfet = new FakeWsfet;

        $repositorio = new EloquentComprobanteFiscalRepository(new DesgloseIvaCalculator, new QrArcaPayloadBuilder);

        $this->service = new EmisionService(
            new EloquentConfiguracionFiscalRepository,
            $repositorio,
            new ElegibilidadEmisorRule,
            new DeterminacionLetraRule,
            new NumeracionService($repositorio),
            $this->wsfet,
        );
    }

    public function test_emite_y_persiste_en_el_ledger(): void
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

        $comprobante = $this->service->emitir($this->solicitud(receptor: $this->receptorRi()));

        $this->assertEquals(LetraComprobante::A, $comprobante->letra());
        $this->assertNotNull($comprobante->cae());

        $this->assertDatabaseHas('comprobantes_fiscales', [
            'comercio_id' => 1,
            'venta_id' => $this->ventaId,
            'tipo' => 'factura',
            'letra' => 'A',
            'numero' => 1,
            'cae' => '12345678901234',
            'total' => 1210.0,
            'estado' => 'emitido',
        ]);

        $this->assertSame(1, ControlSecuenciaFiscal::where('comercio_id', 1)
            ->where('punto_venta', 1)
            ->where('tipo', 'factura')
            ->firstOrFail()
            ->ultimo_numero);
    }

    public function test_emite_factura_b_sin_receptor_ri(): void
    {
        ConfiguracionFiscalComercio::create([
            'comercio_id' => 1,
            'cuit' => '20123456786',
            'razon_social' => 'Comercio RI',
            'condicion_fiscal' => 'responsable_inscripto',
            'domicilio_fiscal' => 'Calle 1',
            'estado_modulo' => 'listo_para_facturar',
        ]);

        $comprobante = $this->service->emitir($this->solicitud(receptor: null));

        $this->assertEquals(LetraComprobante::B, $comprobante->letra());

        $this->assertDatabaseHas('comprobantes_fiscales', [
            'comercio_id' => 1,
            'venta_id' => $this->ventaId,
            'letra' => 'B',
            'numero' => 1,
        ]);
    }

    public function test_no_emite_ni_reserva_numero_si_el_comercio_no_esta_listo(): void
    {
        ConfiguracionFiscalComercio::create([
            'comercio_id' => 1,
            'estado_modulo' => 'datos_validados',
        ]);

        try {
            $this->service->emitir($this->solicitud());
            $this->fail('Se esperaba ComercioNoListoException.');
        } catch (ComercioNoListoException) {
        }

        $this->assertSame(0, ComprobanteFiscalModel::count());
        $this->assertSame(0, ControlSecuenciaFiscal::count());
        $this->assertSame(0, $this->wsfet->solicitudes);
    }

    public function test_emite_una_nota_credito_con_numeracion_propia(): void
    {
        ConfiguracionFiscalComercio::create([
            'comercio_id' => 1,
            'cuit' => '20123456786',
            'razon_social' => 'Comercio RI',
            'condicion_fiscal' => 'responsable_inscripto',
            'estado_modulo' => 'listo_para_facturar',
        ]);

        $solicitud = $this->solicitud(receptor: null, tipo: TipoComprobante::NOTA_CREDITO);

        $this->service->emitir($solicitud);

        $this->assertDatabaseHas('comprobantes_fiscales', [
            'comercio_id' => 1,
            'tipo' => 'nota_credito',
            'numero' => 1,
        ]);

        $this->assertSame(1, ControlSecuenciaFiscal::where('comercio_id', 1)
            ->where('punto_venta', 1)
            ->where('tipo', 'nota_credito')
            ->firstOrFail()
            ->ultimo_numero);
    }

    private function solicitud(?TipoComprobante $tipo = null, ?Receptor $receptor = null): SolicitudEmision
    {
        $desglose = new DesgloseIvaCalculator;
        $emisor = new Emisor(new Cuit('20123456786'), 'Comercio RI', CondicionFiscal::RESPONSABLE_INSCRIPTO);

        return new SolicitudEmision(
            comercioId: 1,
            ventaId: $this->ventaId,
            puntoVenta: new PuntoVenta(1),
            tipo: $tipo ?? TipoComprobante::FACTURA,
            concepto: Concepto::PRODUCTOS,
            emisor: $emisor,
            detalles: [$desglose->construirDetalle(1, new Importe(1210.0), new Alicuota(21.0))],
            receptor: $receptor,
        );
    }

    private function receptorRi(): Receptor
    {
        return new Receptor(
            new Cuit('30500010912'),
            'Receptor RI',
            'Calle 123',
            CondicionFiscal::RESPONSABLE_INSCRIPTO
        );
    }
}
