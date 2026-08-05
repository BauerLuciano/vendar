<?php

namespace App\Services\Ticket;

use App\Facturacion\Domain\Contracts\ComprobanteFiscalRepository;
use App\Facturacion\Domain\Entities\ComprobanteFiscal;
use App\Models\Venta;
use Illuminate\Http\Request;

/**
 * Resuelve el comprobante a imprimir/descargar (F9 §11/§12). Por defecto usa el
 * comprobante de la venta; si el request trae ?comprobante_id se usa ese (p. ej.
 * reimprimir la Nota de Crédito de una venta ya devuelta/anulada). Es solo
 * lectura: nunca altera el ledger (§18.2) y todo acceso filtra por comercio.
 */
final class ComprobanteImpresionResolver
{
    public function __construct(private ComprobanteFiscalRepository $repositorio) {}

    public function resolver(Request $request, Venta $venta, int $comercioId): ?ComprobanteFiscal
    {
        $comprobanteId = $request->integer('comprobante_id');

        if ($comprobanteId > 0) {
            $comprobante = $this->repositorio->buscarPorId($comprobanteId, $comercioId);

            if ($comprobante === null || $comprobante->ventaId() !== (int) $venta->id) {
                abort(404, 'El comprobante no existe o no pertenece a la venta.');
            }

            return $comprobante;
        }

        return $this->repositorio->buscarPorVenta((int) $venta->id, $comercioId);
    }
}
