<!DOCTYPE html>
<html>
<head>
    <style>
        * { font-family: 'Courier New', Courier, monospace; font-size: 12px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .border-top { border-top: 1px dashed black; margin-top: 5px; padding-top: 5px; }
        .w-100 { width: 100%; }
        @page { margin: 0; }
        body { width: 48mm; margin: 5mm; } /* 58mm menos márgenes */
    </style>
</head>
<body onload="window.print();"> <div class="text-center">
        <span class="bold" style="font-size: 16px;">{{ $datosEmpresa['nombre'] }}</span><br>
        {{ $datosEmpresa['direccion'] }}<br>
        Tel: {{ $datosEmpresa['telefono'] }}<br>
    </div>

    <div class="border-top">
        Ticket: #{{ str_pad($venta->id, 8, '0', STR_PAD_LEFT) }}<br>
        Fecha: {{ $venta->created_at->format('d/m/Y H:i') }}<br>
        Cajero: {{ $venta->turno->cajero->name }}<br>
        Cliente: {{ $venta->consumidor ? $venta->consumidor->nombre : 'Consumidor Final' }}
    </div>

    <table class="w-100 border-top">
        <thead>
            <tr>
                <th class="text-left">DESCRIPCIÓN</th>
                <th class="text-right">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $detalle)
                <tr>
                    <td colspan="2">{{ substr($detalle->producto->nombre, 0, 20) }}</td>
                </tr>
                <tr>
                    <td class="text-left">{{ $detalle->cantidad }} x {{ number_format($detalle->precio_unitario, 2) }}</td>
                    <td class="text-right">${{ number_format($detalle->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="border-top text-right">
        <span class="bold" style="font-size: 14px;">TOTAL: ${{ number_format($venta->total, 2) }}</span><br>
        <small>Pago: {!! $venta->metodo_pago_display !!}</small>
    </div>

    <div class="text-center" style="margin-top: 20px;">
        {{ $datosEmpresa['mensaje_pie'] }}<br>
        <small>vendar.com.ar</small>
    </div>
</body>
</html>