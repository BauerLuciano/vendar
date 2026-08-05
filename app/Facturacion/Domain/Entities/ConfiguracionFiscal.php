<?php

namespace App\Facturacion\Domain\Entities;

use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Domain\ValueObjects\EstadoModuloFiscal;

/**
 * Configuración fiscal de un comercio (fuente: configuracion_fiscal_comercios).
 * DTO del dominio: no es un modelo Eloquent (arquitectura §3).
 */
final class ConfiguracionFiscal
{
    public function __construct(
        private int $comercioId,
        private ?Cuit $cuit,
        private ?string $razonSocial,
        private ?CondicionFiscal $condicionFiscal,
        private ?string $domicilioFiscal,
        private string $entorno,
        private ?int $puntoVentaActivo,
        private EstadoModuloFiscal $estadoModulo,
        private ?int $certificadoId = null,
        private float $alicuotaIvaRecargo = 21.0,
    ) {}

    public function comercioId(): int
    {
        return $this->comercioId;
    }

    public function cuit(): ?Cuit
    {
        return $this->cuit;
    }

    public function razonSocial(): ?string
    {
        return $this->razonSocial;
    }

    public function condicionFiscal(): ?CondicionFiscal
    {
        return $this->condicionFiscal;
    }

    public function domicilioFiscal(): ?string
    {
        return $this->domicilioFiscal;
    }

    public function entorno(): string
    {
        return $this->entorno;
    }

    public function puntoVentaActivo(): ?int
    {
        return $this->puntoVentaActivo;
    }

    public function estadoModulo(): EstadoModuloFiscal
    {
        return $this->estadoModulo;
    }

    public function certificadoId(): ?int
    {
        return $this->certificadoId;
    }

    /**
     * Alícuota de IVA aplicable al recargo por tarjeta (arquitectura §10,
     * regla configurable por comercio; no es una verdad absoluta de la normativa).
     */
    public function alicuotaIvaRecargo(): float
    {
        return $this->alicuotaIvaRecargo;
    }

    public function estaListoParaFacturar(): bool
    {
        return $this->estadoModulo->esListoParaFacturar();
    }

    /**
     * Reconstruye la configuración con los overrides dados (DTO inmutable).
     * El wizard de configuración (F7) la usa para persistir cada paso.
     *
     * @param array{
     *   comercioId?: int, cuit?: ?Cuit, razonSocial?: ?string,
     *   condicionFiscal?: ?CondicionFiscal, domicilioFiscal?: ?string,
     *   entorno?: string, puntoVentaActivo?: ?int, estadoModulo?: EstadoModuloFiscal,
     *   certificadoId?: ?int, alicuotaIvaRecargo?: float
     * } $overrides
     */
    public function con(array $overrides): self
    {
        $valor = fn (string $clave, mixed $defecto) => array_key_exists($clave, $overrides) ? $overrides[$clave] : $defecto;

        return new self(
            comercioId: $valor('comercioId', $this->comercioId),
            cuit: $valor('cuit', $this->cuit),
            razonSocial: $valor('razonSocial', $this->razonSocial),
            condicionFiscal: $valor('condicionFiscal', $this->condicionFiscal),
            domicilioFiscal: $valor('domicilioFiscal', $this->domicilioFiscal),
            entorno: $valor('entorno', $this->entorno),
            puntoVentaActivo: $valor('puntoVentaActivo', $this->puntoVentaActivo),
            estadoModulo: $valor('estadoModulo', $this->estadoModulo),
            certificadoId: $valor('certificadoId', $this->certificadoId),
            alicuotaIvaRecargo: $valor('alicuotaIvaRecargo', $this->alicuotaIvaRecargo),
        );
    }
}
