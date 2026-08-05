<?php

namespace App\Facturacion\Domain\Entities;

/**
 * Punto de venta habilitado en ARCA. El MVP soporta 1 PV por comercio (§21).
 */
final class PuntoVenta
{
    private int $numero;

    private bool $habilitado;

    public function __construct(int $numero, bool $habilitado = true)
    {
        if ($numero <= 0) {
            throw new \InvalidArgumentException('El número de punto de venta debe ser positivo.');
        }

        $this->numero = $numero;
        $this->habilitado = $habilitado;
    }

    public function numero(): int
    {
        return $this->numero;
    }

    public function estaHabilitado(): bool
    {
        return $this->habilitado;
    }
}
