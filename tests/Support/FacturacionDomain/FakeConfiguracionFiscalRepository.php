<?php

namespace Tests\Support\FacturacionDomain;

use App\Facturacion\Domain\Contracts\ConfiguracionFiscalRepository;
use App\Facturacion\Domain\Entities\ConfiguracionFiscal;

final class FakeConfiguracionFiscalRepository implements ConfiguracionFiscalRepository
{
    public ?ConfiguracionFiscal $configuracion;

    public function __construct(?ConfiguracionFiscal $configuracion = null)
    {
        $this->configuracion = $configuracion;
    }

    public function buscarPorComercio(int $comercioId): ?ConfiguracionFiscal
    {
        return $this->configuracion;
    }

    public function guardar(ConfiguracionFiscal $configuracion): void
    {
        $this->configuracion = $configuracion;
    }
}
