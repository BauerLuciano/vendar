<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Services\Ticket\TicketBuilder;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function imprimir(Request $request, Venta $venta)
    {
        $user = auth()->user();
        $comercioId = $user->branch?->comercio_id;
        if ($comercioId && $venta->turno->caja->sucursal->comercio_id !== $comercioId) {
            abort(403);
        }

        $ticket = TicketBuilder::build($venta);

        // Solo queda la vista comercial: la variante legal (QR ARCA, CAE,
        // letra y punto de venta) se eliminó con el módulo de facturación
        // electrónica.
        $vista = 'tickets.'.strtolower($ticket->formato);

        return view($vista, ['ticket' => $ticket->toArray()]);
    }
}