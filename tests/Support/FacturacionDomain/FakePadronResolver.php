<?php

namespace Tests\Support\FacturacionDomain;

use App\Facturacion\Application\Contracts\PadronResolver;
use App\Facturacion\Domain\Contracts\PadronConsulta;
use App\Facturacion\Domain\Entities\ConfiguracionFiscal;

final class FakePadronResolver implements PadronResolver
{
    public FakePadronConsulta $cliente;

    public int $llamadas = 0;

    public function __construct(?FakePadronConsulta $cliente = null)
    {
        $this->cliente = $cliente ?? new FakePadronConsulta;
    }

    public function para(ConfiguracionFiscal $configuracion): PadronConsulta
    {
        $this->llamadas++;

        return $this->cliente;
    }
}
