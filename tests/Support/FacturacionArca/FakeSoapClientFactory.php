<?php

namespace Tests\Support\FacturacionArca;

use App\Facturacion\Infrastructure\Arca\ArcaSoapTransport;
use App\Facturacion\Infrastructure\Arca\SoapClientFactory;

/**
 * Fábrica de transportes SOAP simulados para tests (nunca ARCA real en CI).
 */
final class FakeSoapClientFactory implements SoapClientFactory
{
    public function __construct(private ArcaSoapTransport $transporte) {}

    public function crearTransporte(string $wsdl, array $opciones): ArcaSoapTransport
    {
        return $this->transporte;
    }
}
