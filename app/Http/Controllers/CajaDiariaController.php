<?php

namespace App\Http\Controllers;

use App\Enums\MetodoPago;
use App\Models\TurnoCaja;
use App\Models\MovimientoCaja;
use App\Models\Caja;
use App\Models\User;
use App\Models\Configuracion;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class CajaDiariaController extends Controller
{
    private function autorizarTurno(int $turnoId): void
    {
        $user = auth()->user();
        $comercioId = $user->branch?->comercio_id;
        if (!$comercioId) return;

        $turno = TurnoCaja::with('caja.sucursal')->findOrFail($turnoId);
        if ($turno->caja->sucursal->comercio_id !== $comercioId) {
            abort(403);
        }
    }

    /**
     * Obtiene el historial de todas las sesiones de caja de la sucursal del usuario
     * (incluye las cerradas y la actual si la hubiera)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $sucursalId = $user->branch_id;

        $sesiones = TurnoCaja::with(['caja', 'usuarioApertura', 'usuarioCierre'])
            ->when($sucursalId, fn ($q) => $q->where('sucursal_id', $sucursalId))
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
            return response()->json(['message' => 'No hay sesión abierta'], 404);
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

            $user = auth()->user();
            $comercioId = $user->branch?->comercio_id;

            $cajaFisica = Caja::whereHas('sucursal', fn ($q) => $q->when($comercioId, fn ($sq) => $sq->where('comercio_id', $comercioId)))
                ->find($request->caja);

            if (!$cajaFisica) {
                return response()->json(['error' => 'La caja seleccionada no existe.'], 404);
            }

            if (!$cajaFisica->estado) {
                return response()->json(['error' => 'No puedes operar en una caja que se encuentra inactiva.'], 403);
            }

            $efectivo = (float) $request->input('saldo_inicial_efectivo', 0);
            $mp = (float) $request->input('saldo_inicial_mp', 0);

            DB::beginTransaction();
            
            $turno = TurnoCaja::create([
                'caja_id'        => $cajaFisica->id,
                'user_id'        => $user->id,
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
            'monto' => 'required|numeric|min:1',
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
        $user = $request->user();
        $sucursalId = $user->branch_id;
        
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
        $user = $request->user();
        if (!$user->hasRole(['SuperAdmin', 'Administrador Global'])) {
            abort(403);
        }

        $comercioId = $user->branch?->comercio_id;
        $cajas = Caja::where('estado', true)
            ->whereHas('sucursal', fn ($q) => $q->when($comercioId, fn ($sq) => $sq->where('comercio_id', $comercioId)))
            ->with('sucursal')
            ->get()
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
        $user = $request->user();
        if (!$user->hasRole(['SuperAdmin', 'Administrador Global'])) {
            abort(403);
        }

        $comercioId = $user->branch?->comercio_id;
        if (!$comercioId) {
            return response()->json([]);
        }

        $sucursales = Sucursal::where('comercio_id', $comercioId)->select('id', 'nombre')->get();

        $sucursalIds = $sucursales->pluck('id');

        $sesiones = TurnoCaja::with(['caja.sucursal', 'usuarioApertura'])
            ->where('estado', 'Abierto')
            ->whereIn('sucursal_id', $sucursalIds)
            ->get()
            ->map(function ($turno) {
                $movs = MovimientoCaja::where('turno_caja_id', $turno->id)->get();
                $efectivo = 0; $mp = 0; $transf = 0;
                foreach ($movs as $mov) {
                    $monto = ($mov->tipo === 'INGRESO') ? $mov->monto : -$mov->monto;
                    if ($mov->metodo_pago === MetodoPago::EFECTIVO->value) $efectivo += $monto;
                    elseif ($mov->metodo_pago === MetodoPago::MERCADO_PAGO->value) $mp += $monto;
                    else $transf += $monto;
                }
                return [
                    'id'                    => $turno->id,
                    'caja_nombre'           => $turno->caja->nombre ?? '—',
                    'sucursal_nombre'       => $turno->caja->sucursal?->nombre ?? '—',
                    'usuario_apertura_nombre' => $turno->usuarioApertura?->name ?? 'Desconocido',
                    'fecha_apertura'        => $turno->fecha_apertura,
                    'esperado_efectivo'     => $efectivo,
                    'esperado_mp'           => $mp,
                    'esperado_transf'       => $transf,
                    'total'                 => $efectivo + $mp + $transf,
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
        $mp = 0;
        $transf = 0;

        foreach ($movimientos as $mov) {
            $monto = ($mov->tipo === 'INGRESO') ? $mov->monto : -$mov->monto;
            if ($mov->metodo_pago === MetodoPago::EFECTIVO->value) {
                $efectivo += $monto;
            } elseif ($mov->metodo_pago === MetodoPago::MERCADO_PAGO->value) {
                $mp += $monto;
            } else {
                $transf += $monto;
            }
        }

        return response()->json([
            'esperado_efectivo' => $efectivo,
            'esperado_mp' => $mp,
            'esperado_transf' => $transf
        ]);
    }

    /**
     * Obtiene los movimientos de una sesión
     */
    public function getMovimientos($id)
    {
        $this->autorizarTurno($id);

        $movimientos = MovimientoCaja::where('turno_caja_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($mov) {
                $mov->fecha = $mov->created_at;
                $mov->concepto_display = str_replace('_', ' ', $mov->concepto);
                $mov->metodo_pago_display = MetodoPago::fromString($mov->metodo_pago)->label();
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
            'saldo_final_mp_real' => 'required|numeric|min:0',
            'saldo_final_transf_real' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $turno = TurnoCaja::where('id', $id)
            ->when($user->branch?->comercio_id, function ($q) use ($user) {
                $sucursalIds = \App\Models\Sucursal::where('comercio_id', $user->branch->comercio_id)->pluck('id');
                $q->whereIn('sucursal_id', $sucursalIds);
            })
            ->firstOrFail();

        if ($turno->estado !== 'Abierto') {
            return response()->json(['error' => 'Esta caja ya está cerrada'], 400);
        }

        $dataUpdate = [
            'estado' => 'Cerrado',
            'fecha_cierre' => Carbon::now(),
            'user_cierre_id' => $request->user()->id,
            'saldo_final_efectivo_real' => $request->saldo_final_efectivo_real,
            'saldo_final_mp_real' => $request->saldo_final_mp_real,
            'saldo_final_transf_real' => $request->saldo_final_transf_real,
            'observaciones_cierre' => $request->observaciones,
        ];

        $dataUpdate['monto_cierre'] = $request->saldo_final_efectivo_real;

        $turno->update($dataUpdate);

        return response()->json(['message' => 'Caja cerrada exitosamente']);
    }

    /**
     * GENERAR PDF EN A4 - PARAMETRIZACIÓN COMPLETA Y CONVERSOR DE COMPATIBILIDAD
     */
    public function descargarPdf(Request $request, $id)
    {
        $user = $request->user();
        $turno = TurnoCaja::with(['caja', 'usuarioApertura', 'usuarioCierre'])
            ->where('id', $id)
            ->when($user->branch?->comercio_id, function ($q) use ($user) {
                $sucursalIds = \App\Models\Sucursal::where('comercio_id', $user->branch->comercio_id)->pluck('id');
                $q->whereIn('sucursal_id', $sucursalIds);
            })
            ->firstOrFail();
        $sucursal = Sucursal::find($turno->sucursal_id);
        
        $movimientos = MovimientoCaja::where('turno_caja_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        $config = \App\Models\Configuracion::pluck('valor', 'clave')->toArray();
        
        $efectivoEsperado = 0;
        $mpEsperado = 0;
        $transfEsperado = 0;

        foreach ($movimientos as $mov) {
            $monto = ($mov->tipo === 'INGRESO') ? $mov->monto : -$mov->monto;
            if ($mov->metodo_pago === MetodoPago::EFECTIVO->value) {
                $efectivoEsperado += $monto;
            } elseif ($mov->metodo_pago === MetodoPago::MERCADO_PAGO->value) {
                $mpEsperado += $monto;
            } else {
                $transfEsperado += $monto;
            }
        }

        $totales = [
            'efectivo_esperado' => $efectivoEsperado,
            'mp_esperado' => $mpEsperado,
            'transf_esperado' => $transfEsperado,
            'total_esperado' => $efectivoEsperado + $mpEsperado + $transfEsperado,
            'efectivo_real' => (float) $turno->saldo_final_efectivo_real,
            'mp_real' => (float) $turno->saldo_final_mp_real,
            'transf_real' => (float) $turno->saldo_final_transf_real,
            'total_real' => (float)$turno->saldo_final_efectivo_real + (float)$turno->saldo_final_mp_real + (float)$turno->saldo_final_transf_real
        ];

        // 🔥 EXTRAE LA RUTA REAL SIN MAPEAR CADENAS FIJAS
        $logo = null;
        if (!empty($config['logo_empresa'])) {
            // Buscamos la ruta física usando la API de discos de Laravel de forma 100% dinámica
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

        $pdf = Pdf::loadView('pdf.caja_a4', compact('turno', 'movimientos', 'config', 'totales', 'logo', 'sucursal'));
        $pdf->setPaper('a4', 'portrait');
        
        return $pdf->stream("reporte_caja_sesion_{$id}.pdf");
    }
}