<?php

namespace App\Http\Controllers;

use App\Enums\VentaStatus;
use App\Models\Venta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Historial propio de ventas canceladas.
 *
 * Es una consulta y una vista independientes del listado general de ventas, no
 * un filtro: el operador que necesita revisar anulaciones entra acá a ver qué
 * se anuló, por qué, quién lo hizo y cuándo.
 *
 * No modifica nada de la lógica de cancelación. Sólo lee `ventas` y, para el
 * "quién" y el "cuándo", el registro de auditoría que la propia cancelación ya
 * genera al pasar la venta a `Cancelada`.
 */
class VentasCanceladasController extends Controller
{
    /** Filas por página. La paginación es del backend. */
    private const POR_PAGINA = 7;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $sucursalId = session('sucursal_activa_id', $user->branch_id);

        if (! $sucursalId) {
            abort(403, 'No tienes una sucursal asignada.');
        }

        $canceladas = Venta::query()
            ->where('estado', VentaStatus::CANCELLED)
            ->whereHas('turno.caja', fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->with(['consumidor', 'turno.cajero', 'turno.caja.sucursal'])
            ->latest('id')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        $anulaciones = $this->anulacionesDe($canceladas->pluck('id')->all());

        $canceladas->getCollection()->transform(function (Venta $venta) use ($anulaciones) {
            $anulacion = $anulaciones->get($venta->id);
            $cliente = $venta->presentacionCliente();

            return [
                'id' => $venta->id,
                'fecha_venta' => $venta->created_at->format('d/m/Y H:i'),
                'cliente' => $cliente['nombre'],
                'es_consumidor_final' => $cliente['es_consumidor_final'],
                'es_cuenta_corriente' => $cliente['es_cuenta_corriente'],
                'documento' => $cliente['documento'],
                'metodo_pago' => $venta->metodo_pago_display,
                'total' => (float) $venta->total,
                'motivo' => $venta->motivo_anulacion,
                'cancelada_at' => $anulacion['fecha'] ?? null,
                'cancelada_por' => $anulacion['usuario'] ?? null,
            ];
        });

        return Inertia::render('Ventas/Canceladas', [
            'ventas' => $canceladas,
        ]);
    }

    /**
     * Para quién y cuándo se canceló cada venta.
     *
     * La venta no tiene columnas para eso: la fuente de verdad es el evento de
     * auditoría que escribe la propia anulación al cambiar `estado`. Se leen
     * en una sola consulta para los 7 registros de la página.
     *
     * @param  array<int, int>  $ventaIds
     * @return \Illuminate\Support\Collection<int, array{fecha: string, usuario: string}>
     */
    private function anulacionesDe(array $ventaIds): \Illuminate\Support\Collection
    {
        if ($ventaIds === []) {
            return collect();
        }

        return Activity::with('causer')
            ->where('subject_type', Venta::class)
            ->where('event', 'updated')
            ->whereIn('subject_id', $ventaIds)
            ->orderBy('id')
            ->get()
            ->filter(fn (Activity $a) => ($a->properties['attributes']['estado'] ?? null) === VentaStatus::CANCELLED->value)
            ->keyBy('subject_id')
            ->map(fn (Activity $a) => [
                'fecha' => $a->created_at->format('d/m/Y H:i'),
                'usuario' => $this->nombreCauser($a->causer),
            ]);
    }

    private function nombreCauser(?object $causer): string
    {
        if ($causer === null) {
            return 'Sistema';
        }

        if (property_exists($causer, 'nombre')) {
            $nombre = trim(($causer->nombre ?? '').' '.($causer->apellido ?? ''));

            return $nombre !== '' ? $nombre : 'Sistema';
        }

        return $causer->name ?? 'Sistema';
    }
}
