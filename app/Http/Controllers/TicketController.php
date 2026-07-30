<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Services\Ticket\TicketBuilder;

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

        $vistaTermica = strtolower($ticket->formato);
        return view("tickets.{$vistaTermica}", ['ticket' => $ticket->toArray()]);
    }
}
