<?php

namespace App\Facturacion\Domain\ValueObjects;

/**
 * Estados del ciclo de vida del comprobante (arquitectura §7.2):
 * pendiente_emision → en_proceso → emitido | fallo.
 */
enum EstadoComprobante: string
{
    case PENDIENTE_EMISION = 'pendiente_emision';
    case EN_PROCESO = 'en_proceso';
    case EMITIDO = 'emitido';
    case FALLO = 'fallo';
}
