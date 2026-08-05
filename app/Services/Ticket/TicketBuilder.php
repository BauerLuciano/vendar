<?php

namespace App\Services\Ticket;

use App\Facturacion\Domain\Entities\ComprobanteFiscal;
use App\Facturacion\Domain\ValueObjects\CondicionFiscal;
use App\Models\Configuracion;
use App\Models\ConfiguracionFiscalComercio;
use App\Models\Venta;
use Illuminate\Support\Facades\Storage;

class TicketBuilder
{
    public static function build(Venta $venta, ?ComprobanteFiscal $comprobante = null): TicketData
    {
        $venta->loadMissing(['detalles.producto', 'consumidor', 'turno.caja.sucursal.comercio', 'turno.cajero']);

        $config = Configuracion::pluck('valor', 'clave')->toArray();

        $formato = strtoupper($config['formato_impresion'] ?? '80mm');

        $empresa = [
            'nombre' => $config['nombre_empresa'] ?? 'VendAR',
            'cuit' => $config['cuit'] ?? '',
            'direccion' => $config['direccion'] ?? '',
            'telefono' => $config['telefono'] ?? '',
            'logo' => $venta->turno?->caja?->sucursal?->comercio?->url_logo
                            ?? (! empty($config['logo_empresa']) ? Storage::url($config['logo_empresa']) : null),
            'mensaje_pie' => $config['ticket_mensaje_pie'] ?? 'Gracias por su compra',
        ];

        $ventaData = [
            'id' => $venta->id,
            'numero' => str_pad($venta->id, 8, '0', STR_PAD_LEFT),
            'fecha' => $venta->created_at->format('d/m/Y'),
            'hora' => $venta->created_at->format('H:i'),
            'fecha_completa' => $venta->created_at->format('d/m/Y H:i'),
            'metodo_pago_display' => $venta->metodo_pago_display,
            'estado' => $venta->estado->value,
        ];

        $cliente = [
            'nombre' => $venta->consumidor?->nombre ?? 'Consumidor Final',
            'documento' => $venta->consumidor?->documento ?? '',
            'direccion' => $venta->consumidor?->direccion ?? '',
            'email' => $venta->consumidor?->email ?? '',
            'telefono' => $venta->consumidor?->telefono ?? '',
        ];

        $vendedor = [
            'nombre' => $venta->turno?->cajero?->name ?? '',
        ];

        $sucursal = [
            'nombre' => $venta->turno?->caja?->sucursal?->nombre ?? '',
            'direccion' => $venta->turno?->caja?->sucursal?->direccion ?? '',
            'telefono' => $venta->turno?->caja?->sucursal?->telefono ?? '',
        ];

        $items = $venta->detalles->map(function ($detalle) {
            return [
                'nombre' => $detalle->producto?->nombre ?? 'Producto',
                'cantidad' => (float) $detalle->cantidad,
                'precio_unitario' => (float) $detalle->precio_unitario,
                'subtotal' => (float) $detalle->subtotal,
            ];
        })->toArray();

        $totales = [
            'total' => (float) $venta->total,
            'recargo' => (float) ($venta->recargo_monto ?? 0),
        ];

        $pagos = collect($venta->pagos_display)->map(function ($pago) {
            return [
                'metodo' => $pago['metodo_pago'],
                'monto' => (float) $pago['monto'],
                'label' => $pago['label'],
            ];
        })->toArray();

        $fiscal = null;

        if ($comprobante !== null && $comprobante->cae() !== null) {
            $fiscal = self::fiscal($venta, $comprobante);
        }

        return new TicketData(
            empresa: $empresa,
            venta: $ventaData,
            cliente: $cliente,
            vendedor: $vendedor,
            sucursal: $sucursal,
            items: $items,
            totales: $totales,
            pagos: $pagos,
            formato: $formato,
            fiscal: $fiscal,
        );
    }

