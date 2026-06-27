<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 35px 40px 60px 40px; }
        body { 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            font-size: 8pt; 
            color: #1e293b; 
            line-height: 1.3; 
        }
        table { width: 100%; border-collapse: collapse; }
        .header-separator { border-bottom: 2px solid #1e293b; margin: 12px 0 18px 0; }

        .logo { max-height: 55px; margin-bottom: 4px; }
        .company-name { font-size: 14pt; font-weight: bold; color: #1e293b; margin: 0 0 3px 0; text-transform: uppercase; }
        .company-info { font-size: 7.5pt; color: #475569; line-height: 1.4; margin-bottom: 8px; }
        .doc-title { font-size: 16pt; font-weight: bold; color: #1e293b; text-transform: uppercase; margin: 0 0 6px 0; text-align: right; }
        .meta-table { width: auto; margin-left: auto; }
        .meta-table td { padding: 1px 0 1px 16px; text-align: right; font-size: 8pt; white-space: nowrap; }
        .items-table { margin-top: 10px; border: 1px solid #cbd5e1; }
        .items-table th { background-color: #1e293b; color: #ffffff; padding: 6px 8px; font-size: 7pt; text-transform: uppercase; text-align: left; }
        .items-table td { padding: 5px 8px; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #e2e8f0; font-size: 7.5pt; }
        .row-alt { background-color: #f8fafc; }
        .estado-activo { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 6.5pt; font-weight: bold; text-transform: uppercase; background-color: #dcfce7; color: #166534; }
        .estado-inactivo { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 6.5pt; font-weight: bold; text-transform: uppercase; background-color: #fee2e2; color: #991b1b; }
        .footer { position: fixed; bottom: -25px; left: 0; right: 0; border-top: 1px solid #cbd5e1; padding-top: 4px; font-size: 7pt; color: #64748b; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td width="55%" style="vertical-align: top;">
                @if($logo)
                    <img src="{{ $logo }}" class="logo"><br>
                @endif
                <div class="company-name">{{ $config['nombre_empresa'] ?? $comercio }}</div>
                <div class="company-info">
                    @if(!empty($config['cuit'])) CUIT: {{ $config['cuit'] }}<br> @endif
                    @if(!empty($config['direccion_empresa'])) <strong>Dirección:</strong> {{ $config['direccion_empresa'] }}<br> @endif
                    @if(!empty($config['telefono_empresa'])) <strong>Tel:</strong> {{ $config['telefono_empresa'] }} @endif
                </div>
            </td>
            <td width="45%" style="vertical-align: top;">
                <h1 class="doc-title">Listado de Productos</h1>
                <table class="meta-table">
                    <tr>
                        <td><strong>Exportado por:</strong></td>
                        <td>{{ $usuario }}</td>
                    </tr>
                    <tr>
                        <td><strong>Fecha:</strong></td>
                        <td>{{ $fechaGeneracion }}</td>
                    </tr>
                    <tr>
                        <td><strong>Comercio:</strong></td>
                        <td>{{ $config['nombre_empresa'] ?? $comercio }}</td>
                    </tr>
                    <tr>
                        <td><strong>Total productos:</strong></td>
                        <td>{{ $productos->count() }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <div class="header-separator"></div>

    <table class="items-table">
        <thead>
            <tr>
                <th width="3%">#</th>
                <th width="22%">Producto</th>
                <th width="10%">Código</th>
                <th width="12%">Categoría</th>
                <th width="10%">Marca</th>
                <th width="7%" class="num">P. Costo</th>
                <th width="7%" class="num">P. Venta</th>
                <th width="6%" class="num">Stock</th>
                <th width="5%" class="text-center">Ud.</th>
                <th width="6%" class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($productos as $key => $p)
            <tr class="{{ $key % 2 == 0 ? '' : 'row-alt' }}">
                <td class="font-bold" style="color: #94a3b8;">{{ $key + 1 }}</td>
                <td class="font-bold">{{ $p->nombre }}</td>
                <td style="font-family: monospace; font-size: 7pt; color: #64748b;">{{ $p->codigo_barras }}</td>
                <td>{{ $p->categoria?->nombreCategoria ?? '—' }}</td>
                <td>{{ $p->marca?->nombreMarca ?? '—' }}</td>
                <td class="num">$ {{ number_format($p->precio_costo, 2, ',', '.') }}</td>
                <td class="num font-bold">$ {{ number_format($p->precio_venta, 2, ',', '.') }}</td>
                <td class="num">{{ (int) $p->sucursales->sum(fn($s) => (float) $s->pivot->cantidad_fisica) }}</td>
                <td class="text-center" style="font-size: 6.5pt; color: #64748b;">{{ $p->unidad_medida }}</td>
                <td class="text-center">
                    @if($p->estado)
                        <span class="estado-activo">Activo</span>
                    @else
                        <span class="estado-inactivo">Inactivo</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        {{ $config['nombre_empresa'] ?? 'Sistema de Gestión' }} - Listado de Productos - Generado por VendAR
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_text(700, 560, "Página {PAGE_NUM} de {PAGE_COUNT}", "Helvetica", 7, array(0.4, 0.4, 0.4));
        }
    </script>
</body>
</html>
