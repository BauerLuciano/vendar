<?php

namespace App\Facturacion\Infrastructure\Arca\Wsfe;

use App\Facturacion\Domain\Contracts\Wsfet;
use App\Facturacion\Domain\Entities\ComprobanteFiscal;
use App\Facturacion\Domain\ValueObjects\Cae;
use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Domain\ValueObjects\LetraComprobante;
use App\Facturacion\Domain\ValueObjects\TipoComprobante;
use App\Facturacion\Infrastructure\Arca\Certificado\CertificadoMaterial;
use App\Facturacion\Infrastructure\Arca\Exceptions\ArcaIntegrationException;
use App\Facturacion\Infrastructure\Arca\SoapClientFactory;
use App\Facturacion\Infrastructure\Arca\Wsaa\WsaaClient;
use SoapHeader;

/**
 * Cliente WSFE v4 (RG 5616/2024) detrás de la interfaz Wsfet/ComprobanteFiscalAdapter
 * (arquitectura §14.2). Un cambio de versión de ARCA toca únicamente esta capa.
 *
 * Una instancia se construye por comercio y entorno (WsfetClientFactory); F3 decide
 * el certificado y el entorno según la configuración fiscal del comercio.
 */
final class WsfetClient implements Wsfet
{
    private const SERVICIO_WSAA = 'wsfe';

    public function __construct(
        private SoapClientFactory $transportes,
        private WsfeConfig $config,
        private WsaaClient $wsaa,
        private FECAERequestBuilder $builder,
        private CaeMapper $mapper,
        private CertificadoMaterial $material,
        private Cuit $cuitEmisor,
        private ComprobanteAsociadoResolver $asociadoResolver,
    ) {}

    public function solicitarCae(ComprobanteFiscal $comprobante): Cae
    {
        $requerimiento = $this->builder->construir(
            $comprobante,
            null,
            $this->asociadoSiNotaCredito($comprobante)
        );

        $respuesta = $this->invocar('FECAESolicitar', ['FeCAEReq' => $requerimiento]);

        $resultado = $respuesta->FECAESolicitarResult ?? null;

        if (! is_object($resultado)) {
            throw new ArcaIntegrationException('ARCA no devolvió la respuesta del requerimiento.');
        }

        $cae = $this->mapper->desdeRespuesta($resultado);

        if ($cae === null) {
            $errores = $this->mapper->errores($resultado);

            $detalle = $errores === [] ? 'El comprobante fue rechazado por ARCA.' : implode(' | ', $errores);

            throw new ArcaIntegrationException($detalle);
        }

        return $cae;
    }

    public function consultarComprobante(
        int $puntoVenta,
        int $numero,
        TipoComprobante $tipo,
        LetraComprobante $letra,
    ): ?Cae {
        $requerimiento = [
            'FeConsReq' => [
                'FeCabReq' => [
                    'CantReg' => 1,
                    'PtoVta' => $puntoVenta,
                    'CbteTipo' => $tipo->codigoAfip($letra),
                ],
                'FeDetReq' => [
                    'FECAEDetRequest' => [
                        'Concepto' => 1,
                        'DocTipo' => 80,
                        'DocNro' => 0,
                        'CbteDesde' => $numero,
                        'CbteHasta' => $numero,
                        'CbteFch' => date('Ymd'),
                        'ImpTotal' => 0,
                        'ImpTotConc' => 0,
                        'ImpNeto' => 0,
                        'ImpOpEx' => 0,
                        'ImpIVA' => 0,
                        'ImpTrib' => 0,
                        'MonId' => 'PES',
                        'MonCotiz' => 1,
                    ],
                ],
            ],
        ];

        $respuesta = $this->invocar('FEConsultaCAERequerimiento', ['feConsReq' => $requerimiento]);

        $resultado = $respuesta->FEConsultaCAERequerimientoResult ?? null;

        if (! is_object($resultado)) {
            return null;
        }

        return $this->mapper->desdeRespuesta($resultado);
    }

    /**
     * Puntos de venta habilitados del emisor en ARCA.
     *
     * @return array<int, array{nro: int, bloqueado: bool}>
     */
    public function puntosVenta(): array
    {
        $respuesta = $this->invocar('FEParamGetPtosVenta', []);

        $puntos = $respuesta->FEParamGetPtosVentaResult->ResultGet->PtoVenta ?? null;

        if (is_object($puntos)) {
            $puntos = [$puntos];
        }

        if (! is_array($puntos)) {
            return [];
        }

        $resultado = [];

        foreach ($puntos as $punto) {
            $resultado[] = [
                'nro' => (int) ($punto->Nro ?? 0),
                'bloqueado' => $this->estaBloqueado($punto->Bloqueado ?? true),
            ];
        }

        return $resultado;
    }

    private function estaBloqueado(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        return in_array(strtoupper(trim((string) $valor)), ['S', 'TRUE', '1'], true);
    }

    /**
     * Alícuotas de IVA habilitadas en ARCA.
     *
     * @return array<int, array{id: int, descripcion: string}>
     */
    public function alicuotas(): array
    {
        $respuesta = $this->invocar('FEParamGetTiposIva', []);

        $ivas = $respuesta->FEParamGetTiposIvaResult->ResultGet->IvaTipo ?? null;

        if (is_object($ivas)) {
            $ivas = [$ivas];
        }

        if (! is_array($ivas)) {
            return [];
        }

        $resultado = [];

        foreach ($ivas as $iva) {
            $resultado[] = [
                'id' => (int) ($iva->Id ?? 0),
                'descripcion' => trim((string) ($iva->Desc ?? $iva->Descripcion ?? '')),
            ];
        }

        return $resultado;
    }

    private function invocar(string $operacion, array $argumentos): object
    {
        $token = $this->wsaa->obtenerToken(self::SERVICIO_WSAA, $this->config->entorno(), $this->material);

        $cabecera = new SoapHeader(
            $this->config->namespaceAuth(),
            'Auth',
            [
                'Token' => $token->token(),
                'Sign' => $token->sign(),
                'Cuit' => $this->cuitEmisor->valor(),
            ]
        );

        return $this->transportes->crearTransporte($this->config->wsdl(), $this->config->opciones())
            ->llamar($operacion, $argumentos, $cabecera);
    }

    private function asociadoSiNotaCredito(ComprobanteFiscal $comprobante): ?array
    {
        $originalId = $comprobante->comprobanteOriginalId();

        if (! $comprobante->esNotaCredito() || $originalId === null) {
            return null;
        }

        $asociado = $this->asociadoResolver->resolver($originalId);

        if ($asociado === null) {
            throw new ArcaIntegrationException('No se pudo resolver el comprobante original de la Nota de Crédito.');
        }

        return $asociado;
    }
}
