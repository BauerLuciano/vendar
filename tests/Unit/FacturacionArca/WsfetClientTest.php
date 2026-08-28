<?php

namespace Tests\Unit\FacturacionArca;

use App\Facturacion\Domain\Calculators\DesgloseIvaCalculator;
use App\Facturacion\Domain\Entities\ComprobanteFiscal;
use App\Facturacion\Domain\Entities\Emisor;
use App\Facturacion\Domain\Entities\PuntoVenta;
use App\Facturacion\Domain\Entities\Receptor;
use App\Facturacion\Domain\ValueObjects\Alicuota;
use App\Facturacion\Domain\ValueObjects\Cae;
use App\Facturacion\Domain\ValueObjects\Concepto;
use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Domain\ValueObjects\Importe;
use App\Facturacion\Domain\ValueObjects\LetraComprobante;
use App\Facturacion\Domain\ValueObjects\TipoComprobante;
use App\Facturacion\Infrastructure\Arca\Certificado\CertificadoMaterial;
use App\Facturacion\Infrastructure\Arca\Entorno\ArcaEndpointResolver;
use App\Facturacion\Infrastructure\Arca\Entorno\EntornoArca;
use App\Facturacion\Infrastructure\Arca\Exceptions\ArcaIntegrationException;
use App\Facturacion\Infrastructure\Arca\Wsaa\FirmaCms;
use App\Facturacion\Infrastructure\Arca\Wsaa\WsaaClient;
use App\Facturacion\Infrastructure\Arca\Wsfe\CaeMapper;
use App\Facturacion\Infrastructure\Arca\Wsfe\ComprobanteAsociadoResolver;
use App\Facturacion\Infrastructure\Arca\Wsfe\FECAERequestBuilder;
use App\Facturacion\Infrastructure\Arca\Wsfe\WsfeConfig;
use App\Facturacion\Infrastructure\Arca\Wsfe\WsfetClient;
use DateTimeImmutable;
use Tests\Support\FacturacionArca\FakeArcaSoapTransport;
use Tests\Support\FacturacionArca\FakeSoapClientFactory;
use Tests\Support\FacturacionArca\GeneraPfx;
use Tests\Support\FacturacionArca\RespuestasArca;
use Tests\TestCase;

class WsfetClientTest extends TestCase
{
    use RespuestasArca;

    public function test_solicitar_cae_envia_requerimiento_y_devuelve_cae(): void
    {
        $capturados = [];
        $transporte = new FakeArcaSoapTransport(function ($operacion, $argumentos) use (&$capturados) {
            $capturados[] = ['operacion' => $operacion, 'argumentos' => $argumentos];

            return match ($operacion) {
                'loginCms' => (object) ['loginCmsReturn' => $this->loginXml((new DateTimeImmutable)->modify('+600 seconds'))],
                'FECAESolicitar' => (object) ['FECAESolicitarResult' => $this->respuestaWsfeAprobada('64001234567890', '20260813')],
                default => throw new ArcaIntegrationException("Operación inesperada {$operacion}"),
            };
        });

        $client = $this->cliente($transporte);

        $cae = $client->solicitarCae($this->comprobante());

        $this->assertSame('64001234567890', $cae->codigo());

        $solicitar = null;
        foreach ($capturados as $capturado) {
            if ($capturado['operacion'] === 'FECAESolicitar') {
                $solicitar = $capturado['argumentos']['FeCAEReq'];
            }
        }

        $this->assertNotNull($solicitar);
        $detalle = $solicitar['FeDetReq']['FECAEDetRequest'];
        $this->assertSame(6, $solicitar['FeCabReq']['CbteTipo']);
        $this->assertSame(96, $detalle['DocTipo']);
        $this->assertSame(0, $detalle['DocNro']);
        $this->assertEqualsWithDelta(1000.0, $detalle['ImpTotal'], 0.01);
        $this->assertSame('PES', $detalle['MonId']);
    }

    public function test_autenticacion_wsaa_se_envia_en_el_body(): void
    {
        $transporte = new FakeArcaSoapTransport(function ($operacion) {
            return match ($operacion) {
                'loginCms' => (object) ['loginCmsReturn' => $this->loginXml((new DateTimeImmutable)->modify('+600 seconds'))],
                'FECAESolicitar' => (object) ['FECAESolicitarResult' => $this->respuestaWsfeAprobada('64001234567890', '20260813')],
                default => throw new ArcaIntegrationException("Operación inesperada {$operacion}"),
            };
        });

        $client = $this->cliente($transporte);
        $client->solicitarCae($this->comprobante());

        $auth = null;
        foreach ($transporte->llamadas as $llamada) {
            if ($llamada['operacion'] === 'FECAESolicitar') {
                $auth = $llamada['argumentos']['Auth'];
            }
        }

        $this->assertNotNull($auth);
        $this->assertNull($transporte->llamadas[0]['cabecera'] ?? null);
        $this->assertSame(GeneraPfx::CUIT_VALIDO, $auth['Cuit']);
        $this->assertSame('TOKEN_WSAA_TEST', $auth['Token']);
        $this->assertSame('SIGN_WSAA_TEST', $auth['Sign']);
    }

    public function test_solicitar_cae_rechazado_lanza_con_errores(): void
    {
        $transporte = new FakeArcaSoapTransport(function ($operacion) {
            return match ($operacion) {
                'loginCms' => (object) ['loginCmsReturn' => $this->loginXml((new DateTimeImmutable)->modify('+600 seconds'))],
                'FECAESolicitar' => (object) ['FECAESolicitarResult' => $this->respuestaWsfeRechazada('El comprobante no se puede facturar')],
                default => throw new ArcaIntegrationException("Operación inesperada {$operacion}"),
            };
        });

        $client = $this->cliente($transporte);

        $this->expectException(ArcaIntegrationException::class);
        $this->expectExceptionMessage('El comprobante no se puede facturar');

        $client->solicitarCae($this->comprobante());
    }

