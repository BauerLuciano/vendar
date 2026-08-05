<?php

namespace App\Facturacion\Domain\Entities;

use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Domain\ValueObjects\Cuit;

/**
 * Receptor del comprobante. Para Factura A exige CUIT + razón social + domicilio
 * fiscal y condición RI (arquitectura §5). Para Factura B puede ser consumidor final.
 */
final class Receptor
{
    private ?Cuit $cuit;

    private ?string $razonSocial;

    private ?string $domicilioFiscal;

    private ?CondicionFiscal $condicionFiscal;

    public function __construct(
        ?Cuit $cuit = null,
        ?string $razonSocial = null,
        ?string $domicilioFiscal = null,
        ?CondicionFiscal $condicionFiscal = null
    ) {
        $this->cuit = $cuit;
        $this->razonSocial = $razonSocial;
        $this->domicilioFiscal = $domicilioFiscal;
        $this->condicionFiscal = $condicionFiscal;
    }

    public function cuit(): ?Cuit
    {
        return $this->cuit;
    }

    public function razonSocial(): ?string
    {
        return $this->razonSocial;
    }

    public function domicilioFiscal(): ?string
    {
        return $this->domicilioFiscal;
    }

    public function condicionFiscal(): ?CondicionFiscal
    {
        return $this->condicionFiscal;
    }

    /**
     * Datos mínimos para emitir Factura A: CUIT válido + razón social + domicilio
     * (arquitectura §5). La validación contra el padrón se hace en la capa de uso.
     */
    public function tieneDatosParaFacturaA(): bool
    {
        return $this->cuit !== null
            && ! empty($this->razonSocial)
            && ! empty($this->domicilioFiscal);
    }

    /**
     * En el MVP, receptor RI = Factura A; monotributo/consumidor final = Factura B (§1.3).
     * Un receptor sin condición informada se trata como consumidor final.
     */
    public function esReceptorResponsableInscripto(): bool
    {
        return $this->condicionFiscal?->esResponsableInscripto() ?? false;
    }
}
