<?php

namespace App\Facturacion\Application\Exceptions;

use RuntimeException;

/**
 * Error de negocio en el flujo de anulación/devolución de una venta
 * (F8: lógica extraída a VentaOperacionFiscalService). El controller lo
 * traduce a un mensaje de error al usuario; no revierte una transacción
 * por sí misma.
 */
final class VentaOperacionException extends RuntimeException {}
