<?php

namespace App\Facturacion\Infrastructure\Arca;

/**
 * Crea transportes SOAP para los servicios de ARCA (WSAA, WSFE y padrón).
 * Permite inyectar transportes simulados en tests sin abrir conexiones reales.
 */
interface SoapClientFactory
{
    public function crearTransporte(string $wsdl, array $opciones): ArcaSoapTransport;
}
