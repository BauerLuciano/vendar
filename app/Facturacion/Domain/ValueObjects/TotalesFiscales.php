<?php

namespace App\Facturacion\Domain\ValueObjects;

/**
 * Totales fiscales de un comprobante: neto, IVA y total, con la regla
 * total = neto + iva (invariante 11). El ajuste de redondeo recae sobre el IVA.
 */
final class TotalesFiscales
{
    private Importe $neto;

    private Importe $iva;

    private Importe $total;

    public function __construct(Importe $neto, Importe $iva)
    {
        $this->neto = $neto;
        $this->iva = $iva;
        $this->total = $neto->sumar($iva);
    }

    public function neto(): Importe
    {
        return $this->neto;
    }

    public function iva(): Importe
    {
        return $this->iva;
    }

    public function total(): Importe
    {
        return $this->total;
    }
}
