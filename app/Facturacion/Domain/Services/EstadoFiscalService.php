<?php

namespace App\Facturacion\Domain\Services;

use App\Facturacion\Domain\Exceptions\FacturacionDomainException;
use App\Facturacion\Domain\ValueObjects\EstadoModuloFiscal;

/**
 * Máquina de estados del módulo fiscal (arquitectura §15.1).
 * Pura: no depende de Laravel ni de la base de datos.
 */
final class EstadoFiscalService
{
    /**
     * @var EstadoModuloFiscal[]
     */
    private const SECUENCIA = [
        EstadoModuloFiscal::SIN_DATOS,
        EstadoModuloFiscal::DATOS_CARGADOS,
        EstadoModuloFiscal::DATOS_VALIDADOS,
        EstadoModuloFiscal::CERT_CARGADO,
        EstadoModuloFiscal::PV_HABILITADO,
        EstadoModuloFiscal::LISTO_PARA_FACTURAR,
    ];

    /**
     * Avanza al siguiente estado de la secuencia normal.
     */
    public function avanzar(EstadoModuloFiscal $actual): EstadoModuloFiscal
    {
        $posicion = array_search($actual, self::SECUENCIA, true);

        if ($posicion === false || $posicion === count(self::SECUENCIA) - 1) {
            throw new FacturacionDomainException(
                "El estado {$actual->value} no admite avanzar en la secuencia del módulo."
            );
        }

        return self::SECUENCIA[$posicion + 1];
    }

    /**
     * Lleva el módulo a un estado de falla recuperable.
     */
    public function fallar(EstadoModuloFiscal $actual, EstadoModuloFiscal $falla): EstadoModuloFiscal
    {
        if (! $falla->esFalla()) {
            throw new \InvalidArgumentException("El estado {$falla->value} no es un estado de falla válido.");
        }

        return $falla;
    }

    /**
     * Marca el módulo como no soportado (emisor monotributista, §1.2).
     * Estado terminal: no admite remediación dentro del alcance.
     */
    public function marcarNoSoportado(): EstadoModuloFiscal
    {
        return EstadoModuloFiscal::NO_SOPORTADO;
    }

    /**
     * Reanuda la secuencia normal desde el estado actual si es una falla recuperable
     * o un estado de la secuencia; los terminales (no_soportado) no reanudan.
     */
    public function reanudar(EstadoModuloFiscal $actual): EstadoModuloFiscal
    {
        if ($actual->esTerminal()) {
            throw new FacturacionDomainException(
                'El módulo está en estado no_soportado: no puede reanudar la emisión en el MVP.'
            );
        }

        return $actual;
    }

    public function esEstadoDeFalla(EstadoModuloFiscal $estado): bool
    {
        return $estado->esFalla();
    }

    public function esTerminal(EstadoModuloFiscal $estado): bool
    {
        return $estado->esTerminal();
    }
}
