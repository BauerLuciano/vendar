<?php

namespace App\Facturacion\Domain\ValueObjects;

/**
 * Concepto del comprobante según el WSFE (códigos 1..3).
 * El MVP opera con productos (concepto 1).
 */
enum Concepto: string
{
    case PRODUCTOS = 'productos';
    case SERVICIOS = 'servicios';
    case PRODUCTOS_Y_SERVICIOS = 'productos_y_servicios';

    public function codigoAfip(): int
    {
        return match ($this) {
            self::PRODUCTOS => 1,
            self::SERVICIOS => 2,
            self::PRODUCTOS_Y_SERVICIOS => 3,
        };
    }
}
