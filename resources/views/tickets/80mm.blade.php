<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        @media screen {
            html { height: 100%; }
            body {
                background: #e5e7eb;
                min-height: 100%;
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 30px 10px;
                font-family: Arial, sans-serif;
            }
            .ticket-card {
                background: white;
                width: 80mm;
                padding: 3mm 2mm;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                border-radius: 2px;
            }
        }
        @media print {
            html, body { background: white; }
            body { width: 80mm; }
            .ticket-card {
                padding: 1mm 2mm;
                box-shadow: none;
            }
        }
        .font-small { font-size: 10px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .line { border-top: 1px solid #aaa; margin: 3px 0; }
        .line-dash { border-top: 1px dashed #ccc; margin: 3px 0; }
        .logo-img { max-width: 38mm; margin-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        th { padding: 2px 0; text-align: left; font-size: 10px; text-transform: uppercase; border-bottom: 2px solid #333; }
        .total-row td { padding-top: 3px; }
        .total-label, .total-value { font-size: 18px; font-weight: bold; }
        .total-value { text-align: right; }
    </style>
</head>
<body>
    <div class="ticket-card">
        <div class="text-center">
            @if($ticket['empresa']['logo'])
                <img src="{{ $ticket['empresa']['logo'] }}" class="logo-img">
            @endif
            <div class="bold" style="font-size: 16px;">{{ $ticket['empresa']['nombre'] }}</div>
            @if($ticket['empresa']['cuit'])
                <div class="font-small">CUIT: {{ $ticket['empresa']['cuit'] }}</div>
            @endif
            <div class="font-small">{{ $ticket['empresa']['direccion'] }}</div>
            <div class="font-small">Tel: {{ $ticket['empresa']['telefono'] }}</div>
        </div>

        <div class="line"></div>

        <table class="font-small">
            <tr>
                <td>Ticket #{{ $ticket['venta']['numero'] }}</td>
                <td class="text-right">{{ $ticket['venta']['fecha'] }} {{ $ticket['venta']['hora'] }}</td>
            </tr>
            @if($ticket['sucursal']['nombre'])
                <tr><td colspan="2">Sucursal: {{ $ticket['sucursal']['nombre'] }}</td></tr>
            @endif
            <tr><td colspan="2">Vendedor: {{ $ticket['vendedor']['nombre'] }}</td></tr>
            <tr><td colspan="2">Cliente: {{ $ticket['cliente']['nombre'] }}@if($ticket['cliente']['documento']) (Doc: {{ $ticket['cliente']['documento'] }})@endif</td></tr>
        </table>

        <div class="line"></div>

        <table class="font-small">
            <thead>
                <tr>
                    <th style="width: 12%;">CANT</th>
                    <th>DESCRIPCIÓN</th>
                    <th style="width: 25%; text-align: right;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ticket['items'] as $item)
                    <tr>
                        <td>{{ $item['cantidad'] }}</td>
                        <td>{{ $item['nombre'] }}</td>
                        <td class="text-right">${{ number_format($item['subtotal'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="line"></div>

        <table>
            <tr class="total-row">
                <td class="total-label">TOTAL</td>
                <td class="total-value">${{ number_format($ticket['totales']['total'], 2) }}</td>
            </tr>
            @foreach($ticket['pagos'] as $pago)
                <tr>
                    <td class="font-small">{{ $pago['label'] }}</td>
                    <td class="font-small text-right">${{ number_format($pago['monto'], 2) }}</td>
                </tr>
            @endforeach
        </table>

        <div class="text-center" style="margin-top: 6px;">
            <div class="font-small" style="font-style: italic;">{{ $ticket['empresa']['mensaje_pie'] }}</div>
        </div>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    window.print();
});
window.onafterprint = function() {
    window.close();
};
</script>
</body>
</html>