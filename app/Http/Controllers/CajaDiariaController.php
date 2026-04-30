<?php

namespace App\Http\Controllers;

use App\Models\TurnoCaja;
use App\Models\MovimientoCaja;
use App\Models\Caja;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CajaDiariaController extends Controller
{
    /**
     * Obtiene el historial de todas las sesiones de caja de la sucursal del usuario
     * (incluye las cerradas y la actual si la hubiera)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $sucursalId = $user->branch_id ?? 1;

        $sesiones = TurnoCaja::with(['caja', 'usuarioApertura', 'usuarioCierre'])
            ->where('sucursal_id', $sucursalId)
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
            $cajaFisica = Caja::find($request->caja);

            if (!$cajaFisica) {
                return response()->json(['error' => 'La caja seleccionada no existe.'], 404);
            }

            // SEGURIDAD: Evitar abrir una caja inactiva
            if (!$cajaFisica->estado) {
                return response()->json(['error' => 'No puedes operar en una caja que se encuentra inactiva.'], 403);
            }

            // Capturamos asegurando que sean números decimales (float)
            $efectivo = (float) $request->input('saldo_inicial_efectivo', 0);
            $mp = (float) $request->input('saldo_inicial_mp', 0);

            DB::beginTransaction();
            
            // 1. Creamos el turno (cabecera)
            $turno = TurnoCaja::create([
                'caja_id'        => $cajaFisica->id,
                'user_id'        => $user->id,
                'sucursal_id'    => $cajaFisica->sucursal_id, 
                'saldo_inicial'  => $efectivo, // Guardamos efectivo como base para la DB
                'monto_apertura' => $efectivo, 
                'fecha_apertura' => now(),
                'estado'         => 'Abierto',
            ]);

            if ($efectivo > 0 || ($efectivo == 0 && $mp == 0)) {
                MovimientoCaja::create([
                    'turno_caja_id' => $turno->id,
                    'tipo'          => 'INGRESO',
                    'concepto'      => 'FONDO_INICIAL',
                    'metodo_pago'   => 'EFECTIVO',
                    'monto'         => $efectivo,
                    'descripcion'   => 'Apertura de caja (Fondo Efectivo)'
                ]);
            }

            if ($mp > 0) {
                MovimientoCaja::create([
                    'turno_caja_id' => $turno->id,
                    'tipo'          => 'INGRESO',
                    'concepto'      => 'FONDO_INICIAL',
                    'metodo_pago'   => 'MERCADO_PAGO',
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
            return response()->json(['error' => 'No hay turno abierto'], 400);
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
        $sucursalId = $user->branch_id ?? 1;
        
        // CORRECCIÓN: Traemos SOLO las cajas en estado true (Activas)
        $cajas = Caja::where('sucursal_id', $sucursalId)
                     ->where('estado', true)
                     ->get();
                     
        return response()->json($cajas);
    }

    /**
     * Información de pendientes (por implementar, se deja estructura)
     */
    public function getPendientes()
    {
        return response()->json(['cantidad' => 0, 'total_dinero' => 0]);
    }

    /**
     * Calcula el balance actual de una sesión (efectivo, MP, transferencia)
     */
    public function getBalance($id)
    {
        $movimientos = MovimientoCaja::where('turno_caja_id', $id)->get();
        $efectivo = 0;
        $mp = 0;
        $transf = 0;

        foreach ($movimientos as $mov) {
            $monto = ($mov->tipo === 'INGRESO') ? $mov->monto : -$mov->monto;
            if ($mov->metodo_pago === 'EFECTIVO') {
                $efectivo += $monto;
            } elseif (in_array($mov->metodo_pago, ['MERCADO_PAGO', 'MERCADOPAGO'])) {
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
     * Obtiene los movimientos de una sesión (formateados para el frontend)
     */
    public function getMovimientos($id)
    {
        $movimientos = MovimientoCaja::where('turno_caja_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($mov) {
                $mov->fecha = $mov->created_at;
                $mov->concepto_display = str_replace('_', ' ', $mov->concepto);
                $mov->metodo_pago_display = str_replace('_', ' ', $mov->metodo_pago);
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

        $turno = TurnoCaja::findOrFail($id);

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
}