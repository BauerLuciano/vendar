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
                font-family: 'Courier New', Courier, monospace;
            }
            .ticket-card {
                background: white;
                width: 58mm;
                padding: 3mm 2mm;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                border-radius: 2px;
            }
        }
        @media print {
            html, body { background: white; }
            body { width: 58mm; }
            .ticket-card {
                padding: 1mm 2mm;
                box-shadow: none;
            }
        }
        .font-small { font-size: 9px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .line { border-top: 1px dashed #888; margin: 3px 0; }
        .line-solid { border-top: 1px solid #000; margin: 3px 0; }
        .logo-img { max-width: 30mm; margin-bottom: 2px; }
        .qr-img { width: 34mm; margin: 3px auto; display: block; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        .total-row td { padding-top: 2px; }
        .total-label, .total-value { font-size: 15px; font-weight: bold; }
        .total-value { text-align: right; }
    </style>
</head>
<body>
    <div class="ticket-card">
        <div class="text-center">
            @if($ticket['empresa']['logo'])
                <img src="{{ $ticket['empresa']['logo'] }}" class="logo-img">
            @endif
            <div class="bold" style="font-size: 12px;">{{ $ticket['empresa']['nombre'] }}</div>
            <div class="font-small">CUIT: {{ $ticket['fiscal']['emisor']['cuit_formateado'] }}</div>
            <div class="font-small">{{ $ticket['fiscal']['emisor']['condicion_fiscal'] }}</div>
            @if($ticket['fiscal']['emisor']['domicilio_fiscal'])
                <div class="font-small">{{ $ticket['fiscal']['emisor']['domicilio_fiscal'] }}</div>
            @endif
            @if($ticket['empresa']['direccion'])
                <div class="font-small">{{ $ticket['empresa']['direccion'] }}</div>
            @endif
        </div>

        <div class="line-solid"></div>

        <div class="text-center">
            <div class="bold" style="font-size: 11px;">{{ $ticket['fiscal']['tipo'] }} {{ $ticket['fiscal']['letra'] }}</div>
            <div class="font-small">N° {{ $ticket['fiscal']['numero'] }}</div>
            <div class="font-small">F. Emisión: {{ $ticket['fiscal']['fecha_emision'] }}</div>
        </div>

        <div class="line"></div>

        <table class="font-small">
            @if($ticket['fiscal']['receptor']['razon_social'])
                <tr><td>Cliente: {{ \Illuminate\Support\Str::limit($ticket['fiscal']['receptor']['razon_social'], 26) }}</td></tr>
                @if($ticket['fiscal']['receptor']['cuit'])
                    <tr><td>CUIT: {{ $ticket['fiscal']['receptor']['cuit'] }}</td></tr>
                @endif
            @else
                <tr><td>Cliente: Consumidor Final</td></tr>
            @endif
            @if($ticket['sucursal']['nombre'])
                <tr><td>Sucursal: {{ $ticket['sucursal']['nombre'] }}</td></tr>
            @endif
            <tr><td>Cond. venta: {{ $ticket['venta']['metodo_pago_display'] }}</td></tr>
        </table>

        <div class="line-solid"></div>

        @foreach($ticket['items'] as $item)
            <table class="font-small">
                <tr><td colspan="2" class="bold">{{ \Illuminate\Support\Str::limit($item['nombre'], 26) }}</td></tr>
                <tr>
                    <td style="width: 60%;">{{ $item['cantidad'] }} x ${{ number_format($item['precio_unitario'], 2) }}</td>
                    <td style="width: 40%; text-align: right;">${{ number_format($item['subtotal'], 2) }}</td>
                </tr>
            </table>
        @endforeach

        <div class="line-solid"></div>

        <table class="font-small">
            @foreach($ticket['fiscal']['desglose'] as $linea)
                <tr>
                    <td>IVA {{ number_format($linea['alicuota'], 2, ',', '.') }}%</td>
                    <td class="text-right">${{ number_format($linea['total'], 2) }}</td>
                </tr>
            @endforeach
            <tr><td>Neto: </td><td class="text-right">${{ number_format($ticket['fiscal']['neto'], 2) }}</td></tr>
            <tr><td>IVA: </td><td class="text-right">${{ number_format($ticket['fiscal']['iva'], 2) }}</td></tr>
            @if((float) ($ticket['totales']['recargo'] ?? 0) > 0)
                <tr><td>Recargo: </td><td class="text-right">${{ number_format($ticket['totales']['recargo'], 2) }}</td></tr>
            @endif
        </table>

        <table>
            <tr class="total-row">
                <td class="total-label">TOTAL</td>
                <td class="total-value">${{ number_format($ticket['fiscal']['total'], 2) }}</td>
            </tr>
            @foreach($ticket['pagos'] as $pago)
                <tr>
                    <td class="font-small">{{ $pago['label'] }}</td>
                    <td class="font-small text-right">${{ number_format($pago['monto'], 2) }}</td>
                </tr>
            @endforeach
        </table>

        <div class="line-solid"></div>

        <table class="font-small">
            <tr>
                <td>CAE N°: <span class="bold">{{ $ticket['fiscal']['cae'] }}</span></td>
                <td class="text-right">Vto.: {{ $ticket['fiscal']['vencimiento_cae'] }}</td>
            </tr>
        </table>

        <img src="{{ $ticket['fiscal']['qr_image'] }}" class="qr-img" alt="QR ARCA">

        <div class="text-center font-small" style="margin-top: 2px;">Comprobante Autorizado</div>
        <div class="text-center font-small">{{ $ticket['empresa']['mensaje_pie'] }}</div>
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
