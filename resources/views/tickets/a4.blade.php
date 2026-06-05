<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 20px; }
        .header { width: 100%; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .header-left { width: 50%; float: left; }
        .header-right { width: 50%; float: right; text-align: right; }
        .info-box { border: 1px solid #ccc; padding: 15px; margin-top: 20px; border-radius: 10px; }
        .table { width: 100%; margin-top: 30px; border-collapse: collapse; }
        .table th { background: #f4f4f4; border: 1px solid #ddd; padding: 10px; text-align: left; }
        .table td { border: 1px solid #ddd; padding: 10px; }
        .totals { float: right; width: 300px; margin-top: 20px; }
        .totals-row { display: flex; justify-content: space-between; padding: 5px 0; }
        .bold { font-weight: bold; }
        .clear { clear: both; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <h1 style="margin: 0; color: #4f46e5;">{{ $datosEmpresa['nombre'] }}</h1>
            <p>{{ $datosEmpresa['direccion'] }}<br>Tel: {{ $datosEmpresa['telefono'] }}</p>
        </div>
        <div class="header-right">
            <h2 style="margin: 0;">COMPROBANTE DE VENTA</h2>
            <p class="bold">N° 0001 - {{ str_pad($venta->id, 8, '0', STR_PAD_LEFT) }}</p>
            <p>Fecha: {{ $venta->created_at->format('d/m/Y') }}</p>
        </div>
        <div class="clear"></div>
    </div>

    <div class="info-box">
        <p class="bold" style="margin: 0 0 10px 0;">DATOS DEL CLIENTE</p>
        <p style="margin: 0;">
            Nombre: {{ $venta->consumidor ? $venta->consumidor->nombre : 'Consumidor Final' }}<br>
            Documento: {{ $venta->consumidor->documento ?? 'N/A' }}<br>
            Dirección: {{ $venta->consumidor->direccion ?? 'N/A' }}
        </p>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Descripción</th>
                <th style="text-align: center;">Cantidad</th>
                <th style="text-align: right;">Precio Unit.</th>
                <th style="text-align: right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $detalle)
                <tr>
                    <td>{{ $detalle->producto->nombre }}</td>
                    <td style="text-align: center;">{{ $detalle->cantidad }}</td>
                    <td style="text-align: right;">${{ number_format($detalle->precio_unitario, 2) }}</td>
                    <td style="text-align: right;">${{ number_format($detalle->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="totals-row">
            <span>Subtotal:</span>
            <span class="bold">${{ number_format($venta->total, 2) }}</span>
        </div>
        <div class="totals-row" style="border-top: 1px solid #000; padding-top: 10px; font-size: 18px;">
            <span>TOTAL:</span>
            <span class="bold">${{ number_format($venta->total, 2) }}</span>
        </div>
        <p style="text-align: right; margin-top: 10px; font-size: 12px;">Método de pago: {{ $venta->metodo_pago_display }}</p>
    </div>

    <div class="clear"></div>
    <div style="margin-top: 100px; text-align: center; color: #888;">
        <p>{{ $datosEmpresa['mensaje_pie'] }}</p>
    </div>
</body>
</html>