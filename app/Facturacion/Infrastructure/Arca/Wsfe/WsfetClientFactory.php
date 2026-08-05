<?php

namespace App\Facturacion\Infrastructure\Arca\Wsfe;

use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Infrastructure\Arca\Certificado\CertificadoMaterial;
use App\Facturacion\Infrastructure\Arca\Entorno\ArcaEndpointResolver;
use App\Facturacion\Infrastructure\Arca\Entorno\EntornoArca;
use App\Facturacion\Infrastructure\Arca\SoapClientFactory;
use App\Facturacion\Infrastructure\Arca\Wsaa\WsaaClient;

/**
 * Construye WsfetClient por comercio y entorno (F3 y el diagnóstico la usan).
 */
final class WsfetClientFactory
{
    public function __construct(
        private SoapClientFactory $transportes,
        private WsaaClient $wsaa,
        private FECAERequestBuilder $builder,
        private CaeMapper $mapper,
        private ArcaEndpointResolver $endpoints,
        private ComprobanteAsociadoResolver $asociadoResolver,
    ) {}

    public function para(string|EntornoArca $entorno, CertificadoMaterial $material, Cuit $cuitEmisor): WsfetClient
    {
        $entorno = $entorno instanceof EntornoArca ? $entorno : EntornoArca::desde($entorno);

        return new WsfetClient(
            $this->transportes,
            new WsfeConfig(
                $entorno,
                $this->endpoints->wsdlWsfe($entorno),
                $this->endpoints->namespaceAuthWsfe(),
                $this->endpoints->opcionesSoap(),
            ),
            $this->wsaa,
            $this->builder,
            $this->mapper,
            $material,
            $cuitEmisor,
            $this->asociadoResolver,
        );
    }
}
