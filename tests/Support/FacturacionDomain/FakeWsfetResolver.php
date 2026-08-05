<?php

namespace Tests\Support\FacturacionDomain;

use App\Facturacion\Application\Contracts\WsfetResolver;
use App\Facturacion\Domain\Contracts\Wsfet;
use App\Facturacion\Domain\Entities\ConfiguracionFiscal;

final class FakeWsfetResolver implements WsfetResolver
{
    public FakeWsfet $wsfet;

    public function __construct(?FakeWsfet $wsfet = null)
    {
        $this->wsfet = $wsfet ?? new FakeWsfet;
    }

    public function resolver(ConfiguracionFiscal $configuracion): Wsfet
    {
        return $this->wsfet;
    }
}
