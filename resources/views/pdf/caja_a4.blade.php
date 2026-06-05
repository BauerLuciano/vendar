<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        /* Configuración Estricta de Hoja A4 y márgenes */
        @page { margin: 40px 50px 60px 50px; }
        body { 
            font-family: 'Helvetica', 'Arial', sans-serif; 
            font-size: 9pt; 
            color: #1e293b; 
            line-height: 1.4; 
        }
        
        table { width: 100%; border-collapse: collapse; }
        
        /* Encabezado Estructural Sin Floats (Cero Colisiones) */
        .header-table { border-bottom: 2px solid #1e293b; padding-bottom: 12px; margin-bottom: 20px; width: 100%; }
        .logo { max-height: 65px; max-width: 140px; display: block; margin-bottom: 6px; }
        .company-name { font-size: 15pt; font-weight: bold; color: #1e293b; margin: 0 0 4px 0; text-transform: uppercase; letter-spacing: -0.5px; }
        .company-info { font-size: 8.5pt; color: #475569; line-height: 1.4; }

        .doc-title { font-size: 17pt; font-weight: bold; color: #1e293b; text-transform: uppercase; margin: 0 0 8px 0; text-align: right; }
        .meta-table { width: 100%; }
        .meta-table td { padding: 3px 0 3px 12px; text-align: right; font-size: 9pt; }
        .nro-orden { color: #e11d48; font-weight: bold; font-size: 11pt; }

        /* Cajas de Información Secundarias */
        .info-container-table { width: 100%; margin-bottom: 25px; table-layout: fixed; }
        .info-box { border: 1px solid #cbd5e1; background-color: #f8fafc; vertical-align: top; }
        .info-header { background-color: #334155; color: #ffffff; font-size: 8pt; font-weight: bold; padding: 6px 10px; text-transform: uppercase; }
        .info-content { padding: 10px; font-size: 9pt; height: 65px; vertical-align: top; line-height: 1.5; }

        /* Estructura Limpia de Tablas de Datos */
        .items-table { margin-top: 10px; border: 1px solid #cbd5e1; width: 100%; table-layout: fixed; }
        .items-table th { background-color: #1e293b; color: #ffffff; padding: 9px 8px; font-size: 8pt; text-transform: uppercase; text-align: left; border: 1px solid #1e293b; }
        .items-table td { padding: 8px; border: 1px solid #cbd5e1; vertical-align: middle; }
        .row-alt { background-color: #f8fafc; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Estilos de Bloques de Totales */
        .total-bg { background-color: #1e293b; color: #ffffff; font-weight: bold; font-size: 11pt; }
        .total-bg td { border: 1px solid #1e293b !important; }

        /* Firmas de Auditoría Prolijas con Bordes CSS (Cero caracteres de rayas) */
        .tabla-firmas { margin-top: 70px; width: 100%; table-layout: fixed; }
        .celda-firma { text-align: center; vertical-align: top; padding: 0 15px; }
        .linea-firma { border-top: 1px solid #94a3b8; margin-bottom: 5px; width: 100%; }
        .texto-firma { font-size: 8.5pt; color: #475569; font-weight: bold; text-transform: uppercase; }

        /* Footer del Reporte */
        .footer { position: fixed; bottom: -25px; left: 0; right: 0; border-top: 1px solid #cbd5e1; padding-top: 5px; font-size: 8pt; color: #64748b; }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td width="58%" style="vertical-align: top; padding-bottom: 10px;">
                @if($logo)
                    <img src="{{ $logo }}" class="logo">
                @endif
                <div class="company-name">{{ $config['nombre_empresa'] ?? $config['razon_social'] ?? 'MI NEGOCIO' }}</div>
                <div class="company-info">
                    @if(!empty($config['cuit'])) CUIT: {{ $config['cuit'] }}<br> @endif
                    <strong>Dirección:</strong> {{ $config['direccion'] ?? $config['direccion_empresa'] ?? $sucursal?->direccion ?? 'Dirección Central' }}<br>
                    <strong>Teléfono:</strong> {{ $config['telefono'] ?? $config['telefono_empresa'] ?? $sucursal?->telefono ?? '-' }}
                </div>
            </td>
            <td width="42%" style="vertical-align: top; padding-bottom: 10px;">
                <h1 class="doc-title">Planilla de Arqueo</h1>
                <table class="meta-table">
                    <tr>
                        <td width="50%"><strong>Sesión Nro:</strong></td>
                        <td width="50%" class="nro-orden">#{{ str_pad($turno->id, 6, '0', STR_PAD_LEFT) }}</td>
                    </tr>
                    <tr>
                        <td><strong>Apertura:</strong></td>
                        <td>{{ \Carbon\Carbon::parse($turno->fecha_apertura)->format('d/m/Y H:i') }}</td>
                    </tr>
                    <tr>
                        <td><strong>Cierre:</strong></td>
                        <td>{{ $turno->fecha_cierre ? \Carbon\Carbon::parse($turno->fecha_cierre)->format('d/m/Y H:i') : 'Sin cerrar' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Fecha Emisión:</strong></td>
                        <td>{{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="info-container-table">
        <tr>
            <td width="48%" class="info-box">
                <div class="info-header">Detalles de la Sesión</div>
                <div class="info-content">
                    <strong>Caja Operada:</strong> {{ $turno->caja?->nombre ?? 'Caja General' }}<br>
                    <strong>Usuario Apertura:</strong> {{ $turno->usuarioApertura?->name ?? 'Sistema' }}<br>
                    <strong>Usuario Cierre:</strong> {{ $turno->usuarioCierre?->name ?? 'Pendiente' }}
                </div>
            </td>
            <td width="4%"></td>
            <td width="48%" class="info-box">
                <div class="info-header">Observaciones de Cierre</div>
                <div class="info-content" style="font-style: italic; color: #475569;">
                    {{ $turno->observaciones_cierre ?: 'Sin observaciones ni justificaciones de descuadre registradas en este turno.' }}
                </div>
            </td>
        </tr>
    </table>

    <div style="font-weight: bold; font-size: 10pt; text-transform: uppercase; color: #1e293b; margin-bottom: 6px;">
        Resumen Contable del Turno:
    </div>
    <table class="items-table">
        <thead>
            <tr>
                <th width="34%">Medio / Método de Pago</th>
                <th width="22%" class="text-right">Sistema (Esperado)</th>
                <th width="22%" class="text-right">Cajero (Declarado)</th>
                <th width="22%" class="text-right">Diferencia</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Físico (Efectivo)</td>
                <td class="text-right">$ {{ number_format($totales['efectivo_esperado'], 2, ',', '.') }}</td>
                <td class="text-right">$ {{ number_format($totales['efectivo_real'], 2, ',', '.') }}</td>
                <td class="text-right" style="font-weight: bold; color: {{ ($totales['efectivo_real'] - $totales['efectivo_esperado']) < -0.01 ? '#e11d48' : '#1e293b' }}">
                    $ {{ number_format($totales['efectivo_real'] - $totales['efectivo_esperado'], 2, ',', '.') }}
                </td>
            </tr>
            <tr class="row-alt">
                <td>Mercado Pago</td>
                <td class="text-right">$ {{ number_format($totales['mp_esperado'], 2, ',', '.') }}</td>
                <td class="text-right">$ {{ number_format($totales['mp_real'], 2, ',', '.') }}</td>
                <td class="text-right" style="font-weight: bold; color: {{ ($totales['mp_real'] - $totales['mp_esperado']) < -0.01 ? '#e11d48' : '#1e293b' }}">
                    $ {{ number_format($totales['mp_real'] - $totales['mp_esperado'], 2, ',', '.') }}
                </td>
            </tr>
            <tr>
                <td>Transferencias / Otros</td>
                <td class="text-right">$ {{ number_format($totales['transf_esperado'], 2, ',', '.') }}</td>
                <td class="text-right">$ {{ number_format($totales['transf_real'], 2, ',', '.') }}</td>
                <td class="text-right" style="font-weight: bold; color: {{ ($totales['transf_real'] - $totales['transf_esperado']) < -0.01 ? '#e11d48' : '#1e293b' }}">
                    $ {{ number_format($totales['transf_real'] - $totales['transf_esperado'], 2, ',', '.') }}
                </td>
            </tr>
            <tr class="total-bg">
                <td style="font-weight: bold;">TOTAL GENERAL</td>
                <td class="text-right" style="font-weight: bold;">$ {{ number_format($totales['total_esperado'], 2, ',', '.') }}</td>
                <td class="text-right" style="font-weight: bold;">$ {{ number_format($totales['total_real'], 2, ',', '.') }}</td>
                <td class="text-right" style="font-weight: bold;">$ {{ number_format($totales['total_real'] - $totales['total_esperado'], 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div style="font-weight: bold; font-size: 10pt; text-transform: uppercase; color: #1e293b; margin-top: 25px; margin-bottom: 6px;">
        Detalle Analítico de Movimientos:
    </div>
    <table class="items-table">
        <thead>
            <tr>
                <th width="12%">Hora</th>
                <th width="14%">Tipo</th>
                <th width="42%">Concepto y Descripción</th>
                <th width="16%">Método</th>
                <th width="16%" class="text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @foreach($movimientos as $key => $item)
            <tr class="{{ $key % 2 == 0 ? '' : 'row-alt' }}">
                <td class="text-center">{{ \Carbon\Carbon::parse($item->created_at)->format('H:i') }}</td>
                <td style="font-weight: bold; color: {{ $item->tipo === 'INGRESO' ? '#10b981' : '#e11d48' }}">{{ $item->tipo }}</td>
                <td>
                    <strong>{{ strtoupper(str_replace('_', ' ', $item->concepto)) }}</strong>
                    @if($item->descripcion)
                        <br><span style="color: #64748b; font-size: 7.5pt; font-style: italic;">{{ $item->descripcion }}</span>
                    @endif
                </td>
                <td>{{ \App\Enums\MetodoPago::fromString($item->metodo_pago)->label() }}</td>
                <td class="text-right" style="font-weight: bold; color: {{ $item->tipo === 'INGRESO' ? '#10b981' : '#e11d48' }}">
                    {{ $item->tipo === 'EGRESO' ? '-' : '+' }} $ {{ number_format($item->monto, 2, ',', '.') }}
                </td>
            </tr>
            @endforeach
            @if($movimientos->isEmpty())
                <tr>
                    <td colspan="5" style="text-align: center; color: #64748b; font-style: italic; padding: 25px;">
                        No se registraron movimientos contables durante este turno de caja.
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    <table class="tabla-firmas">
        <tr>
            <td class="celda-firma" width="45%">
                <div class="linea-firma"></div>
                <div class="texto-firma">Firma Usuario Caja: {{ $turno->usuarioCierre?->name ?? 'Pendiente' }}</div>
            </td>
            <td width="10%"></td>
            <td class="celda-firma" width="45%">
                <div class="linea-firma"></div>
                <div class="texto-firma">Firma Administrador</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ $config['nombre_comercio'] ?? 'Sistema de Gestión VendAR' }} - Planilla de Arqueo Analítico de Caja Diaria
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_text(480, 810, "Página {PAGE_NUM} de {PAGE_COUNT}", "Helvetica", 8, array(0.4, 0.4, 0.4));
        }
    </script>

</body>
</html>