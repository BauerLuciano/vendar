<?php

namespace App\Facturacion\Infrastructure\Arca;

use SoapHeader;

/**
 * Transporte SOAP de ARCA. Aisla el acceso a SoapClient para poder mockear
 * las operaciones en tests sin red real (regla de CI: nunca ARCA real).
 */
interface ArcaSoapTransport
{
    /**
     * Invoca una operación SOAP. Ante SoapFault lanza ArcaIntegrationException.
     */
    public function llamar(string $operacion, array $argumentos, ?SoapHeader $cabecera = null): object;
}
