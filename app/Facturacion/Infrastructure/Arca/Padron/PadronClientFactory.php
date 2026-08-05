<?php

namespace App\Facturacion\Infrastructure\Arca\Padron;

use App\Facturacion\Infrastructure\Arca\Cifrado\CredencialPlataformaService;
use App\Facturacion\Infrastructure\Arca\Entorno\ArcaEndpointResolver;
use App\Facturacion\Infrastructure\Arca\Entorno\EntornoArca;
use App\Facturacion\Infrastructure\Arca\SoapClientFactory;

/**
 * Construye PadronClient por entorno (F7 wizard y F5 validación de receptor la usan).
 */
final class PadronClientFactory
{
    public function __construct(
        private SoapClientFactory $transportes,
        private ArcaEndpointResolver $endpoints,
        private CredencialPlataformaService $credencial,
        private CondicionFiscalMapper $mapper,
    ) {}

    public function para(string|EntornoArca $entorno): PadronClient
    {
        $entorno = $entorno instanceof EntornoArca ? $entorno : EntornoArca::desde($entorno);

        return new PadronClient(
            $this->transportes,
            $this->endpoints,
            $this->credencial,
            $this->mapper,
            $entorno,
        );
    }
}
