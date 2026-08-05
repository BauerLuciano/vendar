<?php

namespace App\Facturacion\Domain\Calculators;

use App\Facturacion\Domain\Entities\DetalleFiscal;
use App\Facturacion\Domain\ValueObjects\Importe;
use App\Facturacion\Domain\ValueObjects\TotalesFiscales;

/**
 * Construye los totales de un comprobante a partir de sus detalles.
 * La suma del neto se acumula y el IVA total absorbe el redondeo residual:
 * total = neto_total + iva_total (invariante 11).
 */
final class TotalesFiscalesCalculator
{
    /**
     * @param  DetalleFiscal[]  $detalles
     */
    public function calcular(array $detalles): TotalesFiscales
    {
        $netoTotal = 0.0;
        $ivaTotal = 0.0;

        foreach ($detalles as $detalle) {
            $netoTotal += $detalle->neto()->valor();
            $ivaTotal += $detalle->iva()->valor();
        }

        $netoTotal = RedondeoCalculator::redondear($netoTotal);
        $ivaTotal = RedondeoCalculator::redondear($ivaTotal);

        // total = neto + iva, con ajuste sobre el IVA (no sobre el neto).
        return new TotalesFiscales(
            new Importe($netoTotal),
            new Importe($ivaTotal)
        );
    }

    public function calcularDesdeDesgloses(array $desgloses): TotalesFiscales
    {
        $netoTotal = 0.0;
        $ivaTotal = 0.0;

        foreach ($desgloses as $desglose) {
            $netoTotal += $desglose->neto()->valor();
            $ivaTotal += $desglose->iva()->valor();
        }

        return new TotalesFiscales(
            new Importe(RedondeoCalculator::redondear($netoTotal)),
            new Importe(RedondeoCalculator::redondear($ivaTotal))
        );
    }
}