    /**
     * Bloque fiscal de la vista (arquitectura §12): QR ARCA, CAE, vencimiento,
     * desglose de IVA neto/IVA/total por alícuota y datos fiscales del emisor.
     * El número de comprobante proviene del ledger (§18.2); el ID de venta queda
     * solo para el ticket no fiscal.
     */
    private static function fiscal(Venta $venta, ComprobanteFiscal $comprobante): array
    {
        $receptor = $comprobante->receptor();
        $emisor = $comprobante->emisor();
        $totales = $comprobante->totales();

        $configFiscal = ConfiguracionFiscalComercio::where('comercio_id', $comprobante->comercioId())->first();

        $qr = $comprobante->qr();

        return [
            'tipo' => $comprobante->esNotaCredito() ? 'NOTA DE CRÉDITO' : 'FACTURA',
            'letra' => $comprobante->letra()->value,
            'numero' => self::numeroCompleto($comprobante),
            'fecha_emision' => $venta->created_at->format('d/m/Y H:i'),
            'cae' => $comprobante->cae()?->codigo(),
            'vencimiento_cae' => $comprobante->vencimientoCae()->format('d/m/Y'),
            'qr' => $qr,
            'qr_image' => $qr !== null ? app(QrArcaRenderer::class)->renderizar($qr) : null,
            'desglose' => self::desglosePorAlicuota($comprobante),
            'neto' => (float) $totales->neto()->valor(),
            'iva' => (float) $totales->iva()->valor(),
            'total' => (float) $totales->total()->valor(),
            'receptor' => [
                'razon_social' => $receptor?->razonSocial(),
                'cuit' => $receptor?->cuit()?->valor(),
                'domicilio' => $receptor?->domicilioFiscal(),
            ],
            'emisor' => [
                'razon_social' => $emisor->razonSocial(),
                'cuit' => $emisor->cuit()->valor(),
                'cuit_formateado' => $emisor->cuit()->formateado(),
                'condicion_fiscal' => self::condicionFiscalLabel($emisor->condicionFiscal()),
                'domicilio_fiscal' => $configFiscal?->domicilio_fiscal,
            ],
        ];
    }

    private static function numeroCompleto(ComprobanteFiscal $comprobante): string
    {
        return $comprobante->numeroCompleto();
    }

    /**
     * Desglose de IVA neto/IVA/total agrupado por alícuota (arquitectura §12).
     * Se recalcula desde el snapshot de alícuota del ledger (invariante 12).
     */
    private static function desglosePorAlicuota(ComprobanteFiscal $comprobante): array
    {
        $lineas = [];

        foreach ($comprobante->detalles() as $detalle) {
            $alicuota = $detalle->alicuota()->valor();

            if (! isset($lineas[$alicuota])) {
                $lineas[$alicuota] = ['alicuota' => $alicuota, 'neto' => 0.0, 'iva' => 0.0];
            }

            $lineas[$alicuota]['neto'] += $detalle->neto()->valor();
            $lineas[$alicuota]['iva'] += $detalle->iva()->valor();
        }

        $desglose = [];

        foreach ($lineas as $linea) {
            $neto = round($linea['neto'], 2);
            $iva = round($linea['iva'], 2);

            $desglose[] = [
                'alicuota' => $linea['alicuota'],
                'neto' => $neto,
                'iva' => $iva,
                'total' => round($neto + $iva, 2),
            ];
        }

        usort($desglose, fn ($a, $b) => $a['alicuota'] <=> $b['alicuota']);

        return $desglose;
    }

    private static function condicionFiscalLabel(CondicionFiscal $condicion): string
    {
        return match ($condicion) {
            CondicionFiscal::RESPONSABLE_INSCRIPTO => 'IVA Responsable Inscripto',
            CondicionFiscal::MONOTRIBUTO => 'Monotributista',
            CondicionFiscal::EXENTO => 'Sujeto Exento',
            CondicionFiscal::NO_ALCANZADO => 'IVA No Alcanzado',
            CondicionFiscal::CONSUMIDOR_FINAL => 'Consumidor Final',
        };
    }
}
