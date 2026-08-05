<?php

namespace App\Facturacion\Domain\Rules;

use App\Facturacion\Domain\Entities\ComprobanteFiscal;
use App\Facturacion\Domain\Exceptions\ReglaNotaCreditoException;
use App\Facturacion\Domain\ValueObjects\Importe;

/**
 * Reglas de Nota de Crédito (arquitectura §8): NC total para anulaciones y
 * NC parcial (proporcional al monto devuelto) para devoluciones.
 */
final class ReglasNotaCredito
{
    /**
     * Monto de la NC total: igual al total del comprobante original.
     */
    public function montoNcTotal(ComprobanteFiscal $comprobanteOriginal): Importe
    {
        return $comprobanteOriginal->total();
    }

    /**
     * Monto de la NC parcial: debe ser positivo y no superar el total original.
     */
    public function montoNcParcial(ComprobanteFiscal $comprobanteOriginal, Importe $montoDevuelto): Importe
    {
        if ($montoDevuelto->esMenorOIgualQue(Importe::cero())) {
            throw new ReglaNotaCreditoException('El monto de la devolución debe ser mayor a cero.');
        }

        if ($montoDevuelto->esMayorQue($comprobanteOriginal->total())) {
            throw new ReglaNotaCreditoException(
                'El monto de la devolución no puede superar el total del comprobante original.'
            );
        }

        return $montoDevuelto;
    }
}
