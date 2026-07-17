<?php

namespace App\Http\Controllers;

use App\Models\RecargoTarjeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RecargoTarjetaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $comercioId = $user->comercio_id ?? $user->branch?->comercio_id;

        $recargos = RecargoTarjeta::where('comercio_id', $comercioId)
            ->orderBy('banco')
            ->orderBy('tipo_tarjeta')
            ->orderBy('cuotas')
            ->get();

        $grouped = $recargos->groupBy(fn ($r) => $r->banco . '|' . $r->tipo_tarjeta)
            ->map(function ($records, $key) {
                [$banco, $tipo] = explode('|', $key);
                return [
                    'banco' => $banco,
                    'tipo_tarjeta' => $tipo,
                    'cuotas' => $records->mapWithKeys(fn ($r) => [$r->cuotas => [
                        'id' => $r->id,
                        'porcentaje' => (float) $r->porcentaje,
                        'enabled' => $r->enabled,
                    ]])->toArray(),
                ];
            })
            ->values();

        return Inertia::render('Configuracion/Recargos/Index', [
            'recargos' => $grouped,
        ]);
    }

    public function saveGrouped(Request $request)
    {
        $user = $request->user();
        $comercioId = $user->comercio_id ?? $user->branch?->comercio_id;

        $validated = $request->validate([
            'banco' => 'required|string|max:255',
            'tipo_tarjeta' => 'required|string|in:DEBITO,CREDITO',
            'cuotas' => 'required|array',
            'cuotas.*.cuotas' => 'required|integer|min:1|max:60',
            'cuotas.*.porcentaje' => 'required|numeric|min:0|max:100',
        ]);

        DB::transaction(function () use ($comercioId, $validated) {
            // Delete all existing for this banco+tipo
            RecargoTarjeta::where('comercio_id', $comercioId)
                ->where('banco', $validated['banco'])
                ->where('tipo_tarjeta', $validated['tipo_tarjeta'])
                ->delete();

            // Insert only cuotas with porcentaje > 0
            foreach ($validated['cuotas'] as $cuota) {
                if ((float) $cuota['porcentaje'] > 0) {
                    RecargoTarjeta::create([
                        'comercio_id' => $comercioId,
                        'banco' => $validated['banco'],
                        'tipo_tarjeta' => $validated['tipo_tarjeta'],
                        'cuotas' => $cuota['cuotas'],
                        'porcentaje' => $cuota['porcentaje'],
                        'enabled' => true,
                    ]);
                }
            }
        });

        return redirect()->back()->with('success', 'Configuración guardada correctamente.');
    }

    public function destroyGrouped(Request $request)
    {
        $user = $request->user();
        $comercioId = $user->comercio_id ?? $user->branch?->comercio_id;

        $validated = $request->validate([
            'banco' => 'required|string|max:255',
            'tipo_tarjeta' => 'required|string|in:DEBITO,CREDITO',
        ]);

        RecargoTarjeta::where('comercio_id', $comercioId)
            ->where('banco', $validated['banco'])
            ->where('tipo_tarjeta', $validated['tipo_tarjeta'])
            ->delete();

        return redirect()->back()->with('success', 'Configuración eliminada.');
    }
}
