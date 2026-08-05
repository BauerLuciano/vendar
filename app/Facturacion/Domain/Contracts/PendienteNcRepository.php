<?php

namespace App\Facturacion\Domain\Contracts;

/**
 * Repositorio de pendientes de Notas de Crédito fallidas (F8, arquitectura §8).
 * Cuando la emisión de una NC falla, la anulación/devolución se revierte por
 * completo y el pendiente queda registrado para reintento desde el Panel de
 * Diagnóstico. Todo acceso filtra por comercio_id (invariante 8 / multi-tenant).
 *
 * El contrato es puro: expone arreglos y valores escalares, sin dependencias
 * del framework ni de los modelos Eloquent (regla de pureza del dominio).
 */
interface PendienteNcRepository
{
    /**
     * Registra el pendiente de una NC fallida. Si ya existe un pendiente activo
     * para la misma venta y tipo de operación, lo actualiza e incrementa intentos
     * (upsert), evitando duplicados en los reintentos.
     *
     * @param  array{
     *   comercio_id: int,
     *   venta_id: int,
     *   tipo_operacion: 'anulacion'|'devolucion',
     *   motivo: ?string,
     *   items: ?array,
     *   monto_devuelto: ?float,
     *   motivo_fallo: string
     * }  $datos
     */
    public function registrarPendiente(array $datos): int;

    /**
     * @return array<string, mixed>|null Datos del pendiente o null si no existe.
     */
    public function buscar(int $id): ?array;

    /**
     * @return array<int, array<string, mixed>> Pendientes pendientes del comercio.
     */
    public function pendientesDe(int $comercioId): array;

    /**
     * Marca el pendiente como resuelto cuando el reintento completo de la
     * operación (anulación/devolución + NC) termina con éxito.
     */
    public function marcarResuelto(int $id): void;
}
