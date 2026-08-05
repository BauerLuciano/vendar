<?php

namespace App\Facturacion\Domain\ValueObjects;

/**
 * Condición fiscal de un contribuyente según el padrón ARCA.
 * El MVP solo emite si el emisor es Responsable Inscripto (§1.2).
 */
enum CondicionFiscal: string
{
    case RESPONSABLE_INSCRIPTO = 'responsable_inscripto';
    case MONOTRIBUTO = 'monotributo';
    case CONSUMIDOR_FINAL = 'consumidor_final';
    case EXENTO = 'exento';
    case NO_ALCANZADO = 'no_alcanzado';

    public function esResponsableInscripto(): bool
    {
        return $this === self::RESPONSABLE_INSCRIPTO;
    }

    public function esMonotributo(): bool
    {
        return $this === self::MONOTRIBUTO;
    }

    public function esConsumidorFinal(): bool
    {
        return $this === self::CONSUMIDOR_FINAL;
    }
}
