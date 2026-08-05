<?php

namespace App\Facturacion\Domain\ValueObjects;

/**
 * Letra del comprobante fiscal. El MVP soporta A y B (arquitectura §1.1).
 * La letra nunca se elige manualmente: la determina DeterminacionLetraRule (§1.3).
 */
enum LetraComprobante: string
{
    case A = 'A';
    case B = 'B';
}
