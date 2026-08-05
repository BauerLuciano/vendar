<?php

namespace App\Facturacion\Application\Contracts;

use App\Facturacion\Domain\Entities\ConfiguracionFiscal;

/**
 * Resuelve la suite de verificación de conectividad con ARCA para un comercio
 * (wizard paso 5 y diagnóstico F8, arquitectura §15). Aísla la construcción
 * del ConectividadArcaService (infraestructura/SOAP) del caso de uso.
 */
interface ConectividadResolver
{
    /**
     * @return array<int, array{check: string, ok: bool, detalle: string}>
     */
    public function suite(ConfiguracionFiscal $configuracion): array;
}
