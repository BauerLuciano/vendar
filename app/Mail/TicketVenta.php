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
    public $ticket;

    public function __construct(Venta $venta, array $ticket)
    {
        $this->venta = $venta;
        $this->ticket = $ticket;
    }

    public function build()
    {
        return $this->html($this->cuerpoHtml())
            ->subject('Ticket de Venta #' . $this->ticket['venta']['numero'] . ' - ' . $this->ticket['empresa']['nombre']);
    }

    private function cuerpoHtml(): string
    {
        $t = $this->ticket;

        $items = '';
        foreach ($t['items'] as $item) {
            $items .= '<tr>'
                . '<td style="padding:4px 8px;border-bottom:1px solid #eee">' . $item['cantidad'] . 'x ' . e($item['nombre']) . '</td>'
                . '<td style="padding:4px 8px;border-bottom:1px solid #eee;text-align:right">$' . number_format($item['subtotal'], 0, ',', '.') . '</td>'
                . '</tr>';
        }

        $pagosHtml = '';
        foreach ($t['pagos'] as $pago) {
            $pagosHtml .= '<tr>'
                . '<td style="padding:4px 8px;color:#64748b">' . e($pago['label']) . '</td>'
                . '<td style="padding:4px 8px;font-weight:bold;text-align:right">$' . number_format($pago['monto'], 0, ',', '.') . '</td>'
                . '</tr>';
        }

        $html = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto">';
        $html .= '<div style="background:#1e293b;color:#fff;padding:24px;text-align:center">';
        $html .= '<h2 style="margin:0;font-size:20px">' . e($t['empresa']['nombre']) . '</h2>';
        $html .= '<p style="margin:8px 0 0;font-size:13px;opacity:.8">Ticket de Venta</p>';
        $html .= '</div>';
        $html .= '<div style="padding:24px;background:#f8fafc">';
        $html .= '<p style="margin:0 0 16px;font-size:14px;color:#475569">';
        $html .= 'Hola <strong>' . e($t['cliente']['nombre']) . '</strong>, adjuntamos el ticket de tu compra.';
        $html .= '</p>';
        $html .= '<table style="width:100%;font-size:13px;border-collapse:collapse">';
        $html .= '<tr><td style="padding:4px 8px;color:#64748b">Ticket #</td><td style="padding:4px 8px;font-weight:bold">' . $t['venta']['numero'] . '</td></tr>';
        $html .= '<tr><td style="padding:4px 8px;color:#64748b">Fecha</td><td style="padding:4px 8px;font-weight:bold">' . $t['venta']['fecha_completa'] . '</td></tr>';
        $html .= $pagosHtml;
        $html .= '</table>';
        $html .= '<hr style="border:none;border-top:2px solid #e2e8f0;margin:16px 0">';
        $html .= '<table style="width:100%;font-size:13px;border-collapse:collapse">';
        $html .= '<thead><tr style="background:#e2e8f0"><th style="padding:8px;text-align:left">Detalle</th><th style="padding:8px;text-align:right">Subtotal</th></tr></thead>';
        $html .= '<tbody>' . $items . '</tbody>';
        $html .= '</table>';
        $html .= '<hr style="border:none;border-top:2px solid #e2e8f0;margin:16px 0">';
        $html .= '<div style="text-align:right;font-size:18px;font-weight:bold;color:#1e293b">';
        $html .= 'Total: $' . number_format($t['totales']['total'], 0, ',', '.');
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div style="padding:16px 24px;text-align:center;font-size:12px;color:#94a3b8">';
        $html .= e($t['empresa']['mensaje_pie']) . '<br>';
        $html .= e($t['empresa']['direccion']) . ' | ' . e($t['empresa']['telefono']);
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}
