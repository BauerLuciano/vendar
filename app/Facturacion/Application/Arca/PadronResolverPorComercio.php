<?php

namespace App\Facturacion\Application\Arca;

use App\Facturacion\Application\Contracts\PadronResolver;
use App\Facturacion\Domain\Contracts\PadronConsulta;
use App\Facturacion\Domain\Entities\ConfiguracionFiscal;
use App\Facturacion\Infrastructure\Arca\Padron\PadronClientFactory;

/**
 * Implementación de producción: construye el PadronClient del entorno del
 * comercio (homologación/producción). La credencial de plataforma se resuelve
 * internamente al consultar (invariante 10: solo consulta, nunca emite).
 */
final class PadronResolverPorComercio implements PadronResolver
{
    public function __construct(
        private PadronClientFactory $factory,
    ) {}

    public function para(ConfiguracionFiscal $configuracion): PadronConsulta
    {
        return $this->factory->para($configuracion->entorno());
    }
}
