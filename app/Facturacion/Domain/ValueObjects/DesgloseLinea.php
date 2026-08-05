<?php

namespace App\Facturacion\Domain\ValueObjects;

/**
 * Resultado del desglose de un ítem: garantiza total_linea = neto + iva (invariante 11).
 */
final class DesgloseLinea
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
