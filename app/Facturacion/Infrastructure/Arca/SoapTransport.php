<?php

namespace App\Facturacion\Infrastructure\Arca;

use App\Facturacion\Infrastructure\Arca\Exceptions\ArcaIntegrationException;
use SoapClient;
use SoapFault;
use SoapHeader;
use Throwable;

/**
 * Transporte SOAP real sobre SoapClient. La instancia se crea de forma perezosa
 * por la fábrica (el WSDL no se descarga hasta la primera llamada).
 */
final class SoapTransport implements ArcaSoapTransport
{
    public function __construct(private SoapClient $cliente) {}

    public function llamar(string $operacion, array $argumentos, ?SoapHeader $cabecera = null): object
    {
        if ($cabecera !== null) {
            $this->cliente->__setSoapHeaders($cabecera);
        }

        try {
            $respuesta = $this->cliente->__soapCall($operacion, $argumentos);
        } catch (SoapFault $e) {
            throw new ArcaIntegrationException("La operación SOAP \"{$operacion}\" falló: {$e->getMessage()}", 0, $e);
        } catch (Throwable $e) {
            throw new ArcaIntegrationException("La operación SOAP \"{$operacion}\" falló: {$e->getMessage()}", 0, $e);
        }

        if (! is_object($respuesta)) {
            throw new ArcaIntegrationException("La operación \"{$operacion}\" devolvió una respuesta inesperada.");
        }

        return $respuesta;
    }
}
