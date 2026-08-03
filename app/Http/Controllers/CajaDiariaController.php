<?php

namespace App\Http\Controllers;

use App\Enums\MetodoPago;
use App\Models\TurnoCaja;
use App\Models\MovimientoCaja;
use App\Models\Caja;
use App\Models\Configuracion;
use App\Models\Sucursal;
use App\Services\SucursalScopeService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class CajaDiariaController extends Controller
{
    private const METODOS_EFECTIVO = [MetodoPago::EFECTIVO];
    private const METODOS_TRANSFERENCIA = [MetodoPago::MERCADO_PAGO, MetodoPago::VIUMI, MetodoPago::TRANSFERENCIA];
    private const METODOS_TARJETA = [MetodoPago::DEBITO, MetodoPago::CREDITO];

    public function __construct(
        private readonly SucursalScopeService $scope
    ) {}

    private function clasificarMetodo(string $metodoPago): string
    {
        $enum = MetodoPago::from($metodoPago);
        if (in_array($enum, self::METODOS_EFECTIVO)) return 'efectivo';
        if (in_array($enum, self::METODOS_TARJETA)) return 'tarjetas';
        return 'transferencias';
    }

    /**
     * Valida que un turno pertenezca a una sucursal permitida por el usuario.
     */
    private function autorizarTurno(int $turnoId): void
    {
        $turno = TurnoCaja::with('caja.sucursal')->findOrFail($turnoId);

        if ($this->scope->puedeAccederSucursal((int) $turno->sucursal_id)) {
            return;
        }

        abort(403, 'No tenés acceso a esta sesión de caja.');
    }

    /**
     * Obtiene el historial de todas las sesiones de caja
     */
    public function index(Request $request)
    {
        $query = TurnoCaja::with(['caja', 'usuarioApertura', 'usuarioCierre']);
        $this->scope->aplicarFiltroSucursal($query);

        $sesiones = $query
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($turno) {
                return [
                    'id' => $turno->id,
                    'caja_nombre' => $turno->caja->nombre ?? 'Sin caja',
                    'fecha_apertura' => $turno->fecha_apertura,
                    'fecha_cierre' => $turno->fecha_cierre,
                    'usuario_apertura_nombre' => $turno->usuarioApertura?->name ?? 'Desconocido',
                    'usuario_cierre_nombre' => $turno->usuarioCierre?->name ?? null,
                    'esta_abierta' => $turno->estado === 'Abierto',
                    'saldo_final_efectivo_real' => $turno->saldo_final_efectivo_real ?? 0,
                    'saldo_final_mp_real' => $turno->saldo_final_mp_real ?? 0,
                    'saldo_final_transf_real' => $turno->saldo_final_transf_real ?? 0,
                    'saldo_final_tarjetas_real' => $turno->saldo_final_tarjetas_real ?? 0,
                    'observaciones' => $turno->observaciones_cierre ?? '',
                ];
            });

        return response()->json($sesiones);
    }

    /**
     * Obtiene la sesión actual (abierta) del usuario autenticado
     */
    public function getSesionActual(Request $request)
    {
        $user = $request->user();
        $turnoAbierto = TurnoCaja::with('caja')
            ->where('user_id', $user->id)
            ->where('estado', 'Abierto')
            ->first();

        if (!$turnoAbierto) {
            return response()->json(['message' => 'No hay sesión abierta', 'sesion_activa' => false], 200);
        }

        return response()->json([
            'id' => $turnoAbierto->id,
            'caja_nombre' => $turnoAbierto->caja->nombre,
            'usuario_apertura_nombre' => $user->name,
            'fecha_apertura' => $turnoAbierto->fecha_apertura,
            'saldo_inicial_efectivo' => $turnoAbierto->monto_apertura ?? $turnoAbierto->saldo_inicial,
            'saldo_inicial_mp' => 0
        ]);
    }

    /**
     * Abre una nueva sesión de caja
     */
    public function abrirCaja(Request $request)
    {
        try {
            $request->validate([
                'caja' => 'required|exists:cajas,id',
                'saldo_inicial_efectivo' => 'required|numeric|min:0',
                'saldo_inicial_mp' => 'nullable|numeric|min:0',
            ]);

            $cajaFisica = Caja::with('sucursal')->find($request->caja);

            if (!$cajaFisica) {
                return response()->json(['error' => 'La caja seleccionada no existe.'], 404);
            }

            if (!$this->scope->puedeAccederSucursal((int) $cajaFisica->sucursal_id)) {
                return response()->json(['error' => 'No tenés acceso a la sucursal de esta caja.'], 403);
            }

            if (!$cajaFisica->estado) {
                return response()->json(['error' => 'No puedes operar en una caja que se encuentra inactiva.'], 403);
            }

            $yaAbierta = TurnoCaja::where('caja_id', $cajaFisica->id)
                ->where('estado', 'Abierto')
                ->exists();

            if ($yaAbierta) {
                return response()->json(['error' => 'Esta caja ya tiene una sesión abierta. Cerrá la sesión actual antes de abrir una nueva.'], 409);
            }

            $efectivo = (float) $request->input('saldo_inicial_efectivo', 0);
            $mp = (float) $request->input('saldo_inicial_mp', 0);

            DB::beginTransaction();

            $turno = TurnoCaja::create([
                'caja_id'        => $cajaFisica->id,
                'user_id'        => auth()->id(),
                'sucursal_id'    => $cajaFisica->sucursal_id,
                'saldo_inicial'  => $efectivo,
                'monto_apertura' => $efectivo,
                'fecha_apertura' => now(),
                'estado'         => 'Abierto',
            ]);

            if ($efectivo > 0 || ($efectivo == 0 && $mp == 0)) {
                MovimientoCaja::create([
                    'turno_caja_id' => $turno->id,
                    'tipo'          => 'INGRESO',
                    'concepto'      => 'FONDO_INICIAL',
                    'metodo_pago'   => MetodoPago::EFECTIVO->value,
                    'monto'         => $efectivo,
                    'descripcion'   => 'Apertura de caja (Fondo Efectivo)'
                ]);
            }

            if ($mp > 0) {
                MovimientoCaja::create([
                    'turno_caja_id' => $turno->id,
                    'tipo'          => 'INGRESO',
                    'concepto'      => 'FONDO_INICIAL',
                    'metodo_pago'   => MetodoPago::MERCADO_PAGO->value,
                    'monto'         => $mp,
                    'descripcion'   => 'Apertura de caja (Fondo Mercado Pago)'
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Caja abierta correctamente']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'ERROR DE BD: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Registra un movimiento manual (ingreso/egreso) en la caja actual
     */
    public function crearMovimientoManual(Request $request)
    {
        $request->validate([
            'tipo' => 'required|in:INGRESO,EGRESO',
            'concepto' => 'required|string',
            'metodo_pago' => 'required|string',
            'monto' => 'required|numeric|min:0',
            'descripcion' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $turno = TurnoCaja::where('user_id', $user->id)->where('estado', 'Abierto')->first();

        if (!$turno) {
            return response()->json(['error' => 'No hay un turno abierto para tu usuario. Solo podés registrar movimientos en tu propia caja.'], 400);
        }

        $movimiento = MovimientoCaja::create([
            'turno_caja_id' => $turno->id,
            'tipo' => $request->tipo,
            'concepto' => $request->concepto,
            'metodo_pago' => $request->metodo_pago,
            'monto' => $request->monto,
            'descripcion' => $request->descripcion,
        ]);

        return response()->json(['message' => 'Movimiento registrado', 'data' => $movimiento], 201);
    }

    /**
     * Lista las cajas físicas disponibles (ACTIVAS) de la sucursal del usuario
     */
    public function getCajasDisponibles(Request $request)
    {
        $sucursalId = $this->scope->obtenerSucursalActiva();

        $cajas = Caja::where('estado', true)
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
            ->get();

        return response()->json($cajas);
    }

    /**
     * Cajas disponibles para ADMIN (todas las sucursales del comercio)
     */
    public function getCajasDisponiblesAdmin(Request $request)
    {
        if (!$this->scope->esJefe()) {
            abort(403);
        }

        $query = Caja::where('estado', true)->with('sucursal');
        $this->scope->aplicarFiltroSucursal($query, 'sucursal_id');

        $cajas = $query->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'sucursal_id' => $c->sucursal_id,
                'sucursal_nombre' => $c->sucursal?->nombre,
            ]);

        return response()->json($cajas);
    }

    /**
     * Todas las sesiones abiertas del comercio (solo admin)
     */
    public function getSesionesAbiertasGlobal(Request $request)
    {
        if (!$this->scope->esJefe()) {
            abort(403);
        }

        $query = TurnoCaja::with(['caja.sucursal', 'usuarioApertura'])
            ->where('estado', 'Abierto');
        $this->scope->aplicarFiltroSucursal($query);

        $sucursales = $this->scope->obtenerSucursalesDelComercio()
            ->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre])
            ->values();

        $sesiones = $query->get()
            ->map(function ($turno) {
                $movs = MovimientoCaja::where('turno_caja_id', $turno->id)->get();
                $efectivo = 0;
                $transferencias = 0;
                $tarjetas = 0;

                foreach ($movs as $mov) {
                    $monto = ($mov->tipo === 'INGRESO') ? $mov->monto : -$mov->monto;
                    $categoria = $this->clasificarMetodo($mov->metodo_pago);
                    match ($categoria) {
                        'efectivo' => $efectivo += $monto,
                        'tarjetas' => $tarjetas += $monto,
                        default => $transferencias += $monto,
                    };
                }

                return [
                    'id'                    => $turno->id,
                    'caja_nombre'           => $turno->caja->nombre ?? '—',
                    'sucursal_nombre'       => $turno->caja->sucursal?->nombre ?? '—',
                    'usuario_apertura_nombre' => $turno->usuarioApertura?->name ?? 'Desconocido',
                    'fecha_apertura'        => $turno->fecha_apertura,
                    'esperado_efectivo'     => $efectivo,
                    'esperado_transferencias' => $transferencias,
                    'esperado_tarjetas'     => $tarjetas,
                    'total'                 => $efectivo + $transferencias + $tarjetas,
                ];
            });

        return response()->json([
            'sucursales' => $sucursales,
            'sesiones'   => $sesiones,
        ]);
    }

    /**
     * Información de pendientes
     */
    public function getPendientes()
    {
        return response()->json(['cantidad' => 0, 'total_dinero' => 0]);
    }

    /**
     * Calcula el balance actual de una sesión
     */
    public function getBalance($id)
    {
        $this->autorizarTurno($id);

        $movimientos = MovimientoCaja::where('turno_caja_id', $id)->get();
        $efectivo = 0;
        $transferencias = 0;
        $tarjetas = 0;

        foreach ($movimientos as $mov) {
            $monto = ($mov->tipo === 'INGRESO') ? $mov->monto : -$mov->monto;
            $categoria = $this->clasificarMetodo($mov->metodo_pago);
            match ($categoria) {
                'efectivo' => $efectivo += $monto,
                'tarjetas' => $tarjetas += $monto,
                default => $transferencias += $monto,
            };
        }

        return response()->json([
            'esperado_efectivo' => $efectivo,
            'esperado_transferencias' => $transferencias,
            'esperado_tarjetas' => $tarjetas,
        ]);
    }

    /**
     * Obtiene los movimientos de una sesión
     */
    public function getMovimientos($id)
    {
        $this->autorizarTurno($id);

        $turno = TurnoCaja::findOrFail($id);
        $comercioId = $this->scope->obtenerComercioId();
        $labelMap = $comercioId ? \App\Models\PaymentMethodConfiguration::labelMap($comercioId) : [];

        $movimientos = MovimientoCaja::where('turno_caja_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($mov) use ($labelMap) {
                $mov->fecha = $mov->created_at;
                $mov->concepto_display = str_replace('_', ' ', $mov->concepto);
                $mov->metodo_pago_display = $labelMap[$mov->metodo_pago] ?? \App\Enums\MetodoPago::fromString($mov->metodo_pago)->label();
                return $mov;
            });

        return response()->json($movimientos);
    }

    /**
     * Cierra una sesión de caja, guarda los montos reales y observaciones
     */
    public function cerrarCaja(Request $request, $id)
    {
        $request->validate([
            'saldo_final_efectivo_real' => 'required|numeric|min:0',
            'saldo_final_transferencias_real' => 'required|numeric|min:0',
            'saldo_final_tarjetas_real' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string|max:500',
        ]);

        $turno = TurnoCaja::findOrFail($id);

        if (!$this->scope->puedeAccederSucursal((int) $turno->sucursal_id)) {
            return response()->json(['error' => 'No tenés acceso a esta sesión de caja.'], 403);
        }

        if ($turno->estado !== 'Abierto') {
            return response()->json(['error' => 'Esta caja ya está cerrada'], 400);
        }

        $turno->update([
            'estado' => 'Cerrado',
            'fecha_cierre' => Carbon::now(),
            'user_cierre_id' => auth()->id(),
            'saldo_final_efectivo_real' => $request->saldo_final_efectivo_real,
            'saldo_final_mp_real' => $request->saldo_final_transferencias_real,
            'saldo_final_transf_real' => 0,
            'saldo_final_tarjetas_real' => $request->saldo_final_tarjetas_real,
            'observaciones_cierre' => $request->observaciones,
            'monto_cierre' => $request->saldo_final_efectivo_real,
        ]);

        return response()->json(['message' => 'Caja cerrada exitosamente']);
    }

    /**
     * GENERAR PDF EN A4
     */
    public function descargarPdf(Request $request, $id)
    {
        $this->autorizarTurno($id);

        $turno = TurnoCaja::with(['caja', 'usuarioApertura', 'usuarioCierre'])
            ->findOrFail($id);

        $sucursal = Sucursal::find($turno->sucursal_id);

        $comercioId = $this->scope->obtenerComercioId();
        $labelMap = $comercioId ? \App\Models\PaymentMethodConfiguration::labelMap($comercioId) : [];

        $movimientos = MovimientoCaja::where('turno_caja_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        $config = Configuracion::paraComercio($comercioId);

        $efectivoEsperado = 0;
        $transferenciasEsperado = 0;
        $tarjetasEsperado = 0;

        foreach ($movimientos as $mov) {
            $monto = ($mov->tipo === 'INGRESO') ? $mov->monto : -$mov->monto;
            $categoria = $this->clasificarMetodo($mov->metodo_pago);
            match ($categoria) {
                'efectivo' => $efectivoEsperado += $monto,
                'tarjetas' => $tarjetasEsperado += $monto,
                default => $transferenciasEsperado += $monto,
            };
        }

        $tarjetasReal = (float) $turno->saldo_final_tarjetas_real;
        $transferenciasReal = (float) $turno->saldo_final_mp_real;

        $totales = [
            'efectivo_esperado' => $efectivoEsperado,
            'transferencias_esperado' => $transferenciasEsperado,
            'tarjetas_esperado' => $tarjetasEsperado,
            'total_esperado' => $efectivoEsperado + $transferenciasEsperado + $tarjetasEsperado,
            'efectivo_real' => (float) $turno->saldo_final_efectivo_real,
            'transferencias_real' => $transferenciasReal,
            'tarjetas_real' => $tarjetasReal,
            'total_real' => (float) $turno->saldo_final_efectivo_real + $transferenciasReal + $tarjetasReal,
        ];

        $logo = null;
        if (!empty($config['logo_empresa'])) {
            if (Storage::disk('public')->exists($config['logo_empresa'])) {
                $path = Storage::disk('public')->path($config['logo_empresa']);

                if (file_exists($path) && is_file($path)) {
                    $type = pathinfo($path, PATHINFO_EXTENSION);
                    $data = @file_get_contents($path);
                    if ($data !== false) {
                        $logo = 'data:image/' . $type . ';base64,' . base64_encode($data);
                    }
                }
            }
        }

        $pdf = Pdf::loadView('pdf.caja_a4', compact('turno', 'movimientos', 'config', 'totales', 'logo', 'sucursal', 'labelMap'));
        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream("reporte_caja_sesion_{$id}.pdf");
    }
}
