<?php

namespace App\Facturacion\Domain\Services;

use App\Facturacion\Domain\Contracts\ComprobanteFiscalRepository;
use App\Facturacion\Domain\Contracts\ConfiguracionFiscalRepository;
use App\Facturacion\Domain\Contracts\Wsfet;
use App\Facturacion\Domain\Entities\ComprobanteFiscal;
use App\Facturacion\Domain\Exceptions\ComercioNoListoException;
use App\Facturacion\Domain\Exceptions\EmisorNoElegibleException;
use App\Facturacion\Domain\Exceptions\ReceptorNoAptoException;
use App\Facturacion\Domain\Rules\DeterminacionLetraRule;
use App\Facturacion\Domain\Rules\ElegibilidadEmisorRule;
use App\Facturacion\Domain\ValueObjects\LetraComprobante;

/**
 * Caso de uso principal de emisión (arquitectura §7.1):
 * valida estado del comercio → determinación de letra → numeración
 * (lockForUpdate) → solicitud de CAE → persistencia en el ledger.
 *
 * El CAE se persiste dentro de la misma operación que lo obtiene; si la
 * solicitud falla, la excepción se propaga y la venta no se completa
 * (invariante 1). La resolución de CAE perdido queda en CaePerdidoHandler.
 */
final class EmisionService
{
    public function __construct(
        private ConfiguracionFiscalRepository $configuracion,
        private ComprobanteFiscalRepository $repositorio,
        private ElegibilidadEmisorRule $elegibilidad,
        private DeterminacionLetraRule $letra,
        private NumeracionService $numeracion,
        private Wsfet $wsfet,
    ) {}

    public function emitir(SolicitudEmision $solicitud): ComprobanteFiscal
    {
        $configuracion = $this->configuracion->buscarPorComercio($solicitud->comercioId());

        if ($configuracion === null || ! $configuracion->estaListoParaFacturar()) {
            throw new ComercioNoListoException(
                'El módulo fiscal del comercio no está listo para facturar.'
            );
        }

        if (! $this->elegibilidad->esElegible($solicitud->emisor())) {
            throw new EmisorNoElegibleException(
                $this->elegibilidad->motivoNoElegible($solicitud->emisor())
            );
        }

        $letra = $solicitud->letra() ?? $this->letra->determinar($solicitud->emisor(), $solicitud->receptor());

        $this->validarReceptor($letra, $solicitud);

        $numero = $this->numeracion->siguiente(
            $solicitud->comercioId(),
            $solicitud->puntoVenta()->numero(),
            $solicitud->tipo()
        );

        $cae = $this->wsfet->solicitarCae(
            $solicitud->construirComprobante($letra, $numero)
        );

        $emitido = $solicitud->construirComprobante($letra, $numero, $cae);

        return $this->repositorio->guardar($emitido);
    }

    private function validarReceptor(LetraComprobante $letra, SolicitudEmision $solicitud): void
    {
        $receptor = $solicitud->receptor();

        if ($letra !== LetraComprobante::A) {
            return;
        }

        if ($receptor === null || ! $receptor->tieneDatosParaFacturaA() || ! $receptor->esReceptorResponsableInscripto()) {
            throw new ReceptorNoAptoException(
                'Una Factura A requiere un receptor RI con CUIT, razón social y domicilio fiscal.'
            );
        }
    }
}
