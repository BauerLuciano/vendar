<?php

namespace App\Facturacion\Domain\Contracts;

use App\Facturacion\Domain\Entities\ComprobanteFiscal;

/**
 * Repositorio del ledger de comprobantes fiscales (arquitectura §18).
 * El ledger es inmutable (solo insert) y todo registro respeta comercio_id.
 */
interface ComprobanteFiscalRepository
{
    public function guardar(ComprobanteFiscal $comprobante): ComprobanteFiscal;

    public function buscarPorVenta(int $ventaId, int $comercioId): ?ComprobanteFiscal;

    /**
     * Comprobante específico del ledger, para reimpresión/PDF por comprobante
     * (incluye la Nota de Crédito de una venta). Todo acceso filtra por
     * comercio_id (invariante 8).
     */
    public function buscarPorId(int $id, int $comercioId): ?ComprobanteFiscal;

    /**
     * Nota de Crédito emitida para una venta (comprobante con comprobante_original_id).
     * Una venta facturada puede tener factura y NC con el mismo venta_id.
     */
    public function buscarNotaCredito(int $ventaId, int $comercioId): ?ComprobanteFiscal;

    /**
     * @return ComprobanteFiscal[]
     */
    public function listarPorComercio(int $comercioId): array;

    /**
     * Próximo número de comprobante para (comercio_id, punto_venta, tipo).
     * Debe ser secuencial, sin retrocesos y seguro bajo concurrencia (§18.2).
     */
    public function proximoNumero(int $comercioId, int $puntoVenta, string $tipo): int;
}
