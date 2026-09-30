<?php

namespace App\Services\Ticket;

use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Genera el PDF de una venta. Solo existe la vista comercial: la variante legal
 * (QR ARCA, CAE y desglose de IVA) se eliminó con el módulo de facturación
 * electrónica. Es compartido por el email digital y la descarga manual.
 */
final class TicketPdfService
{
    public function generar(Venta $venta)
    {
        $ticket = TicketBuilder::build($venta);

        $pdf = Pdf::loadView('tickets.a4', ['ticket' => $ticket->toArray()]);
        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }
}