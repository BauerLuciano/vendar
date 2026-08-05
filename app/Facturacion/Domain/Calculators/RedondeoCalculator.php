<?php

namespace App\Facturacion\Domain\Calculators;

/**
 * Regla de redondeo fiscal (arquitectura §4.4): redondeo a 2 decimales.
 * La validación final exige total = neto + iva; el ajuste recae sobre el IVA.
 */
final class RedondeoCalculator
{
    public static function redondear(float $valor): float
    {
        return round($valor, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * Devuelve el IVA que absorbe el redondeo de una línea: iva = total_linea - neto.
     */
    public static function ivaDesdeTotal(float $totalLinea, float $neto): float
    {
        return self::redondear($totalLinea - $neto);
    }
}
