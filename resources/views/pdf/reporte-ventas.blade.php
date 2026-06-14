<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 40px 50px 60px 50px; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9pt;
            color: #1e293b;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #1e293b;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 15pt;
            font-weight: bold;
            color: #1e293b;
            margin: 0 0 4px 0;
            text-transform: uppercase;
        }
        .doc-title {
            font-size: 17pt;
            font-weight: bold;
            color: #1e293b;
            text-transform: uppercase;
            margin: 0 0 8px 0;
        }
        .subtitle {
            font-size: 9pt;
            color: #475569;
            margin-bottom: 20px;
        }
        .summary-grid {
            width: 100%;
            margin-bottom: 25px;
            border-collapse: collapse;
        }
        .summary-grid td {
            width: 33.33%;
            padding: 12px;
            text-align: center;
            border: 1px solid #e2e8f0;
            font-size: 10pt;
        }
        .summary-grid .label {
            font-size: 7.5pt;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 1px;
            display: block;
            margin-bottom: 4px;
        }
        .summary-grid .value {
            font-size: 14pt;
            font-weight: bold;
            color: #1e293b;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        table.data th {
            background-color: #1e293b;
            color: #ffffff;
            padding: 8px 10px;
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: left;
        }
        table.data th.right { text-align: right; }
        table.data td {
            padding: 7px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 8.5pt;
        }
        table.data td.right { text-align: right; }
        table.data tr:nth-child(even) { background-color: #f8fafc; }
        .footer {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7.5pt;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ $nombreEmpresa }}</div>
        <div class="doc-title">Reporte de Ventas</div>
        <div class="subtitle">
            Período: {{ \Carbon\Carbon::parse($fechaDesde)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($fechaHasta)->format('d/m/Y') }}
            | Generado: {{ $fechaGeneracion }}
        </div>
    </div>

    <table class="summary-grid">
        <tr>
            <td>
                <span class="label">Total Vendido</span>
                <span class="value">$ {{ number_format($resumen['total_ventas'], 2, ',', '.') }}</span>
            </td>
            <td>
                <span class="label">Ventas Realizadas</span>
                <span class="value">{{ $resumen['cantidad_ventas'] }}</span>
            </td>
            <td>
                <span class="label">Ticket Promedio</span>
                <span class="value">$ {{ number_format($resumen['ticket_promedio'], 2, ',', '.') }}</span>
            </td>
        </tr>
    </table>

    <h3 style="font-size: 10pt; color: #1e293b; margin: 0 0 8px 0; text-transform: uppercase;">Medios de Pago</h3>
    <table class="data">
        <thead>
            <tr>
                <th>Método</th>
                <th class="right">Ventas</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($metodosPago as $mp)
                <tr>
                    <td>{{ $mp['label'] }}</td>
                    <td class="right">{{ $mp['cantidad'] }}</td>
                    <td class="right">$ {{ number_format($mp['total'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align: center; color: #94a3b8;">Sin ventas en el período</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3 style="font-size: 10pt; color: #1e293b; margin: 0 0 8px 0; text-transform: uppercase;">Productos Más Vendidos</h3>
    <table class="data">
        <thead>
            <tr>
                <th>Producto</th>
                <th class="right">Cantidad</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($topProductos as $p)
                <tr>
                    <td>{{ $p['nombre'] }}</td>
                    <td class="right">{{ number_format($p['cantidad'], 2, ',', '.') }}</td>
                    <td class="right">$ {{ number_format($p['total'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align: center; color: #94a3b8;">Sin ventas en el período</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">VendAR — Reporte generado el {{ $fechaGeneracion }}</div>
</body>
</html>
