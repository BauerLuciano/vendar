<?php

namespace App\Facturacion\Infrastructure\Arca\Exceptions;

use RuntimeException;

/**
 * Excepción base de integración con ARCA (SOAP, red o respuestas inválidas).
 * Al lanzarse durante la emisión, la venta no se completa (arquitectura §7.2).
 */
class ArcaIntegrationException extends RuntimeException {}
