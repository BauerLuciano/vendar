<?php

namespace App\Facturacion\Application;

use App\Facturacion\Application\Contracts\PadronResolver;
use App\Facturacion\Application\Contracts\WsfetResolver;
use App\Facturacion\Application\Exceptions\EmisionVentaException;
use App\Facturacion\Domain\Calculators\DesgloseIvaCalculator;
use App\Facturacion\Domain\Contracts\ComprobanteFiscalRepository;
use App\Facturacion\Domain\Contracts\ConfiguracionFiscalRepository;
use App\Facturacion\Domain\Entities\ComprobanteFiscal;
use App\Facturacion\Domain\Entities\ConfiguracionFiscal;
use App\Facturacion\Domain\Entities\DetalleFiscal;
use App\Facturacion\Domain\Entities\Emisor;
use App\Facturacion\Domain\Entities\PuntoVenta;
use App\Facturacion\Domain\Entities\Receptor;
use App\Facturacion\Domain\Rules\DeterminacionLetraRule;
use App\Facturacion\Domain\Rules\ElegibilidadEmisorRule;
use App\Facturacion\Domain\Rules\ReglasNotaCredito;
use App\Facturacion\Domain\Services\EmisionService;
use App\Facturacion\Domain\Services\NumeracionService;
use App\Facturacion\Domain\Services\SolicitudEmision;
use App\Facturacion\Domain\ValueObjects\Concepto;
use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Domain\ValueObjects\Importe;
use App\Facturacion\Domain\ValueObjects\TipoComprobante;
use App\Facturacion\Infrastructure\Arca\Exceptions\CredencialPlataformaNoConfiguradaException;
use App\Models\Venta;
use Throwable;

/**
 * Caso de uso de aplicación F6: emite la Nota de Crédito (total para
 * anulaciones, parcial para devoluciones) dentro de la transacción que
 * anula/devuelve, referenciando el comprobante original (invariante 2).
 *
 * La NC no reafecta stock ni caja (invariantes 3 y 4): eso lo sigue haciendo
 * el flujo actual de anulación/devolución. Si la emisión de la NC falla, la
 * excepción propaga y la anulación/devolución no se concreta.
 */
final class NcService
{
    public function __construct(
        private ConfiguracionFiscalRepository $configuracion,
        private ComprobanteFiscalRepository $repositorio,
        private ElegibilidadEmisorRule $elegibilidad,
        private DeterminacionLetraRule $letra,
        private NumeracionService $numeracion,
        private DesgloseIvaCalculator $desglose,
        private ReglasNotaCredito $reglasNc,
        private WsfetResolver $wsfetResolver,
        private PadronResolver $padronResolver,
    ) {}

    /**
     * Emite la NC si la venta tiene un comprobante emitido. Devuelve null cuando
     * la venta no está facturada (el flujo actual no cambia).
     *
     * @param  float|null  $montoDevuelto  null para NC total (anulación), importe
     *                                     devuelto para NC parcial (devolución).
     *
     * @throws EmisionVentaException cuando la emisión de la NC es obligatoria y
     *                               falla (la anulación/devolución no se concreta).
     */
    public function emitirNcSiCorresponde(Venta $venta, ?float $montoDevuelto = null): ?ComprobanteFiscal
    {
        $comercioId = $this->comercioDe($venta);

        if ($comercioId === null) {
            return null;
        }

        $original = $this->repositorio->buscarPorVenta((int) $venta->id, $comercioId);

        if ($original === null || ! $original->esEmitido()) {
            return null;
        }

        $configuracion = $this->configuracion->buscarPorComercio($comercioId);

        if ($configuracion === null || ! $configuracion->estaListoParaFacturar()) {
            throw new EmisionVentaException(
                'El módulo fiscal del comercio no está listo para facturar la Nota de Crédito de la venta #'.$venta->id.'.'
            );
        }

        $monto = $montoDevuelto === null
            ? $this->reglasNc->montoNcTotal($original)
            : $this->reglasNc->montoNcParcial($original, new Importe($montoDevuelto));

        $detalles = $this->detallesNc($original, $monto);
        $receptor = $this->receptorDe($venta, $configuracion);

        $emision = new EmisionService(
            $this->configuracion,
            $this->repositorio,
            $this->elegibilidad,
            $this->letra,
            $this->numeracion,
            $this->wsfetResolver->resolver($configuracion),
        );

        try {
            return $emision->emitir($this->solicitudNc($configuracion, $venta, $original, $receptor, $detalles));
        } catch (EmisionVentaException $e) {
            throw $e;
        } catch (Throwable $e) {
            // F8: cualquier fallo de la emisión (ARCA, WSAA, SOAP o reglas de
            // dominio) se traduce en la frontera de aplicación para que la
            // operación revierta y el pendiente quede registrado para reintento.
            throw new EmisionVentaException($e->getMessage());
        }
    }

