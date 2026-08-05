<?php

namespace App\Facturacion\Domain\Calculators;

use App\Facturacion\Domain\Entities\DetalleFiscal;
use App\Facturacion\Domain\ValueObjects\Alicuota;
use App\Facturacion\Domain\ValueObjects\DesgloseLinea;
use App\Facturacion\Domain\ValueObjects\Importe;

/**
 * Back-cálculo del desglose neto/IVA desde el precio final con IVA incluido
 * (arquitectura §4.2): neto = round(precio * cant / (1 + alicuota), 2),
 * iva = total_linea - neto. Garantiza total_linea = neto + iva.
 */
final class DesgloseIvaCalculator
{
    public function desglosarLinea(float $cantidad, Importe $precioUnitario, Alicuota $alicuota): DesgloseLinea
    {
        $precioUnitarioValor = $precioUnitario->valor();
        $totalLinea = RedondeoCalculator::redondear($precioUnitarioValor * $cantidad);

        if ($alicuota->esExenta()) {
            $neto = $totalLinea;
        } else {
            $neto = RedondeoCalculator::redondear($totalLinea / $alicuota->factor());
        }

        $iva = RedondeoCalculator::ivaDesdeTotal($totalLinea, $neto);

        return new DesgloseLinea(
            new Importe($neto),
            new Importe($iva)
        );
    }

    /**
     * Construye el detalle fiscal de la venta a partir de cantidad + precio con IVA.
     * El snapshot de alícuota queda fijado en el detalle (invariante 12).
     */
    public function construirDetalle(float $cantidad, Importe $precioUnitario, Alicuota $alicuota): DetalleFiscal
    {
        $desglose = $this->desglosarLinea($cantidad, $precioUnitario, $alicuota);

        return new DetalleFiscal(
            $cantidad,
            $precioUnitario,
            $alicuota,
            $desglose->neto(),
            $desglose->iva()
        );
    }
}
