<?php

namespace App\Facturacion\Infrastructure\Arca\Entorno;

use InvalidArgumentException;

/**
 * Entorno de integración con ARCA (arquitectura §14.5).
 * La homologación es exclusiva de Administración Global / Desarrollo (§13.1).
 */
enum EntornoArca: string
{
    case PRODUCCION = 'produccion';
    case HOMOLOGACION = 'homologacion';

    public static function desde(string $valor): self
    {
        return self::tryFrom($valor)
            ?? throw new InvalidArgumentException("El entorno ARCA \"{$valor}\" no es válido.");
    }
}
