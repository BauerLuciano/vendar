<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 40px 50px 70px 50px; }
        body { 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            font-size: 9pt; 
            color: #1e293b; 
            line-height: 1.3; 
        }
        table { width: 100%; border-collapse: collapse; }
        .header-section { border-bottom: 2px solid #1e293b; padding-bottom: 15px; margin-bottom: 20px; }
        .logo { max-height: 60px; margin-bottom: 5px; }
        .company-name { font-size: 16pt; font-weight: bold; color: #1e293b; margin: 0 0 5px 0; text-transform: uppercase; }
        .company-info { font-size: 8.5pt; color: #475569; line-height: 1.4; }
        .doc-title { font-size: 18pt; font-weight: bold; color: #1e293b; text-transform: uppercase; margin: 0; text-align: right; }
        .meta-table { width: auto; float: right; margin-top: 5px; }
        .meta-table td { padding: 1px 0 1px 15px; text-align: right; font-size: 9pt; white-space: nowrap; }
        .info-box { border: 1px solid #cbd5e1; background-color: #f8fafc; vertical-align: top; }
        .info-header { background-color: #334155; color: #ffffff; font-size: 8pt; font-weight: bold; padding: 5px 10px; text-transform: uppercase; }
        .info-content { padding: 10px; font-size: 9pt; vertical-align: top; }
        .items-table { margin-top: 20px; border: 1px solid #cbd5e1; }
        .items-table th { background-color: #1e293b; color: #ffffff; padding: 8px; font-size: 8pt; text-transform: uppercase; text-align: left; }
        .items-table td { padding: 8px; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; }
        .row-alt { background-color: #f1f5f9; }
        .totals-table { width: 100%; }
        .totals-table td { padding: 6px 12px; border: 1px solid #cbd5e1; text-align: right; }
        .total-bg { background-color: #1e293b; color: #ffffff; font-weight: bold; font-size: 12pt; }
        .footer { position: fixed; bottom: -30px; left: 0; right: 0; border-top: 1px solid #cbd5e1; padding-top: 5px; font-size: 8pt; color: #64748b; }
        .clear { clear: both; }
        .section-title { font-size: 11pt; font-weight: bold; color: #1e293b; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: -0.3px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 7.5pt; font-weight: bold; text-transform: uppercase; }
        .badge-verde { background-color: #dcfce7; color: #166534; }
        .badge-rojo { background-color: #fee2e2; color: #991b1b; }
        .badge-azul { background-color: #dbeafe; color: #1e40af; }
        .badge-ambar { background-color: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <table class="header-section">
        <tr>
            <td width="60%" style="vertical-align: top;">
                @if($logo)
                    <img src="{{ $logo }}" class="logo"><br>
                @endif
                <div class="company-name">{{ $config['nombre_empresa'] ?? $config['razon_social'] ?? 'MI NEGOCIO' }}</div>
                <div class="company-info">
                    @if(!empty($config['cuit'])) CUIT: {{ $config['cuit'] }}<br> @endif
                    <strong>Dirección:</strong> {{ $config['direccion'] ?? $config['direccion_empresa'] ?? '—' }}<br>
                    <strong>Tel:</strong> {{ $config['telefono'] ?? $config['telefono_empresa'] ?? '—' }}
                </div>
            </td>
            <td width="40%" style="vertical-align: top;">
                <h1 class="doc-title">Reporte del Dashboard</h1>
                <table class="meta-table">
                    <tr>
                        <td><strong>Fecha:</strong></td>
                        <td>{{ $fecha }}</td>
                    </tr>
                    <tr>
                        <td><strong>Hora:</strong></td>
                        <td>{{ $hora }}</td>
                    </tr>
                    <tr>
                        <td><strong>Emitido por:</strong></td>
                        <td>{{ $usuario }}</td>
                    </tr>
                    <tr>
                        <td><strong>Sucursal:</strong></td>
                        <td>{{ $sucursal }}</td>
                    </tr>
                    <tr>
                        <td><strong>Período:</strong></td>
                        <td>{{ \Carbon\Carbon::parse($fechaDesde)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($fechaHasta)->format('d/m/Y') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-bottom: 20px;">
        <tr>
            <td width="23%" class="info-box">
                <div class="info-header">Ventas del Período</div>
                <div class="info-content" style="text-align: center;">
                    <strong style="font-size: 14pt;">$ {{ number_format($ventasPeriodo, 2, ',', '.') }}</strong>
                </div>
            </td>
            <td width="2%"></td>
            <td width="23%" class="info-box">
                <div class="info-header">Deuda en Calle</div>
                <div class="info-content" style="text-align: center;">
                    <strong style="font-size: 14pt;">$ {{ number_format($deudaTotal, 2, ',', '.') }}</strong>
                </div>
            </td>
            <td width="2%"></td>
            <td width="23%" class="info-box">
                <div class="info-header">Cajas Abiertas</div>
                <div class="info-content" style="text-align: center;">
                    <strong style="font-size: 14pt;">{{ $cajasActivas }}</strong>
                </div>
            </td>
            <td width="2%"></td>
            <td width="23%" class="info-box">
                <div class="info-header">Pedidos Pendientes</div>
                <div class="info-content" style="text-align: center;">
                    <strong style="font-size: 14pt;">{{ $pedidosPendientes }}</strong>
                </div>
            </td>
        </tr>
    </table>

    @if($ventasPorDia->count() > 0)
        <div class="section-title">Ventas por Día</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ventasPorDia as $key => $dia)
                <tr class="{{ $key % 2 == 0 ? '' : 'row-alt' }}">
                    <td>{{ \Carbon\Carbon::parse($dia['fecha'])->format('d/m/Y') }}</td>
                    <td>{{ $dia['dia'] }}</td>
                    <td style="text-align: right; font-weight: bold;">$ {{ number_format($dia['total'], 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <br>
    @endif

    @if($topProductos->count() > 0)
        <div class="section-title">Top Productos Más Vendidos</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th width="60%">Producto</th>
                    <th width="15%" style="text-align: center;">Cantidad</th>
                    <th width="25%" style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topProductos as $key => $prod)
                <tr class="{{ $key % 2 == 0 ? '' : 'row-alt' }}">
                    <td><strong>{{ $prod->nombre }}</strong></td>
                    <td style="text-align: center;">{{ (int) $prod->cantidad }}</td>
                    <td style="text-align: right;">$ {{ number_format($prod->total, 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <br>
    @endif

    @if($bajoStock->count() > 0)
        <div class="section-title">Alertas de Stock ({{ $bajoStock->count() }})</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th width="45%">Producto</th>
                    <th width="25%">Sucursal</th>
                    <th width="15%" style="text-align: center;">Stock Actual</th>
                    <th width="15%" style="text-align: center;">Mínimo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bajoStock as $key => $item)
                <tr class="{{ $key % 2 == 0 ? '' : 'row-alt' }}">
                    <td>{{ $item->producto }}</td>
                    <td>{{ $item->sucursal }}</td>
                    <td style="text-align: center;"><strong style="color: #991b1b;">{{ (int) $item->cantidad_fisica }}</strong></td>
                    <td style="text-align: center;">{{ (int) $item->stock_minimo }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <br>
    @endif

    @if($pedidosLista->count() > 0)
        <div class="section-title">Pedidos Pendientes ({{ $pedidosPendientes }})</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th width="35%">Cliente</th>
                    <th width="25%">Fecha</th>
                    <th width="20%" style="text-align: center;">Estado</th>
                    <th width="20%" style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pedidosLista as $key => $p)
                <tr class="{{ $key % 2 == 0 ? '' : 'row-alt' }}">
                    <td><strong>{{ $p['cliente'] }}</strong></td>
                    <td>{{ $p['fecha'] }}</td>
                    <td style="text-align: center;"><strong>{{ strtoupper($p['estado']) }}</strong></td>
                    <td style="text-align: right;">$ {{ number_format($p['total'], 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <br>
    <table style="width: 50%; margin-left: auto;">
        <tr>
            <td style="text-align: left; font-weight: bold; color: #64748b; border: 1px solid #cbd5e1; padding: 6px 12px;">TOTAL VENTAS PERÍODO</td>
            <td style="border: 1px solid #cbd5e1; padding: 6px 12px; text-align: right; font-weight: bold;">$ {{ number_format($ventasPeriodo, 2, ',', '.') }}</td>
        </tr>
        <tr class="total-bg">
            <td style="text-align: left;">TOTAL ARS</td>
            <td style="text-align: right;">$ {{ number_format($ventasPeriodo, 2, ',', '.') }}</td>
        </tr>
    </table>

    <div class="footer">
        {{ $config['nombre_comercio'] ?? 'Sistema de Gestión' }} - Reporte del Dashboard
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_text(480, 810, "Página {PAGE_NUM} de {PAGE_COUNT}", "Helvetica", 8, array(0.4, 0.4, 0.4));
        }
    </script>
</body>
</html>
