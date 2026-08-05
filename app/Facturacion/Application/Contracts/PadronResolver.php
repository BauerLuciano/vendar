<?php

namespace App\Facturacion\Application\Contracts;

use App\Facturacion\Domain\Contracts\PadronConsulta;
use App\Facturacion\Domain\Entities\ConfiguracionFiscal;

/**
 * Resuelve el cliente del padrón ARCA de un comercio a partir de su
 * configuración fiscal (entorno). Aísla la construcción del PadronClient
 * (infraestructura/SOAP) del caso de uso de aplicación (F5), permitiendo
 * testear la validación de receptor con fakes.
 */
interface PadronResolver
{
    public function para(ConfiguracionFiscal $configuracion): PadronConsulta;
}
