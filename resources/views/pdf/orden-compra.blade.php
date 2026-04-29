<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        /* Configuración de Hoja A4 y márgenes */
        @page { margin: 40px 50px 70px 50px; }
        body { 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            font-size: 9pt; 
            color: #1e293b; 
            line-height: 1.3; 
        }
        
        table { width: 100%; border-collapse: collapse; }
        
        /* Encabezado Principal */
        .header-section { border-bottom: 2px solid #1e293b; padding-bottom: 15px; margin-bottom: 20px; }
        .logo { max-height: 60px; margin-bottom: 5px; }
        .company-name { font-size: 16pt; font-weight: bold; color: #1e293b; margin: 0 0 5px 0; text-transform: uppercase; }
        .company-info { font-size: 8.5pt; color: #475569; line-height: 1.4; }

        /* Metadata Derecha (Compacta) */
        .doc-title { font-size: 18pt; font-weight: bold; color: #1e293b; text-transform: uppercase; margin: 0; text-align: right; }
        .meta-table { width: auto; float: right; margin-top: 5px; }
        .meta-table td { padding: 1px 0 1px 15px; text-align: right; font-size: 9pt; white-space: nowrap; }
        .nro-orden { color: #e11d48; font-weight: bold; font-size: 11pt; }

        /* Cajas de Información (Proveedor / Entrega) */
        .info-box { border: 1px solid #cbd5e1; background-color: #f8fafc; vertical-align: top; }
        .info-header { background-color: #334155; color: #ffffff; font-size: 8pt; font-weight: bold; padding: 5px 10px; text-transform: uppercase; }
        .info-content { padding: 10px; font-size: 9pt; height: 75px; vertical-align: top; }

        /* Tabla de Productos */
        .items-table { margin-top: 20px; border: 1px solid #cbd5e1; }
        .items-table th { background-color: #1e293b; color: #ffffff; padding: 8px; font-size: 8pt; text-transform: uppercase; text-align: left; }
        .items-table td { padding: 8px; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; }
        .row-alt { background-color: #f1f5f9; }

        /* Estilos de Totales */
        .totals-table { width: 100%; }
        .totals-table td { padding: 8px 12px; border: 1px solid #cbd5e1; text-align: right; }
        .total-bg { background-color: #1e293b; color: #ffffff; font-weight: bold; font-size: 12pt; }

        /* Footer estático */
        .footer { position: fixed; bottom: -30px; left: 0; right: 0; border-top: 1px solid #cbd5e1; padding-top: 5px; font-size: 8pt; color: #64748b; }
        
        .clear { clear: both; }
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
                    <strong>Dirección:</strong> {{ $config['direccion'] ?? $config['direccion_empresa'] ?? $orden->sucursal->direccion }}<br>
                    <strong>Tel:</strong> {{ $config['telefono'] ?? $config['telefono_empresa'] ?? $orden->sucursal->telefono }}
                </div>
            </td>
            <td width="40%" style="vertical-align: top;">
                <h1 class="doc-title">Orden de Compra</h1>
                <table class="meta-table">
                    <tr>
                        <td><strong>Nro. de Orden:</strong></td>
                        <td class="nro-orden">#{{ str_pad($orden->id, 6, '0', STR_PAD_LEFT) }}</td>
                    </tr>
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
                        <td><strong>Estado:</strong></td>
                        <td><strong>{{ strtoupper($orden->estado) }}</strong></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-bottom: 20px;">
        <tr>
            <td width="48%" class="info-box">
                <div class="info-header">Información del Proveedor</div>
                <div class="info-content">
                    <strong style="font-size: 10pt;">{{ $orden->proveedor->razon_social ?? $orden->proveedor->nombre }}</strong><br>
                    CUIT: {{ $orden->proveedor->cuit ?? 'S/D' }}<br>
                    Tel: {{ $orden->proveedor->telefono ?? '-' }}<br>
                    Email: {{ $orden->proveedor->email ?? '-' }}
                </div>
            </td>
            <td width="4%"></td>
            <td width="48%" class="info-box">
                <div class="info-header">Lugar de Entrega</div>
                <div class="info-content">
                    <strong style="font-size: 10pt;">{{ $orden->sucursal->nombre }}</strong><br>
                    Dirección: {{ $orden->sucursal->direccion }}<br>
                    Tel: {{ $orden->sucursal->telefono }}<br>
                    <br>
                    <strong>Entrega Esperada:</strong> {{ $orden->fecha_entrega_esperada ? $orden->fecha_entrega_esperada->format('d/m/Y') : 'A coordinar' }}
                </div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th width="50%">Descripción del Producto</th>
                <th width="10%" style="text-align: center;">Cant.</th>
                <th width="20%" style="text-align: right;">Unitario (Est.)</th>
                <th width="20%" style="text-align: right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orden->detalles as $key => $item)
            <tr class="{{ $key % 2 == 0 ? '' : 'row-alt' }}">
                <td>{{ $item->producto->nombre }}</td>
                <td style="text-align: center;"><strong>{{ $item->cantidad_pedida }}</strong></td>
                <td style="text-align: right;">$ {{ number_format($item->costo_unitario_estimado, 2, ',', '.') }}</td>
                <td style="text-align: right;"><strong>$ {{ number_format($item->subtotal_estimado, 2, ',', '.') }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width: 100%; margin-top: 10px;">
        <tr>
            <td width="60%" style="vertical-align: top; padding-right: 20px;">
                @if($orden->observaciones)
                    <div style="font-weight: bold; font-size: 8pt; text-transform: uppercase; color: #1e293b; margin-bottom: 5px;">
                        Observaciones y Notas:
                    </div>
                    <div style="padding: 10px; border-left: 3px solid #cbd5e1; background-color: #f8fafc; font-size: 8.5pt; color: #475569;">
                        {{ $orden->observaciones }}
                    </div>
                @endif
            </td>

            <td width="40%" style="vertical-align: top;">
                <table class="totals-table">
                    <tr>
                        <td style="text-align: left; font-weight: bold; color: #64748b;">SUBTOTAL</td>
                        <td>$ {{ number_format($orden->total_estimado, 2, ',', '.') }}</td>
                    </tr>
                    <tr class="total-bg">
                        <td style="text-align: left;">TOTAL ARS</td>
                        <td>$ {{ number_format($orden->total_estimado, 2, ',', '.') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ $config['nombre_comercio'] ?? 'Sistema de Gestión' }} - Reporte de Orden de Compra
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_text(480, 810, "Página {PAGE_NUM} de {PAGE_COUNT}", "Helvetica", 8, array(0.4, 0.4, 0.4));
        }
    </script>

</body>
</html>