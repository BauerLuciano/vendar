<?php

namespace App\Facturacion\Domain\Contracts;

use App\Facturacion\Domain\Entities\ComprobanteFiscal;
use App\Facturacion\Domain\ValueObjects\Cae;
use App\Facturacion\Domain\ValueObjects\LetraComprobante;
use App\Facturacion\Domain\ValueObjects\TipoComprobante;

/**
 * Servicio WSFE (Web Service de Facturación Electrónica).
 * Solicita y consulta CAE. Un cambio de versión de ARCA impacta solo en
 * Infrastructure/Arca, nunca en el dominio (arquitectura §14.2).
 */
interface Wsfet
{
    /**
     * Solicita el CAE de un comprobante. Devuelve el CAE emitido.
     * Ante fallo de ARCA lanza una excepción de integración (la venta no se completa).
     */
    public function solicitarCae(ComprobanteFiscal $comprobante): Cae;

    /**
     * Consulta el estado de un comprobante (CAE perdido, arquitectura §7.2).
     * Devuelve null si ARCA no lo registra para (punto_venta, numero, tipo).
     */
    public function consultarComprobante(
        int $puntoVenta,
        int $numero,
        TipoComprobante $tipo,
        LetraComprobante $letra
    ): ?Cae;

    /**
     * Puntos de venta habilitados del emisor en ARCA (wizard paso 4, §13).
     *
     * @return array<int, array{nro: int, bloqueado: bool}>
     */
    public function puntosVenta(): array;
}
