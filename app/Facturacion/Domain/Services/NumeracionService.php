<?php

namespace App\Facturacion\Domain\Services;

use App\Facturacion\Domain\Contracts\ComprobanteFiscalRepository;
use App\Facturacion\Domain\ValueObjects\TipoComprobante;

/**
 * Numeración del ledger (arquitectura §18.2): secuencial, inmutable, sin
 * retrocesos y segura bajo concurrencia. Delega el cálculo atómico en el
 * repositorio (fila de control con lockForUpdate + índice único).
 */
final class NumeracionService
{
    public function __construct(
        private ComprobanteFiscalRepository $repositorio,
    ) {}

    public function siguiente(int $comercioId, int $puntoVenta, TipoComprobante $tipo): int
    {
        return $this->repositorio->proximoNumero($comercioId, $puntoVenta, $tipo->value);
    }
}
