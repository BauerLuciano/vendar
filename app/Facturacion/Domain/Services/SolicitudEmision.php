<?php

namespace App\Facturacion\Domain\Services;

use App\Facturacion\Domain\Entities\ComprobanteFiscal;
use App\Facturacion\Domain\Entities\DetalleFiscal;
use App\Facturacion\Domain\Entities\Emisor;
use App\Facturacion\Domain\Entities\PuntoVenta;
use App\Facturacion\Domain\Entities\Receptor;
use App\Facturacion\Domain\ValueObjects\Cae;
use App\Facturacion\Domain\ValueObjects\Concepto;
use App\Facturacion\Domain\ValueObjects\LetraComprobante;
use App\Facturacion\Domain\ValueObjects\TipoComprobante;

/**
 * Datos de entrada del caso de uso de emisión (arquitectura §7.1).
 * Los detalles ya llegan desglosados (snapshot de alícuota, invariante 12).
 */
final class SolicitudEmision
{
    /**
     * @param  DetalleFiscal[]  $detalles
     */
    public function __construct(
        private int $comercioId,
        private int $ventaId,
        private PuntoVenta $puntoVenta,
        private TipoComprobante $tipo,
        private Concepto $concepto,
        private Emisor $emisor,
        private array $detalles,
        private ?Receptor $receptor = null,
        private ?int $comprobanteOriginalId = null,
        private ?LetraComprobante $letra = null,
    ) {
        if ($detalles === []) {
            throw new \InvalidArgumentException('Una solicitud de emisión requiere al menos un detalle.');
        }
    }

    public function comercioId(): int
    {
        return $this->comercioId;
    }

    public function ventaId(): int
    {
        return $this->ventaId;
    }

    public function puntoVenta(): PuntoVenta
    {
        return $this->puntoVenta;
    }

    public function tipo(): TipoComprobante
    {
        return $this->tipo;
    }

    public function concepto(): Concepto
    {
        return $this->concepto;
    }

    public function emisor(): Emisor
    {
        return $this->emisor;
    }

    public function receptor(): ?Receptor
    {
        return $this->receptor;
    }

    public function comprobanteOriginalId(): ?int
    {
        return $this->comprobanteOriginalId;
    }

    /**
     * Letra fija de la solicitud (Notas de Crédito). Si es null, la letra se
     * determina con DeterminacionLetraRule en el caso de uso de emisión.
     */
    public function letra(): ?LetraComprobante
    {
        return $this->letra;
    }

    /**
     * @return DetalleFiscal[]
     */
    public function detalles(): array
    {
        return $this->detalles;
    }

    /**
     * Construye el comprobante del ledger con letra y número asignados.
     * El CAE puede quedar pendiente (null) mientras la emisión está en curso (§7.2).
     */
    public function construirComprobante(
        LetraComprobante $letra,
        int $numero,
        ?Cae $cae = null,
        ?int $comprobanteOriginalId = null,
    ): ComprobanteFiscal {
        return new ComprobanteFiscal(
            comercioId: $this->comercioId,
            ventaId: $this->ventaId,
            puntoVenta: $this->puntoVenta,
            tipo: $this->tipo,
            letra: $letra,
            concepto: $this->concepto,
            emisor: $this->emisor,
            cae: $cae,
            detalles: $this->detalles,
            receptor: $this->receptor,
            numero: $numero,
            comprobanteOriginalId: $comprobanteOriginalId ?? $this->comprobanteOriginalId,
        );
    }
}
