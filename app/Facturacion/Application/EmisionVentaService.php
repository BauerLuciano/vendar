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
use App\Facturacion\Domain\Services\EmisionService;
use App\Facturacion\Domain\Services\NumeracionService;
use App\Facturacion\Domain\Services\SolicitudEmision;
use App\Facturacion\Domain\ValueObjects\Alicuota;
use App\Facturacion\Domain\ValueObjects\Concepto;
use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Facturacion\Domain\ValueObjects\Importe;
use App\Facturacion\Domain\ValueObjects\LetraComprobante;
use App\Facturacion\Domain\ValueObjects\TipoComprobante;
use App\Facturacion\Infrastructure\Arca\Exceptions\CredencialPlataformaNoConfiguradaException;
use App\Models\Consumidor;
use App\Models\Venta;
use Throwable;

/**
 * Caso de uso de aplicación F5: emite el comprobante fiscal de una venta del POS
 * dentro de la transacción que la completa, solo si el módulo está listo para
 * facturar (invariante 1). Comercios sin módulo no cambian su comportamiento.
 */
final class EmisionVentaService
{
    public function __construct(
        private ConfiguracionFiscalRepository $configuracion,
        private ComprobanteFiscalRepository $repositorio,
        private ElegibilidadEmisorRule $elegibilidad,
        private DeterminacionLetraRule $letra,
        private NumeracionService $numeracion,
        private DesgloseIvaCalculator $desglose,
        private WsfetResolver $wsfetResolver,
        private PadronResolver $padronResolver,
    ) {}

    /**
     * Emite si corresponde. Devuelve el comprobante persistido o null cuando el
     * módulo del comercio no está activo (comportamiento actual sin factura).
     *
     * @throws EmisionVentaException cuando la emisión es obligatoria y falla
     *                               (la venta no se completa, invariante 1).
     */
    public function emitirSiCorresponde(Venta $venta): ?ComprobanteFiscal
    {
        $configuracion = $this->configuracionDe($venta);

        if ($configuracion === null || ! $configuracion->estaListoParaFacturar()) {
            return null;
        }

        $emisor = $this->emisor($configuracion);

        if (! $emisor->esElegible()) {
            return null;
        }

        $receptor = $this->receptor($venta->consumidor, $configuracion);
        $detalles = $this->detallesFiscales($venta, $configuracion);

        $emision = new EmisionService(
            $this->configuracion,
            $this->repositorio,
            $this->elegibilidad,
            $this->letra,
            $this->numeracion,
            $this->wsfetResolver->resolver($configuracion),
        );

        return $emision->emitir($this->solicitud($configuracion, $venta, $emisor, $receptor, $detalles));
    }

    /**
     * Determina la letra que tendría el comprobante de una venta para el
     * consumidor dado, sin emitir. Devuelve null si el módulo no está listo.
     * Permite al POS exigir los datos del receptor antes de cobrar (§5).
     */
    public function letraEsperada(int $comercioId, ?Consumidor $consumidor): ?LetraComprobante
    {
        $configuracion = $this->configuracion->buscarPorComercio($comercioId);

        if ($configuracion === null || ! $configuracion->estaListoParaFacturar()) {
            return null;
        }

        $emisor = $this->emisor($configuracion);

        if (! $emisor->esElegible()) {
            return null;
        }

        $receptor = $this->receptor($consumidor, $configuracion);

        return $this->letra->determinar($emisor, $receptor);
    }

    private function configuracionDe(Venta $venta): ?ConfiguracionFiscal
    {
        $comercioId = $venta->turno?->caja?->sucursal?->comercio_id
            ?? $venta->loadMissing('turno.caja.sucursal')->turno->caja->sucursal->comercio_id;

        if ($comercioId === null) {
            return null;
        }

        return $this->configuracion->buscarPorComercio((int) $comercioId);
    }

    private function emisor(ConfiguracionFiscal $configuracion): Emisor
    {
        $cuit = $configuracion->cuit();
        $condicion = $configuracion->condicionFiscal();

        if ($cuit === null || $condicion === null) {
            throw new EmisionVentaException('La configuración fiscal del comercio está incompleta (CUIT o condición fiscal).');
        }

        return new Emisor($cuit, $configuracion->razonSocial() ?? '', $condicion);
    }

    private function receptor(?Consumidor $consumidor, ConfiguracionFiscal $configuracion): ?Receptor
    {
        if ($consumidor === null || empty($consumidor->cuit)) {
            return null;
        }

        $cuit = new Cuit($consumidor->cuit);

        try {
            $respuesta = $this->padronResolver->para($configuracion)->consultar($cuit);
        } catch (CredencialPlataformaNoConfiguradaException) {
            throw new EmisionVentaException(
                'Para facturar a un cliente con CUIT se requiere la credencial de padrón ARCA (prerequisito de entorno).'
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

        $razonSocial = $consumidor->razon_social ?: ($respuesta['nombre'] ?? null);

        return new Receptor($cuit, $razonSocial, $consumidor->domicilio_fiscal, $condicion);
    }

    /**
     * @return DetalleFiscal[]
     */
    private function detallesFiscales(Venta $venta, ConfiguracionFiscal $configuracion): array
    {
        $detalles = [];

        foreach ($venta->detalles()->get() as $detalle) {
            $alicuota = $detalle->alicuota_iva;

            if ($alicuota === null || $alicuota === '') {
                throw new EmisionVentaException(
                    "El producto \"{$detalle->producto?->nombre}\" no tiene alícuota de IVA cargada (detalle #{$detalle->id})."
                );
            }

            $detalles[] = $this->desglose->construirDetalle(
                (float) $detalle->cantidad,
                new Importe((float) $detalle->precio_unitario),
                new Alicuota((float) $alicuota),
            );
        }

        $recargo = (float) ($venta->recargo_monto ?? 0);

        if ($recargo > 0) {
            // Arquitectura §10: el recargo se incluye en el comprobante con su
            // alícuota configurable por comercio, para que lo facturado coincida
            // con lo cobrado en caja (F4).
            $detalles[] = $this->desglose->construirDetalle(
                1.0,
                new Importe($recargo),
                new Alicuota($configuracion->alicuotaIvaRecargo()),
            );
        }

        return $detalles;
    }

    private function solicitud(
        ConfiguracionFiscal $configuracion,
        Venta $venta,
        Emisor $emisor,
        ?Receptor $receptor,
        array $detalles,
    ): SolicitudEmision {
        $puntoVenta = $configuracion->puntoVentaActivo();

        if ($puntoVenta === null) {
            throw new EmisionVentaException('El comercio no tiene un punto de venta activo configurado.');
        }

        return new SolicitudEmision(
            comercioId: $configuracion->comercioId(),
            ventaId: $venta->id,
            puntoVenta: new PuntoVenta($puntoVenta),
            tipo: TipoComprobante::FACTURA,
            concepto: Concepto::PRODUCTOS,
            emisor: $emisor,
            detalles: $detalles,
            receptor: $receptor,
        );
    }
}
