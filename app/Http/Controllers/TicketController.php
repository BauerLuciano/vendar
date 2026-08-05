<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Services\Ticket\ComprobanteImpresionResolver;
use App\Services\Ticket\TicketBuilder;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function imprimir(Request $request, Venta $venta, ComprobanteImpresionResolver $resolver)
    {
        $user = auth()->user();
        $comercioId = $user->branch?->comercio_id;
        if ($comercioId && $venta->turno->caja->sucursal->comercio_id !== $comercioId) {
            abort(403);
        }

        // F9: la reimpresión es solo lectura; el comprobante se reconstruye del
        // ledger (arquitectura §18.2) y no altera ningún registro. Se puede
        // pedir un comprobante específico con ?comprobante_id (p. ej. la NC).
        $comercioFiscal = $venta->turno->caja->sucursal->comercio_id;
        $comprobante = $resolver->resolver($request, $venta, $comercioFiscal);

        $ticket = TicketBuilder::build($venta, $comprobante);

        // F9: con comprobante fiscal se imprime la vista legal (58/80/A4) con QR,
        // CAE y desglose; sin módulo fiscal se mantiene el ticket actual.
        $vistaTermica = strtolower($ticket->formato);
        $vista = $comprobante !== null ? "facturacion.{$vistaTermica}" : "tickets.{$vistaTermica}";

        return view($vista, ['ticket' => $ticket->toArray()]);
    }
}
