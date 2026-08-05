<?php

namespace App\Facturacion\Infrastructure\Arca\Wsfe;

/**
 * Resuelve los datos fiscales del comprobante original para una Nota de Crédito.
 * ARCA exige CmpAsoc con (tipo, punto de venta, número) del comprobante de origen.
 */
interface ComprobanteAsociadoResolver
{
    /**
     * @return array{tipo: int, ptoVta: int, nro: int}|null
     */
    public function resolver(int $comprobanteOriginalId): ?array;
}
