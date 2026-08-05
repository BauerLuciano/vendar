<?php

namespace App\Facturacion\Domain\ValueObjects;

use App\Facturacion\Domain\Calculators\RedondeoCalculator;
use App\Facturacion\Domain\Exceptions\ImporteInvalidoException;

/**
 * Importe monetario con precisión de 2 decimales.
 * Toda operación re-redondea a 2 decimales (arquitectura §4.4).
 */
final class Importe
{
    private float $valor;

    public function __construct(float $valor)
    {
        if (! is_finite($valor)) {
            throw new ImporteInvalidoException('El importe debe ser un número finito.');
        }

        $this->valor = RedondeoCalculator::redondear($valor);
    }

    public static function cero(): self
    {
        return new self(0.0);
    }

    public function valor(): float
    {
        return $this->valor;
    }

    public function sumar(Importe $otro): self
    {
        return new self($this->valor + $otro->valor);
    }

    public function restar(Importe $otro): self
    {
        return new self($this->valor - $otro->valor);
    }

    public function multiplicarPor(float $factor): self
    {
        return new self($this->valor * $factor);
    }

    public function dividirPor(float $divisor): self
    {
        if ($divisor == 0.0) {
            throw new ImporteInvalidoException('No se puede dividir un importe por cero.');
        }

        return new self($this->valor / $divisor);
    }

    public function esIgual(Importe $otro): bool
    {
        return $this->valor == $otro->valor;
    }

    public function esCero(): bool
    {
        return $this->valor == 0.0;
    }

    public function esMayorQue(Importe $otro): bool
    {
        return $this->valor > $otro->valor;
    }

    public function esMayorOIgualQue(Importe $otro): bool
    {
        return $this->valor >= $otro->valor;
    }

    public function esMenorOIgualQue(Importe $otro): bool
    {
        return $this->valor <= $otro->valor;
    }

    public function __toString(): string
    {
        return number_format($this->valor, 2, '.', '');
    }
}
