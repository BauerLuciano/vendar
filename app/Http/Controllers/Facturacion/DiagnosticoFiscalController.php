<?php

namespace App\Http\Controllers\Facturacion;

use App\Facturacion\Application\DiagnosticoFiscalService;
use App\Http\Controllers\Controller;
use App\Models\PendienteNc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Throwable;

/**
 * Panel de Diagnóstico Fiscal (F8, arquitectura §15): checklist por ítem con
 * indicador global, suite de conectividad con ARCA y gestión de pendientes de
 * Notas de Crédito fallidas (§8) con reintento de la operación completa.
 * Controller delgado: delega en DiagnosticoFiscalService.
 */
class DiagnosticoFiscalController extends Controller
{
    public function __construct(private DiagnosticoFiscalService $servicio) {}

    public function index(Request $request)
    {
        $comercioId = $this->comercioId($request);
        $resultadoConexion = session('facturacion.resultado_conexion');

        return Inertia::render('Facturacion/Diagnostico', [
            'diagnostico' => $this->servicio->diagnostico($comercioId, $resultadoConexion),
            'pendientes' => $this->servicio->pendientes($comercioId),
            'resultadoConexion' => $resultadoConexion,
        ]);
    }

    public function probarConexion(Request $request)
    {
        try {
            $resultado = $this->servicio->probarConexion($this->comercioId($request));

            session()->flash('facturacion.resultado_conexion', $resultado);

            $ok = collect($resultado)->every(fn ($r) => $r['ok']);

            return back()->with($ok ? 'success' : 'error', $ok
                ? 'Conexión con ARCA verificada correctamente.'
                : 'Se detectaron problemas de conexión con ARCA.');
        } catch (Throwable $e) {
            Log::warning('Diagnóstico: error probando conexión', ['error' => $e->getMessage()]);

            return back()->withErrors(['conexion' => $e->getMessage()]);
        }
    }

    public function reintentarNc(Request $request, PendienteNc $pendiente)
    {
        try {
            $resultado = $this->servicio->reintentarNc(
                $this->comercioId($request),
                $pendiente->id,
                (int) $request->user()->id,
            );

            return back()->with('success', $resultado['mensaje']);
        } catch (Throwable $e) {
            Log::warning('Diagnóstico: reintento de NC fallido', [
                'pendiente_id' => $pendiente->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['reintento' => $e->getMessage()]);
        }
    }

    private function comercioId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->comercio_id ?? $user->branch?->comercio_id);
    }
}
