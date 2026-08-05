<?php

namespace App\Facturacion\Application\Exceptions;

use RuntimeException;

/**
 * Error de emisión fiscal en el flujo de venta (F5).
 * Se propaga para que la transacción de la venta revierta (invariante 1).
 */
final class EmisionVentaException extends RuntimeException
{
}
