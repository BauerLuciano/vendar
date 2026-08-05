<?php

namespace App\Services\Ticket;

use App\Facturacion\Domain\Entities\ComprobanteFiscal;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Genera el PDF de una venta (F9, arquitectura §12). Con comprobante fiscal se
 * usa la vista legal A4 (QR, CAE, desglose); sin módulo fiscal se mantiene el
 * ticket A4 actual. Es compartido por el email digital y la descarga manual.
 */
final class TicketPdfService
{
    public function generar(Venta $venta, ?ComprobanteFiscal $comprobante = null)
    {
        $ticket = TicketBuilder::build($venta, $comprobante);

        $vista = $comprobante !== null ? 'facturacion.a4' : 'tickets.a4';
        $pdf = Pdf::loadView($vista, ['ticket' => $ticket->toArray()]);
        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }
}
