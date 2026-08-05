<?php

namespace App\Facturacion\Domain\Rules;

use App\Facturacion\Domain\Entities\Emisor;
use App\Facturacion\Domain\Entities\Receptor;
use App\Facturacion\Domain\Exceptions\EmisorNoElegibleException;
use App\Facturacion\Domain\ValueObjects\LetraComprobante;

/**
 * Determinación automática de la letra del comprobante (arquitectura §1.3).
 * Solo emisores RI (única condición soportada); la letra nunca se elige manualmente.
 *
 * | Emisor | Receptor | Comprobante |
 * |--------|----------|-------------|
 * | RI     | RI       | Factura A   |
 * | RI     | Monotributo / Consumidor Final | Factura B |
 */
final class DeterminacionLetraRule
{
    public function determinar(Emisor $emisor, ?Receptor $receptor): LetraComprobante
    {
        if (! $emisor->esElegible()) {
            throw new EmisorNoElegibleException(
                'El emisor no es Responsable Inscripto: no puede emitir comprobantes en el MVP.'
            );
        }

        if ($receptor === null || ! $receptor->esReceptorResponsableInscripto()) {
            return LetraComprobante::B;
        }

        return LetraComprobante::A;
    }
}
