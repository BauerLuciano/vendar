<?php

namespace App\Facturacion\Infrastructure\Persistence;

use App\Facturacion\Domain\Contracts\PendienteNcRepository;
use App\Models\PendienteNc;

final class EloquentPendienteNcRepository implements PendienteNcRepository
{
    public function registrarPendiente(array $datos): int
    {
        $pendiente = PendienteNc::query()
            ->where('comercio_id', $datos['comercio_id'])
            ->where('venta_id', $datos['venta_id'])
            ->where('tipo_operacion', $datos['tipo_operacion'])
            ->where('estado', 'pendiente')
            ->first();

        if ($pendiente !== null) {
            $pendiente->update([
                'motivo' => $datos['motivo'] ?? $pendiente->motivo,
                'items' => $datos['items'] ?? $pendiente->items,
                'monto_devuelto' => $datos['monto_devuelto'] ?? $pendiente->monto_devuelto,
                'motivo_fallo' => $datos['motivo_fallo'],
                'intentos' => $pendiente->intentos + 1,
            ]);

            return (int) $pendiente->id;
        }

        $pendiente = PendienteNc::create([
            'comercio_id' => $datos['comercio_id'],
            'venta_id' => $datos['venta_id'],
            'tipo_operacion' => $datos['tipo_operacion'],
            'motivo' => $datos['motivo'] ?? null,
            'items' => $datos['items'] ?? null,
            'monto_devuelto' => $datos['monto_devuelto'] ?? null,
            'motivo_fallo' => $datos['motivo_fallo'],
            'estado' => 'pendiente',
            'intentos' => 1,
        ]);

        return (int) $pendiente->id;
    }

    public function buscar(int $id): ?array
    {
        $pendiente = PendienteNc::find($id);

        return $pendiente !== null ? $pendiente->toArray() : null;
    }

    public function pendientesDe(int $comercioId): array
    {
        return PendienteNc::query()
            ->where('comercio_id', $comercioId)
            ->where('estado', 'pendiente')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (PendienteNc $pendiente) => $pendiente->toArray())
            ->all();
    }

    public function marcarResuelto(int $id): void
    {
        PendienteNc::where('id', $id)->update(['estado' => 'resuelto']);
    }
}
