<?php

namespace App\Facturacion\Domain\ValueObjects;

use App\Facturacion\Domain\Exceptions\FacturacionDomainException;
use DateTimeImmutable;

/**
 * Código de Autorización Electrónico: 14 dígitos + fecha de vencimiento.
 */
final class Cae
{
    private string $codigo;

    private DateTimeImmutable $vencimiento;

    public function __construct(string $codigo, DateTimeImmutable $vencimiento)
    {
        if (! preg_match('/^\d{14}$/', $codigo)) {
            throw new FacturacionDomainException('El CAE debe contener exactamente 14 dígitos numéricos.');
        }

        $this->codigo = $codigo;
        $this->vencimiento = $vencimiento;
    }

    public function codigo(): string
    {
        return $this->codigo;
    }

    public function vencimiento(): DateTimeImmutable
    {
        return $this->vencimiento;
    }
}
