<?php

namespace App\Jobs;

use App\Mail\TicketVenta;
use App\Models\Venta;
use App\Services\Ticket\TicketBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EnviarTicketDigital implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $ventaId;

    public function __construct(int $ventaId)
    {
        $this->ventaId = $ventaId;
    }

    public function handle(): void
    {
        $venta = Venta::find($this->ventaId);
        if (!$venta || !$venta->consumidor || !$venta->consumidor->email) {
            return;
        }

        $ticket = TicketBuilder::build($venta);

        $config = \App\Models\Configuracion::pluck('valor', 'clave')->toArray();
        if (($config['ticket_digital_auto_email'] ?? '0') !== '1') {
            return;
        }

        $pdf = Pdf::loadView('tickets.a4', ['ticket' => $ticket->toArray()]);
        $pdf->setPaper('a4', 'portrait');

        $mailable = new TicketVenta($venta, $ticket->toArray());
        $mailable->attachData($pdf->output(), "ticket_{$venta->id}.pdf", ['mime' => 'application/pdf']);

        Mail::to($venta->consumidor->email)->send($mailable);
    }
}
