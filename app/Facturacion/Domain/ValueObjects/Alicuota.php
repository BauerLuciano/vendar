<?php

namespace App\Facturacion\Domain\ValueObjects;

use App\Facturacion\Domain\Exceptions\AlicuotaInvalidaException;

/**
 * Alícuota de IVA expresada en porcentaje (p. ej. 21, 10.5, 0).
 * La alícuota 0 representa operaciones exentas/no gravadas.
 */
final class Alicuota
{
    private float $valor;

    public function __construct(float $valor)
    {
        if ($valor < 0) {
            throw new AlicuotaInvalidaException("La alícuota no puede ser negativa: {$valor}.");
        }

        $this->valor = $valor;
    }

    public static function general(): self
    {
        return new self(21.0);
    }

    public function valor(): float
    {
        return $this->valor;
    }

    /**
     * Factor para back-cálculo: 1 + (alicuota / 100).
     * Con alícuota 21 devuelve 1.21.
     */
    public function factor(): float
    {
        return 1 + ($this->valor / 100);
    }

    public function esExenta(): bool
    {
        return $this->valor == 0.0;
    }

    public function esIgual(Alicuota $otra): bool
    {
        return abs($this->valor - $otra->valor) < 0.0001;
    }

    public function __toString(): string
    {
        $valor = number_format($this->valor, 2, '.', '');

        return rtrim(rtrim($valor, '0'), '.');
    }
}
