<?php

namespace App\Jobs;

use App\Facturacion\Domain\Contracts\ComprobanteFiscalRepository;
use App\Mail\TicketVenta;
use App\Models\Configuracion;
use App\Models\Venta;
use App\Services\Ticket\TicketBuilder;
use App\Services\Ticket\TicketPdfService;
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

    public function handle(ComprobanteFiscalRepository $repositorio, TicketPdfService $pdfService): void
    {
        $venta = Venta::find($this->ventaId);
        if (! $venta || ! $venta->consumidor || ! $venta->consumidor->email) {
            return;
        }

        // F9: se reconstruye el comprobante del ledger para que el ticket digital
        // muestre el bloque fiscal (QR, CAE, desglose) cuando corresponda.
        $comercioId = $venta->turno?->caja?->sucursal?->comercio_id;
        $comprobante = $comercioId
            ? $repositorio->buscarPorVenta($venta->id, $comercioId)
            : null;

        $ticket = TicketBuilder::build($venta, $comprobante);

        $config = Configuracion::pluck('valor', 'clave')->toArray();
        if (($config['ticket_digital_auto_email'] ?? '0') !== '1') {
            return;
        }

        // F9: TicketPdfService elige la vista legal A4 (QR, CAE, desglose) con
        // comprobante fiscal, o el ticket A4 actual sin el módulo fiscal.
        $pdf = $pdfService->generar($venta, $comprobante);

        $mailable = new TicketVenta($venta, $ticket->toArray());
        $mailable->attachData($pdf->output(), "ticket_{$venta->id}.pdf", ['mime' => 'application/pdf']);

        Mail::to($venta->consumidor->email)->send($mailable);
    }
}
