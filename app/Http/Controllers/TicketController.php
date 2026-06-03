<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\Configuracion;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class TicketController extends Controller
{
        public function imprimir(Venta $venta)
    {
        $user = auth()->user();
        $comercioId = $user->branch?->comercio_id;
        if ($comercioId && $venta->turno->caja->sucursal->comercio_id !== $comercioId) {
            abort(403);
        }

        $venta->load(['detalles.producto', 'consumidor', 'turno.caja.sucursal', 'turno.cajero']);
        
        // Obtenemos todas las configuraciones
        $config = \App\Models\Configuracion::pluck('valor', 'clave')->toArray();

        //Debugueamos la configuración para asegurarnos de que se está obteniendo correctamente
        //dd($config); 

        // Buscamos el formato y lo pasamos a MAYÚSCULAS para que no falle por una letra
        $formato = strtoupper($config['formato_impresion'] ?? '58mm');

        $datosEmpresa = [
            'nombre' => $config['nombre_empresa'] ?? 'VendAR',
            'direccion' => $config['direccion_empresa'] ?? '',
            'telefono' => $config['telefono_empresa'] ?? '',
            'mensaje_pie' => $config['ticket_mensaje_pie'] ?? 'Gracias por su compra',
            'logo' => $config['logo_empresa'] ?? null,
        ];

        // 🔥 SI ES A4, GENERAMOS PDF PROFESIONAL
        if ($formato === 'A4') {
            // Asegurate de tener instalado: composer require barryvdh/laravel-dompdf
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('tickets.a4', compact('venta', 'datosEmpresa'));
            
            // Seteamos el tamaño de papel a A4 explícitamente
            $pdf->setPaper('a4', 'portrait');
            
            return $pdf->stream("factura_{$venta->id}.pdf");
        }

        // SI ES TÉRMICO (58MM o 80MM)
        // Usamos strtolower porque los archivos suelen estar en minúscula: 58mm.blade.php
        $vistaTermica = strtolower($formato); 
        
        return view("tickets.{$vistaTermica}", compact('venta', 'datosEmpresa'));
    }
}