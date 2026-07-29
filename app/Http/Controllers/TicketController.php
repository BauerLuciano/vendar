<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Services\Ticket\TicketBuilder;
use Barryvdh\DomPDF\Facade\Pdf;

class TicketController extends Controller
{
    public function imprimir(Venta $venta)
    {
        $user = auth()->user();
        $comercioId = $user->branch?->comercio_id;
        if ($comercioId && $venta->turno->caja->sucursal->comercio_id !== $comercioId) {
            abort(403);
        }

        $ticket = TicketBuilder::build($venta);

        if ($ticket->formato === 'A4') {
            $pdf = Pdf::loadView('tickets.a4', ['ticket' => $ticket->toArray()]);
            $pdf->setPaper('a4', 'portrait');
            return $pdf->stream("factura_{$venta->id}.pdf");
        }

        $vistaTermica = strtolower($ticket->formato);
        return view("tickets.{$vistaTermica}", ['ticket' => $ticket->toArray()]);
    }
}
