<?php

namespace App\Mail;

use App\Models\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketVenta extends Mailable
{
    use Queueable, SerializesModels;

    public $venta;
    public $datosEmpresa;

    public function __construct(Venta $venta, array $datosEmpresa)
    {
        $this->venta = $venta;
        $this->datosEmpresa = $datosEmpresa;
    }

    public function build()
    {
        return $this->html($this->cuerpoHtml())
            ->subject('Ticket de Venta #' . str_pad($this->venta->id, 6, '0', STR_PAD_LEFT) . ' - ' . $this->datosEmpresa['nombre']);
    }

    private function cuerpoHtml(): string
    {
        $v = $this->venta;
        $e = $this->datosEmpresa;
        $items = '';
        foreach ($v->detalles as $d) {
            $subtotal = $d->cantidad * ($d->precio_unitario ?? $d->precio_venta);
            $items .= "<tr><td style='padding:4px 8px;border-bottom:1px solid #eee'>{$d->cantidad}x {$d->producto->nombre}</td><td style='padding:4px 8px;border-bottom:1px solid #eee;text-align:right'>\$" . number_format($subtotal, 0, ',', '.') . "</td></tr>";
        }

        return <<<HTML
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto">
            <div style="background:#1e293b;color:#fff;padding:24px;text-align:center">
                <h2 style="margin:0;font-size:20px">{$e['nombre']}</h2>
                <p style="margin:8px 0 0;font-size:13px;opacity:.8">Ticket de Venta</p>
            </div>
            <div style="padding:24px;background:#f8fafc">
                <p style="margin:0 0 16px;font-size:14px;color:#475569">
                    Hola <strong>{$v->consumidor->nombre}</strong>, adjuntamos el ticket de tu compra.
                </p>
                <table style="width:100%;font-size:13px;border-collapse:collapse">
                    <tr><td style="padding:4px 8px;color:#64748b">Ticket #</td><td style="padding:4px 8px;font-weight:bold">" . str_pad($v->id, 6, '0', STR_PAD_LEFT) . "</td></tr>
                    <tr><td style="padding:4px 8px;color:#64748b">Fecha</td><td style="padding:4px 8px;font-weight:bold">{$v->created_at->format('d/m/Y H:i')}</td></tr>
                    <tr><td style="padding:4px 8px;color:#64748b">Método de pago</td><td style="padding:4px 8px;font-weight:bold">{$v->metodo_pago_display}</td></tr>
                </table>
                <hr style="border:none;border-top:2px solid #e2e8f0;margin:16px 0">
                <table style="width:100%;font-size:13px;border-collapse:collapse">
                    <thead><tr style="background:#e2e8f0"><th style="padding:8px;text-align:left">Detalle</th><th style="padding:8px;text-align:right">Subtotal</th></tr></thead>
                    <tbody>{$items}</tbody>
                </table>
                <hr style="border:none;border-top:2px solid #e2e8f0;margin:16px 0">
                <div style="text-align:right;font-size:18px;font-weight:bold;color:#1e293b">
                    Total: \$" . number_format($v->total, 0, ',', '.') . "
                </div>
            </div>
            <div style="padding:16px 24px;text-align:center;font-size:12px;color:#94a3b8">
                {$e['mensaje_pie']}<br>
                {$e['direccion']} | {$e['telefono']}
            </div>
        </div>
HTML;
    }
}
