<?php

namespace App\Facturacion\Domain\Entities;

use App\Facturacion\Domain\Calculators\TotalesFiscalesCalculator;
use App\Facturacion\Domain\ValueObjects\Cae;
use App\Facturacion\Domain\ValueObjects\Concepto;
use App\Facturacion\Domain\ValueObjects\EstadoComprobante;
use App\Facturacion\Domain\ValueObjects\Importe;
use App\Facturacion\Domain\ValueObjects\LetraComprobante;
use App\Facturacion\Domain\ValueObjects\TipoComprobante;
use App\Facturacion\Domain\ValueObjects\TotalesFiscales;
use DateTimeImmutable;

/**
 * Comprobante fiscal del ledger inmutable (arquitectura §18.2).
 * El ledger solo se inserta; una vez emitido no se modifica ni se borra (invariante 6).
 */
final class ComprobanteFiscal
{
    private ?int $id;

    private int $comercioId;

    private int $ventaId;

    private PuntoVenta $puntoVenta;

    private TipoComprobante $tipo;

    private LetraComprobante $letra;

    private ?int $numero;

    private Concepto $concepto;

    private Emisor $emisor;

    private ?Receptor $receptor;

    private ?Cae $cae;

    private EstadoComprobante $estado;

    /** @var DetalleFiscal[] */
    private array $detalles;

    private ?int $comprobanteOriginalId;

    private ?string $qr;

    private TotalesFiscalesCalculator $totalesCalculator;

    /**
     * @param  DetalleFiscal[]  $detalles
     */
    public function __construct(
        int $comercioId,
        int $ventaId,
        PuntoVenta $puntoVenta,
        TipoComprobante $tipo,
        LetraComprobante $letra,
        Concepto $concepto,
        Emisor $emisor,
        ?Cae $cae = null,
        array $detalles = [],
        ?Receptor $receptor = null,
        ?int $numero = null,
        ?int $comprobanteOriginalId = null,
        EstadoComprobante $estado = EstadoComprobante::EMITIDO
    ) {
        if ($detalles === []) {
            throw new \InvalidArgumentException('Un comprobante fiscal requiere al menos un detalle.');
        }

        $this->comercioId = $comercioId;
        $this->ventaId = $ventaId;
        $this->puntoVenta = $puntoVenta;
        $this->tipo = $tipo;
        $this->letra = $letra;
        $this->concepto = $concepto;
        $this->emisor = $emisor;
        $this->receptor = $receptor;
        $this->cae = $cae;
        $this->estado = $estado;
        $this->detalles = array_values($detalles);
        $this->numero = $numero;
        $this->comprobanteOriginalId = $comprobanteOriginalId;
        $this->totalesCalculator = new TotalesFiscalesCalculator;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function asignarId(int $id): void
    {
        $this->id = $id;
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

    public function letra(): LetraComprobante
    {
        return $this->letra;
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

    public function cae(): ?Cae
    {
        return $this->cae;
    }

    public function vencimientoCae(): DateTimeImmutable
    {
        if ($this->cae === null) {
            throw new \LogicException('El comprobante aún no tiene CAE asignado.');
        }

        return $this->cae->vencimiento();
    }

    public function estado(): EstadoComprobante
    {
        return $this->estado;
    }

    public function numero(): ?int
    {
        return $this->numero;
    }

    /**
     * Número fiscal completo en el formato estándar ARCA (punto de venta -
     * número). Es la única fuente de verdad que usan las vistas fiscales y el
     * historial (arquitectura §18.2).
     */
    public function numeroCompleto(): string
    {
        return sprintf('%04d-%08d', $this->puntoVenta->numero(), (int) $this->numero);
    }

    public function comprobanteOriginalId(): ?int
    {
        return $this->comprobanteOriginalId;
    }

    public function qr(): ?string
    {
        return $this->qr;
    }

    public function asignarQr(string $qr): void
    {
        $this->qr = $qr;
    }

    public function detalles(): array
    {
        return $this->detalles;
    }

    public function esEmitido(): bool
    {
        return $this->estado === EstadoComprobante::EMITIDO;
    }

    public function esNotaCredito(): bool
    {
        return $this->tipo->esNotaCredito();
    }

    public function totales(): TotalesFiscales
    {
        return $this->totalesCalculator->calcular($this->detalles);
    }

    public function neto(): Importe
    {
        return $this->totales()->neto();
    }

    public function iva(): Importe
    {
        return $this->totales()->iva();
    }

    public function total(): Importe
    {
        return $this->totales()->total();
    }
}
