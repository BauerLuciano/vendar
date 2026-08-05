<?php

namespace App\Facturacion\Infrastructure\Arca\Exceptions;

use RuntimeException;

/**
 * Intento de usar el entorno de homologación por fuera de Administración Global /
 * Desarrollo (arquitectura §13.1 y §16).
 */
class EntornoRestringidoException extends RuntimeException {}
