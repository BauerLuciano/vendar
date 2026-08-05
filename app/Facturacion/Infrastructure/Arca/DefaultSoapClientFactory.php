<?php

namespace App\Facturacion\Infrastructure\Arca;

use SoapClient;

/**
 * Fábrica por defecto de transportes SOAP.
 */
final class DefaultSoapClientFactory implements SoapClientFactory
{
    public function crearTransporte(string $wsdl, array $opciones): ArcaSoapTransport
    {
        return new SoapTransport(new SoapClient($wsdl, $opciones));
    }
}
