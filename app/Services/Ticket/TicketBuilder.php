<?php

namespace App\Services\Ticket;

use App\Models\Venta;
use App\Models\Configuracion;
use Illuminate\Support\Facades\Storage;

class TicketBuilder
{
    public static function build(Venta $venta): TicketData
    {
        $venta->loadMissing(['detalles.producto', 'consumidor', 'turno.caja.sucursal.comercio', 'turno.cajero']);

        $config = Configuracion::pluck('valor', 'clave')->toArray();

        $formato = strtoupper($config['formato_impresion'] ?? '80mm');

        $empresa = [
            'nombre'      => $config['nombre_empresa'] ?? 'VendAR',
            'cuit'        => $config['cuit'] ?? '',
            'direccion'   => $config['direccion'] ?? '',
            'telefono'    => $config['telefono'] ?? '',
            'logo'        => $venta->turno?->caja?->sucursal?->comercio?->url_logo
                            ?? ($config['logo_empresa'] ? Storage::url($config['logo_empresa']) : null),
            'mensaje_pie' => $config['ticket_mensaje_pie'] ?? 'Gracias por su compra',
        ];

        $ventaData = [
            'id'                 => $venta->id,
            'numero'             => str_pad($venta->id, 8, '0', STR_PAD_LEFT),
            'fecha'              => $venta->created_at->format('d/m/Y'),
            'hora'               => $venta->created_at->format('H:i'),
            'fecha_completa'     => $venta->created_at->format('d/m/Y H:i'),
            'metodo_pago_display'=> $venta->metodo_pago_display,
            'estado'             => $venta->estado->value,
        ];

        $cliente = [
            'nombre'    => $venta->consumidor?->nombre ?? 'Consumidor Final',
            'documento' => $venta->consumidor?->documento ?? '',
            'direccion' => $venta->consumidor?->direccion ?? '',
            'email'     => $venta->consumidor?->email ?? '',
            'telefono'  => $venta->consumidor?->telefono ?? '',
        ];

        $vendedor = [
            'nombre' => $venta->turno?->cajero?->name ?? '',
        ];

        $sucursal = [
            'nombre'    => $venta->turno?->caja?->sucursal?->nombre ?? '',
            'direccion' => $venta->turno?->caja?->sucursal?->direccion ?? '',
            'telefono'  => $venta->turno?->caja?->sucursal?->telefono ?? '',
        ];

        $items = $venta->detalles->map(function ($detalle) {
            return [
                'nombre'          => $detalle->producto?->nombre ?? 'Producto',
                'cantidad'        => (float) $detalle->cantidad,
                'precio_unitario' => (float) $detalle->precio_unitario,
                'subtotal'        => (float) $detalle->subtotal,
            ];
        })->toArray();

        $totales = [
            'total'    => (float) $venta->total,
            'recargo'  => (float) ($venta->recargo_monto ?? 0),
        ];

        $pagos = collect($venta->pagos_display)->map(function ($pago) {
            return [
                'metodo' => $pago['metodo_pago'],
                'monto'  => (float) $pago['monto'],
                'label'  => $pago['label'],
            ];
        })->toArray();

        return new TicketData(
            empresa:  $empresa,
            venta:    $ventaData,
            cliente:  $cliente,
            vendedor: $vendedor,
            sucursal: $sucursal,
            items:    $items,
            totales:  $totales,
            pagos:    $pagos,
            formato:  $formato,
        );
    }
}
