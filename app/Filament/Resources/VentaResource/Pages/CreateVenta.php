<?php

namespace App\Filament\Resources\VentaResource\Pages;

use App\Filament\Resources\VentaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateVenta extends CreateRecord
{
    protected static string $resource = VentaResource::class;

    protected function afterCreate(): void
    {
        $venta = $this->record;
        $totalVenta = 0;

        foreach ($venta->detalles as $detalle) {
            // 1. Acumular el total (el subtotal ya se guardó gracias al Hidden)
            $totalVenta += $detalle->subtotal;

            // 2. Descontar Stock de la SUCURSAL correcta (Tabla intermedia)
            $sucursalId = $venta->sucursal_id;
            
            // Buscamos el registro en la tabla pivote para este producto y esta sucursal
            $relacionPivot = $detalle->producto->branches()->where('branch_id', $sucursalId)->first();

            if ($relacionPivot) {
                // Si existe la relación, le restamos la cantidad a "cantidad_fisica"
                $nuevaCantidad = $relacionPivot->pivot->cantidad_fisica - $detalle->cantidad;
                
                $detalle->producto->branches()->updateExistingPivot($sucursalId, [
                    'cantidad_fisica' => $nuevaCantidad
                ]);
            }
        }

        // 3. Actualizar total de la cabecera
        $venta->update(['total' => $totalVenta]);

        // 4. Lógica de Cliente (si no es consumidor final)
        if ($venta->consumidor_id) {
            $cliente = $venta->consumidor;

            // Sumar puntos
            $cliente->increment('puntos_acumulados', floor($totalVenta / 100));

            // Si es Fiado, sumamos la deuda
            if ($venta->metodo_pago === 'fiado') {
                $cuenta = $cliente->cuentaCorriente;
                if ($cuenta) {
                    $cuenta->increment('saldo_deudor', $totalVenta);
                }
            }
        }
    }
}

