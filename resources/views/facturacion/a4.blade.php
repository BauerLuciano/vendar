<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 20px; color: #333; }
        .header { width: 100%; border-bottom: 2px solid #333; padding-bottom: 20px; overflow: hidden; }
        .header-left { width: 50%; float: left; }
        .header-right { width: 50%; float: right; text-align: right; }
        .header-left h1 { margin: 0; color: #4f46e5; font-size: 24px; }
        .header-left p { margin: 4px 0; font-size: 12px; color: #666; }
        .header-right h2 { margin: 0; font-size: 16px; }
        .header-right p { margin: 4px 0; font-size: 12px; }
        .logo { max-height: 50px; margin-bottom: 8px; }
        .comprobante-badge { display: inline-block; background: #eef2ff; color: #4f46e5; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-right: 4px; }
        .info-box { border: 1px solid #ccc; padding: 15px; margin-top: 20px; border-radius: 10px; }
        .info-box p { margin: 0; font-size: 13px; line-height: 1.6; }
        .bold { font-weight: bold; }
        .table { width: 100%; margin-top: 30px; border-collapse: collapse; }
        .table th { background: #f4f4f4; border: 1px solid #ddd; padding: 10px; text-align: left; font-size: 12px; }
        .table td { border: 1px solid #ddd; padding: 10px; font-size: 12px; }
        .table td:last-child { text-align: right; }
        .table td:nth-child(2) { text-align: center; }
        .table td:nth-child(3) { text-align: right; }
        .iva-table { width: 45%; margin-top: 20px; float: left; border-collapse: collapse; }
        .iva-table th { background: #f4f4f4; border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 11px; }
        .iva-table td { border: 1px solid #ddd; padding: 8px; font-size: 11px; }
        .iva-table td:last-child { text-align: right; }
        .totals { float: right; width: 300px; margin-top: 20px; }
        .totals-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; }
        .totals-final { border-top: 1px solid #000; padding-top: 8px; font-size: 18px; display: flex; justify-content: space-between; }
        .clear { clear: both; }
        .qr-box { width: 50%; margin: 20px auto 0; text-align: center; }
        .qr-img { width: 140px; }
        .footer { margin-top: 40px; text-align: center; color: #888; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            @if($ticket['empresa']['logo'])
                <img src="{{ $ticket['empresa']['logo'] }}" class="logo"><br>
            @endif
            <h1>{{ $ticket['empresa']['nombre'] }}</h1>
            <p>CUIT: {{ $ticket['fiscal']['emisor']['cuit_formateado'] }}</p>
            <p class="bold">{{ $ticket['fiscal']['emisor']['condicion_fiscal'] }}</p>
            <p>{{ $ticket['fiscal']['emisor']['domicilio_fiscal'] ?: $ticket['empresa']['direccion'] }}<br>
               Tel: {{ $ticket['empresa']['telefono'] }}</p>
        </div>
        <div class="header-right">
            <h2>{{ $ticket['fiscal']['tipo'] }} {{ $ticket['fiscal']['letra'] }}</h2>
            <p class="bold">N° {{ $ticket['fiscal']['numero'] }}</p>
            <p>Fecha de emisión: {{ $ticket['fiscal']['fecha_emision'] }}</p>
            <p>CAE N°: {{ $ticket['fiscal']['cae'] }} - Vto.: {{ $ticket['fiscal']['vencimiento_cae'] }}</p>
        </div>
        <div class="clear"></div>
    </div>

    <div class="info-box">
        <p class="bold" style="margin: 0 0 8px 0;">DATOS DEL CLIENTE</p>
        <p style="margin: 0;">
            Nombre/Razón social: {{ $ticket['fiscal']['receptor']['razon_social'] ?: 'Consumidor Final' }}<br>
            CUIT/DNI: {{ $ticket['fiscal']['receptor']['cuit'] ?: 'N/A' }}<br>
            Domicilio: {{ $ticket['fiscal']['receptor']['domicilio'] ?: $ticket['cliente']['direccion'] ?: 'N/A' }}
        </p>
    </div>

    @if($ticket['sucursal']['nombre'])
        <div style="margin-top: 10px;">
            <span class="comprobante-badge">Sucursal: {{ $ticket['sucursal']['nombre'] }}</span>
            <span class="comprobante-badge">Vendedor: {{ $ticket['vendedor']['nombre'] }}</span>
            <span class="comprobante-badge">Cond. venta: {{ $ticket['venta']['metodo_pago_display'] }}</span>
        </div>
    @endif

    <table class="table">
        <thead>
            <tr>
                <th>Descripción</th>
                <th style="text-align: center; width: 10%;">Cantidad</th>
                <th style="text-align: right; width: 18%;">Precio Unit.</th>
                <th style="text-align: right; width: 18%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ticket['items'] as $item)
                <tr>
                    <td>{{ $item['nombre'] }}</td>
                    <td style="text-align: center;">{{ $item['cantidad'] }}</td>
                    <td style="text-align: right;">${{ number_format($item['precio_unitario'], 2) }}</td>
                    <td style="text-align: right;">${{ number_format($item['subtotal'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="iva-table">
        <thead>
            <tr>
                <th>Alícuota</th>
                <th style="text-align: right;">Neto</th>
                <th style="text-align: right;">IVA</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ticket['fiscal']['desglose'] as $linea)
                <tr>
                    <td>{{ number_format($linea['alicuota'], 2, ',', '.') }}%</td>
                    <td style="text-align: right;">${{ number_format($linea['neto'], 2) }}</td>
                    <td style="text-align: right;">${{ number_format($linea['iva'], 2) }}</td>
                    <td style="text-align: right;">${{ number_format($linea['total'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="totals-row">
            <span>Subtotal:</span>
            <span class="bold">${{ number_format($ticket['fiscal']['neto'], 2) }}</span>
        </div>
        <div class="totals-row">
            <span>IVA:</span>
            <span>${{ number_format($ticket['fiscal']['iva'], 2) }}</span>
        </div>
        @if((float) ($ticket['totales']['recargo'] ?? 0) > 0)
            <div class="totals-row">
                <span>Recargo:</span>
                <span>${{ number_format($ticket['totales']['recargo'], 2) }}</span>
            </div>
        @endif
        @foreach($ticket['pagos'] as $pago)
            <div class="totals-row">
                <span>{{ $pago['label'] }}:</span>
                <span>${{ number_format($pago['monto'], 2) }}</span>
            </div>
        @endforeach
        <div class="totals-final">
            <span>TOTAL:</span>
            <span class="bold">${{ number_format($ticket['fiscal']['total'], 2) }}</span>
        </div>
    </div>

    <div class="clear"></div>

    <div class="qr-box">
        <img src="{{ $ticket['fiscal']['qr_image'] }}" class="qr-img" alt="QR ARCA">
        <p style="font-size: 11px; color: #666; margin: 4px 0 0 0;">Comprobante Autorizado</p>
    </div>

    <div class="footer">
        {{ $ticket['empresa']['mensaje_pie'] }}
    </div>
</body>
</html>