    public function test_nota_credito_incluye_comprobante_asociado(): void
    {
        $capturados = [];
        $transporte = new FakeArcaSoapTransport(function ($operacion, $argumentos) use (&$capturados) {
            $capturados[] = ['operacion' => $operacion, 'argumentos' => $argumentos];

            return match ($operacion) {
                'loginCms' => (object) ['loginCmsReturn' => $this->loginXml((new DateTimeImmutable)->modify('+600 seconds'))],
                'FECAESolicitar' => (object) ['FECAESolicitarResult' => $this->respuestaWsfeAprobada('64001234567890', '20260813')],
                default => throw new ArcaIntegrationException("Operación inesperada {$operacion}"),
            };
        });

        $client = $this->cliente($transporte);
        $client->solicitarCae($this->comprobante(TipoComprobante::NOTA_CREDITO));

        $detalle = null;
        foreach ($capturados as $capturado) {
            if ($capturado['operacion'] === 'FECAESolicitar') {
                $detalle = $capturado['argumentos']['FeCAEReq']['FeDetReq']['FECAEDetRequest'];
            }
        }

        $this->assertNotNull($detalle);
        $this->assertSame(8, $capturados[1]['argumentos']['FeCAEReq']['FeCabReq']['CbteTipo'] ?? null);
        $this->assertSame(1, $detalle['CmpAsoc']['CmpAsoc'][0]['Tipo']);
        $this->assertSame(1, $detalle['CmpAsoc']['CmpAsoc'][0]['PtoVta']);
        $this->assertSame(50, $detalle['CmpAsoc']['CmpAsoc'][0]['Nro']);
    }

    public function test_consultar_comprobante_devuelve_cae(): void
    {
        $transporte = new FakeArcaSoapTransport(function ($operacion) {
            return match ($operacion) {
                'loginCms' => (object) ['loginCmsReturn' => $this->loginXml((new DateTimeImmutable)->modify('+600 seconds'))],
                'FEConsultaCAERequerimiento' => (object) ['FEConsultaCAERequerimientoResult' => $this->respuestaWsfeAprobada('64001234567890', '20260813')],
                default => throw new ArcaIntegrationException("Operación inesperada {$operacion}"),
            };
        });

        $client = $this->cliente($transporte);

        $cae = $client->consultarComprobante(1, 100, TipoComprobante::FACTURA, LetraComprobante::B);

        $this->assertSame('64001234567890', $cae?->codigo());
    }

    public function test_puntos_venta_y_alicuotas(): void
    {
        $transporte = new FakeArcaSoapTransport(function ($operacion) {
            return match ($operacion) {
                'loginCms' => (object) ['loginCmsReturn' => $this->loginXml((new DateTimeImmutable)->modify('+600 seconds'))],
                'FEParamGetPtosVenta' => $this->respuestaPuntoVenta(),
                'FEParamGetTiposIva' => $this->respuestaAlicuotas(),
                default => throw new ArcaIntegrationException("Operación inesperada {$operacion}"),
            };
        });

        $client = $this->cliente($transporte);

        $puntos = $client->puntosVenta();
        $this->assertSame([['nro' => 1, 'bloqueado' => false], ['nro' => 2, 'bloqueado' => true]], $puntos);

        $alicuotas = $client->alicuotas();
        $this->assertSame([['id' => 5, 'descripcion' => 'IVA 21%'], ['id' => 4, 'descripcion' => 'IVA 10.5%']], $alicuotas);
    }

    private function cliente(FakeArcaSoapTransport $transporte): WsfetClient
    {
        $material = new CertificadoMaterial(GeneraPfx::valido()['pfx'], 'clave-secreta');

        $wsaa = new WsaaClient(
            new FakeSoapClientFactory($transporte),
            new FirmaCms,
            new ArcaEndpointResolver(config('services.arca')),
        );

        return new WsfetClient(
            new FakeSoapClientFactory($transporte),
            new WsfeConfig(
                EntornoArca::PRODUCCION,
                'https://wsfe.test/wsfev1/service.asmx?WSDL',
                'http://ar.gov.afip.dif.FEV1/',
                ['exceptions' => true],
            ),
            $wsaa,
            new FECAERequestBuilder,
            new CaeMapper,
            $material,
            new Cuit(GeneraPfx::CUIT_VALIDO),
            new class implements ComprobanteAsociadoResolver
            {
                public function resolver(int $comprobanteOriginalId): ?array
                {
                    return ['tipo' => 1, 'ptoVta' => 1, 'nro' => 50];
                }
            },
        );
    }

    private function comprobante(TipoComprobante $tipo = TipoComprobante::FACTURA): ComprobanteFiscal
    {
        $emisor = new Emisor(new Cuit(GeneraPfx::CUIT_VALIDO), 'Emisor RI', CondicionFiscal::RESPONSABLE_INSCRIPTO);
        $cae = new Cae('12345678901234', new DateTimeImmutable('2030-01-01'));

        $desglose = new DesgloseIvaCalculator;
        $detalle = $desglose->construirDetalle(1, new Importe(1000.0), Alicuota::general());

        return new ComprobanteFiscal(
            comercioId: 1,
            ventaId: 1,
            puntoVenta: new PuntoVenta(1),
            tipo: $tipo,
            letra: LetraComprobante::B,
            concepto: Concepto::PRODUCTOS,
            emisor: $emisor,
            cae: $cae,
            detalles: [$detalle],
            receptor: new Receptor,
            numero: 100,
            comprobanteOriginalId: $tipo->esNotaCredito() ? 42 : null,
        );
    }
}
