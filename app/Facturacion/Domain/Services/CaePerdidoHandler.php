<?php

namespace App\Facturacion\Domain\Services;

use App\Facturacion\Domain\Contracts\ComprobanteFiscalRepository;
use App\Facturacion\Domain\Contracts\Wsfet;
use App\Facturacion\Domain\Entities\ComprobanteFiscal;
use App\Facturacion\Domain\ValueObjects\LetraComprobante;

/**
 * Resolución de CAE perdido (arquitectura §7.2): cuando una solicitud de CAE
 * quedó en estado incierto (ARCA pudo emitir pero no llegó la respuesta), se
 * consulta el estado del comprobante por (punto_venta, numero, tipo).
 *
 * - Si existe → se adopta el CAE devuelto (mismo número).
 * - Si no existe → se reemite con un número nuevo (nunca se reutiliza el número
 *   reservado en un intento fallido, §18.2: la numeración no retrocede).
 */
final class CaePerdidoHandler
{
    public function __construct(
        private ComprobanteFiscalRepository $repositorio,
        private NumeracionService $numeracion,
        private Wsfet $wsfet,
    ) {}

    public function resolver(SolicitudEmision $solicitud, LetraComprobante $letra, int $numero): ComprobanteFiscal
    {
        $adoptado = $this->wsfet->consultarComprobante(
            $solicitud->puntoVenta()->numero(),
            $numero,
            $solicitud->tipo(),
            $letra,
        );

        if ($adoptado !== null) {
            return $this->repositorio->guardar(
                $solicitud->construirComprobante($letra, $numero, $adoptado)
            );
        }

        $nuevoNumero = $this->numeracion->siguiente(
            $solicitud->comercioId(),
            $solicitud->puntoVenta()->numero(),
            $solicitud->tipo()
        );

        $cae = $this->wsfet->solicitarCae(
            $solicitud->construirComprobante($letra, $nuevoNumero)
        );

        return $this->repositorio->guardar(
            $solicitud->construirComprobante($letra, $nuevoNumero, $cae)
        );
    }
}