    private function comercioDe(Venta $venta): ?int
    {
        $comercioId = $venta->turno?->caja?->sucursal?->comercio_id
            ?? $venta->loadMissing('turno.caja.sucursal')->turno->caja->sucursal->comercio_id;

        return $comercioId === null ? null : (int) $comercioId;
    }

    private function emisor(ConfiguracionFiscal $configuracion): Emisor
    {
        $cuit = $configuracion->cuit();
        $condicion = $configuracion->condicionFiscal();

        if ($cuit === null || $condicion === null) {
            throw new EmisionVentaException(
                'La configuración fiscal del comercio está incompleta (CUIT o condición fiscal).'
            );
        }

        return new Emisor($cuit, $configuracion->razonSocial() ?? '', $condicion);
    }

    private function receptorDe(Venta $venta, ConfiguracionFiscal $configuracion): ?Receptor
    {
        $consumidor = $venta->consumidor;

        if ($consumidor === null || empty($consumidor->cuit)) {
            return null;
        }

        $cuit = new Cuit($consumidor->cuit);

        try {
            $respuesta = $this->padronResolver->para($configuracion)->consultar($cuit);
        } catch (CredencialPlataformaNoConfiguradaException) {
            throw new EmisionVentaException(
                'Para facturar la Nota de Crédito de un cliente con CUIT se requiere la credencial de padrón ARCA.'
            );
        } catch (Throwable $e) {
            throw new EmisionVentaException('No se pudo consultar el padrón ARCA del cliente: '.$e->getMessage());
        }

        $estado = strtoupper((string) ($respuesta['estado'] ?? ''));
        if ($estado !== '' && $estado !== 'ACTIVO') {
            throw new EmisionVentaException('El CUIT del cliente no está activo en ARCA.');
        }

        $condicion = CondicionFiscal::tryFrom((string) ($respuesta['condicion_fiscal'] ?? ''))
            ?? CondicionFiscal::CONSUMIDOR_FINAL;

        return new Receptor($cuit, $consumidor->razon_social, $consumidor->domicilio_fiscal, $condicion);
    }

    /**
     * Desglose de la NC. Para NC total se reutilizan los detalles del original
     * (misma alícuota, invariante 12). Para NC parcial se escalan los detalles
     * proporcionalmente al monto devuelto (redondeo conforme a arquitectura §4.4).
     *
     * @return DetalleFiscal[]
     */
    private function detallesNc(ComprobanteFiscal $original, Importe $monto): array
    {
        if ($monto->esIgual($original->total())) {
            return $original->detalles();
        }

        $factor = $monto->valor() / $original->total()->valor();

        $detalles = [];

        foreach ($original->detalles() as $detalle) {
            $detalles[] = $this->desglose->construirDetalle(
                $detalle->cantidad(),
                new Importe($detalle->precioUnitario()->valor() * $factor),
                $detalle->alicuota(),
            );
        }

        return $detalles;
    }

    /**
     * @param  DetalleFiscal[]  $detalles
     */
    private function solicitudNc(
        ConfiguracionFiscal $configuracion,
        Venta $venta,
        ComprobanteFiscal $original,
        ?Receptor $receptor,
        array $detalles,
    ): SolicitudEmision {
        $puntoVenta = $configuracion->puntoVentaActivo();

        if ($puntoVenta === null) {
            throw new EmisionVentaException('El comercio no tiene un punto de venta activo configurado.');
        }

        if ($original->id() === null) {
            throw new EmisionVentaException('No se pudo referenciar el comprobante original de la Nota de Crédito.');
        }

        return new SolicitudEmision(
            comercioId: $configuracion->comercioId(),
            ventaId: (int) $venta->id,
            puntoVenta: new PuntoVenta($puntoVenta),
            tipo: TipoComprobante::NOTA_CREDITO,
            concepto: Concepto::PRODUCTOS,
            emisor: $this->emisor($configuracion),
            detalles: $detalles,
            receptor: $receptor,
            comprobanteOriginalId: $original->id(),
            letra: $original->letra(),
        );
    }
}
