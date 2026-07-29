<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 10mm 10mm 10mm 10mm; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9pt;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        table.grid {
            width: 100%;
            border-collapse: collapse;
        }
        table.grid td.label {
            width: 33.33%;
            height: 25mm;
            vertical-align: middle;
            text-align: center;
            border: 0.5pt solid #e2e8f0;
            padding: 3mm 2mm;
        }
        .producto-nombre {
            font-size: 8pt;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
            margin-bottom: 2mm;
            line-height: 1.2;
        }
        .producto-precio {
            font-size: 18pt;
            font-weight: bold;
            color: #0f172a;
            margin: 0;
            line-height: 1;
        }
        .producto-precio .simbolo {
            font-size: 11pt;
        }
        .producto-fecha {
            font-size: 6.5pt;
            color: #94a3b8;
            margin-top: 1.5mm;
        }
    </style>
</head>
<body>
    <table class="grid">
        @php
            $total = $productos->count();
            $cols = 3;
            $rows = ceil($total / $cols);
        @endphp
        @for($row = 0; $row < $rows; $row++)
            <tr>
                @for($col = 0; $col < $cols; $col++)
                    @php
                        $index = $row * $cols + $col;
                    @endphp
                    @if($index < $total)
                        @php $p = $productos[$index]; @endphp
                        <td class="label">
                            <div class="producto-nombre">{{ $p->nombre }}</div>
                            <div class="producto-precio">
                                <span class="simbolo">$</span> {{ number_format($p->precio_venta, 2, ',', '.') }}
                            </div>
                            @if($p->precio_venta_actualizado_en)
                                <div class="producto-fecha">Actualizado: {{ \Carbon\Carbon::parse($p->precio_venta_actualizado_en)->format('d/m/y') }}</div>
                            @endif
                        </td>
                    @else
                        <td class="label" style="border: none;"></td>
                    @endif
                @endfor
            </tr>
        @endfor
    </table>
</body>
</html>
