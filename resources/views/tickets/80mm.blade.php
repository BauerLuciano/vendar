<!DOCTYPE html>
<html>
<head>
    <style>
        * { font-family: 'Arial', sans-serif; font-size: 13px; color: #333; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .border-bottom { border-bottom: 1px solid #000; padding-bottom: 5px; margin-bottom: 5px; }
        .w-100 { width: 100%; border-collapse: collapse; }
        .items-table th { border-bottom: 2px solid #000; padding: 5px 0; }
        .items-table td { padding: 5px 0; }
        @page { margin: 0; }
        body { width: 72mm; margin: 4mm; } /* Optimizado para 80mm */
    </style>
</head>
<body onload="window.print();">
    <div class="text-center">
        @if($datosEmpresa['logo'])
            <img src="{{ $datosEmpresa['logo'] }}" style="max-width: 40mm; margin-bottom: 10px;">
        @endif
        <div class="bold" style="font-size: 18px;">{{ $datosEmpresa['nombre'] }}</div>
        <div>{{ $datosEmpresa['direccion'] }}</div>
        <div>Teléfono: {{ $datosEmpresa['telefono'] }}</div>
    </div>

    <div class="border-bottom" style="margin-top: 10px;">
        <div style="display: flex; justify-content: space-between;">
            <span>Ticket: #{{ str_pad($venta->id, 8, '0', STR_PAD_LEFT) }}</span>
            <span class="text-right">{{ $venta->created_at->format('d/m/Y H:i') }}</span>
        </div>
        <div>Cajero: {{ $venta->turno->cajero->name }}</div>
        <div>Cliente: {{ $venta->consumidor ? $venta->consumidor->nombre : 'Consumidor Final' }}</div>
    </div>

    <table class="w-100 items-table">
        <thead>
            <tr>
                <th class="text-left">CANT.</th>
                <th class="text-left">DESCRIPCIÓN</th>
                <th class="text-right">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $detalle)
                <tr>
                    <td class="text-left">{{ $detalle->cantidad }}</td>
                    <td class="text-left">{{ $detalle->producto->nombre }}</td>
                    <td class="text-right">${{ number_format($detalle->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="border-bottom" style="margin-top: 10px; text-align: right;">
        <div style="font-size: 16px;">TOTAL: <span class="bold">${{ number_format($venta->total, 2) }}</span></div>
        <div style="font-size: 11px;">Método: {{ $venta->metodo_pago_display }}</div>
    </div>

    <div class="text-center" style="margin-top: 15px; font-style: italic;">
        {{ $datosEmpresa['mensaje_pie'] }}
    </div>
</body>
</html>