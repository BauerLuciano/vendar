<?php

namespace App\Jobs;

use App\Mail\TicketVenta;
use App\Models\Configuracion;
use App\Models\Venta;
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
        $venta = Venta::with(['detalles.producto', 'consumidor', 'turno.caja.sucursal', 'turno.cajero'])->find($this->ventaId);
        if (!$venta || !$venta->consumidor || !$venta->consumidor->email) {
            return;
        }

        $config = Configuracion::pluck('valor', 'clave')->toArray();

        $autoEmail = $config['ticket_digital_auto_email'] ?? '0';
        if ($autoEmail !== '1') {
            return;
        }

        $datosEmpresa = [
            'nombre' => $config['nombre_empresa'] ?? 'VendAR',
            'direccion' => $config['direccion_empresa'] ?? '',
            'telefono' => $config['telefono_empresa'] ?? '',
            'mensaje_pie' => $config['ticket_mensaje_pie'] ?? 'Gracias por su compra',
            'logo' => $config['logo_empresa'] ?? null,
        ];

        $pdf = Pdf::loadView('tickets.a4', compact('venta', 'datosEmpresa'));
        $pdf->setPaper('a4', 'portrait');

        Mail::to($venta->consumidor->email)
            ->send(new TicketVenta($venta, $datosEmpresa)
                ->attachData($pdf->output(), "ticket_{$venta->id}.pdf", ['mime' => 'application/pdf'])
            );
    }
}
