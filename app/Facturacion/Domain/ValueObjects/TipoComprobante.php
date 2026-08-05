<?php

namespace App\Facturacion\Domain\ValueObjects;

/**
 * Tipo de comprobante del ledger fiscal. El MVP emite Facturas y Notas de Crédito (A/B).
 */
enum TipoComprobante: string
{
    case FACTURA = 'factura';
    case NOTA_CREDITO = 'nota_credito';

    public function esFactura(): bool
    {
        return $this === self::FACTURA;
    }

    public function esNotaCredito(): bool
    {
        return $this === self::NOTA_CREDITO;
    }

    /**
     * Código AFIP de comprobante (valor facturable en el WSFE).
     * Factura A = 1, Factura B = 6, NC A = 3, NC B = 8.
     */
    public function codigoAfip(LetraComprobante $letra): int
    {
        return match ($this) {
            self::FACTURA => $letra === LetraComprobante::A ? 1 : 6,
            self::NOTA_CREDITO => $letra === LetraComprobante::A ? 3 : 8,
        };
    }
}
