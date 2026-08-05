<?php

namespace App\Facturacion\Application\Contracts;

use App\Facturacion\Domain\Contracts\Wsfet;
use App\Facturacion\Domain\Entities\ConfiguracionFiscal;

/**
 * Resuelve el cliente WSFE concreto de un comercio a partir de su configuración
 * fiscal (entorno, certificado y CUIT emisor). Aísla la construcción per-comercio
 * del WsfetClient (infraestructura) del caso de uso de aplicación (F5).
 */
interface WsfetResolver
{
    public function resolver(ConfiguracionFiscal $configuracion): Wsfet;
}
