<?php

namespace App\Facturacion\Domain\Entities;

use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Domain\ValueObjects\Cuit;

/**
 * Emisor del comprobante (el comercio). El MVP solo soporta Responsable Inscripto (§1.2).
 */
final class Emisor
{
    private Cuit $cuit;

    private string $razonSocial;

    private CondicionFiscal $condicionFiscal;

    public function __construct(Cuit $cuit, string $razonSocial, CondicionFiscal $condicionFiscal)
    {
        $this->cuit = $cuit;
        $this->razonSocial = $razonSocial;
        $this->condicionFiscal = $condicionFiscal;
    }

    public function cuit(): Cuit
    {
        return $this->cuit;
    }

    public function razonSocial(): string
    {
        return $this->razonSocial;
    }

    public function condicionFiscal(): CondicionFiscal
    {
        return $this->condicionFiscal;
    }

    /**
     * En el MVP solo un emisor Responsable Inscripto es elegible para facturar.
     * El monotributo queda en estado no_soportado (§1.2).
     */
    public function esElegible(): bool
    {
        return $this->condicionFiscal->esResponsableInscripto();
    }
}
