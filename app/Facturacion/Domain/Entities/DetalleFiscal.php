<?php

namespace App\Facturacion\Domain\Entities;

use App\Facturacion\Domain\ValueObjects\Alicuota;
use App\Facturacion\Domain\ValueObjects\Importe;

/**
 * Línea fiscal de un comprobante. Conserva el snapshot de alícuota al vender
 * (invariante 12): el IVA nunca se recalcula desde el producto.
 */
final class DetalleFiscal
{
    private float $cantidad;

    private Importe $precioUnitario;

    private Alicuota $alicuota;

    private Importe $neto;

    private Importe $iva;

    public function __construct(
        float $cantidad,
        Importe $precioUnitario,
        Alicuota $alicuota,
        Importe $neto,
        Importe $iva
    ) {
        $this->cantidad = $cantidad;
        $this->precioUnitario = $precioUnitario;
        $this->alicuota = $alicuota;
        $this->neto = $neto;
        $this->iva = $iva;
    }

    public function cantidad(): float
    {
        return $this->cantidad;
    }

    public function precioUnitario(): Importe
    {
        return $this->precioUnitario;
    }

    public function alicuota(): Alicuota
    {
        return $this->alicuota;
    }

    public function neto(): Importe
    {
        return $this->neto;
    }

    public function iva(): Importe
    {
        return $this->iva;
    }

    public function totalLinea(): Importe
    {
        return $this->neto->sumar($this->iva);
    }
}
