<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * El pago manual no cumple las validaciones de negocio (importe distinto al
 * precio configurado, período duplicado, método inválido, etc.).
 */
class PagoManualInvalidoException extends RuntimeException
{
}
