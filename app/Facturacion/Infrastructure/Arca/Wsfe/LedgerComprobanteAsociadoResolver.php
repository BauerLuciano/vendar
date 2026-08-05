<?php

namespace App\Facturacion\Infrastructure\Arca\Wsfe;

use App\Facturacion\Domain\ValueObjects\LetraComprobante;
use App\Facturacion\Domain\ValueObjects\TipoComprobante;
use App\Models\ComprobanteFiscal as ComprobanteFiscalModel;

/**
 * Resuelve el comprobante original consultando el ledger inmutable (solo lectura).
 */
final class LedgerComprobanteAsociadoResolver implements ComprobanteAsociadoResolver
{
    public function resolver(int $comprobanteOriginalId): ?array
    {
        $original = ComprobanteFiscalModel::find($comprobanteOriginalId);

        if ($original === null) {
            return null;
        }

        try {
            $tipo = TipoComprobante::from($original->tipo);
            $letra = LetraComprobante::from($original->letra);
        } catch (\ValueError) {
            return null;
        }

        return [
            'tipo' => $tipo->codigoAfip($letra),
            'ptoVta' => (int) $original->punto_venta,
            'nro' => (int) $original->numero,
        ];
    }
}
