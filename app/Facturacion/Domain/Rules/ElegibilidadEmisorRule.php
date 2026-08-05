<?php

namespace App\Facturacion\Domain\Rules;

use App\Facturacion\Domain\Entities\Emisor;

/**
 * Regla de elegibilidad del emisor (arquitectura §1.2): el MVP solo soporta
 * Responsable Inscripto. Un emisor monotributo deja el módulo en estado
 * no_soportado y no emite (sigue operando con ticket no fiscal).
 */
final class ElegibilidadEmisorRule
{
    public function esElegible(Emisor $emisor): bool
    {
        return $emisor->esElegible();
    }

    public function motivoNoElegible(Emisor $emisor): string
    {
        if ($emisor->condicionFiscal()->esMonotributo()) {
            return 'El emisor es monotributista: el módulo fiscal no es soportado en el MVP.';
        }

        return 'La condición fiscal del emisor no está soportada en el MVP.';
    }
}
