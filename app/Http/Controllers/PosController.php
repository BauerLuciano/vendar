<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\TurnoCaja;
use App\Models\Producto;
use App\Models\Consumidor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class PosController extends Controller
{
    // Esta es la puerta de entrada al POS
   public function index(Request $request)
    {
        $user = auth()->user();

        $turnoAbierto = TurnoCaja::where('user_id', $user->id)
            ->where('estado', 'Abierto')
            ->first();

        if ($turnoAbierto) {
            $sucursalId = $user->branch_id ?? 1;

            $productos = Producto::where('estado', true)
                ->select('id', 'nombre', 'codigo_barras', 'precio_venta', 'imagen', 'unidad_medida')
                ->with(['sucursales' => function($q) use ($sucursalId) {
                    $q->where('sucursal_id', $sucursalId);
                }, 'reglaLiquidacion']) // 🔥 Cargamos la regla de liquidación
                ->get()
                ->map(function($p) use ($sucursalId) {
                    $pivot = $p->sucursales->first();
                    $p->stock_actual = $pivot ? (float)$pivot->pivot->cantidad_fisica : 0;

                    // 🔥 LÓGICA DE LIQUIDACIÓN PREVENTIVA
                    // Verificamos si este producto tiene AL MENOS UN lote en liquidación en esta sucursal con stock
                    $loteEnLiquidacion = \App\Models\Lote::where('producto_id', $p->id)
                        ->where('sucursal_id', $sucursalId)
                        ->where('estado_liquidacion', true)
                        ->where('stock_actual', '>', 0)
                        ->exists();

                    $p->en_liquidacion = false;
                    $p->porcentaje_descuento = 0;
                    $p->precio_rebajado = $p->precio_venta;

                    // Si hay lote en liquidación y el producto tiene una regla activa...
                    if ($loteEnLiquidacion && $p->reglaLiquidacion && $p->reglaLiquidacion->estado) {
                        $p->en_liquidacion = true;
                        $p->porcentaje_descuento = (float) $p->reglaLiquidacion->porcentaje_descuento;
                        
                        // Calculamos el nuevo precio: Precio - (Precio * % / 100)
                        $descuento = $p->precio_venta * ($p->porcentaje_descuento / 100);
                        $p->precio_rebajado = round($p->precio_venta - $descuento, 2);
                    }

                    return $p;
                });

            $clientesActivos = Consumidor::with('cuentaCorriente')
                ->where('estado', true)
                ->get();

            return Inertia::render('Pos/Terminal', [
                'turno' => $turnoAbierto->load('caja.sucursal'),
                'productos' => $productos,
                'clientes' => $clientesActivos
            ]);
        }

        $cajasDisponibles = Caja::where('sucursal_id', $user->branch_id ?? 1)
            ->where('estado', true)
            ->get();

        return Inertia::render('Pos/AperturaTurno', [
            'cajas' => $cajasDisponibles
        ]);
    }

    // Este método procesa el formulario de "Abrir Caja"
    public function abrirTurno(Request $request)
    {
        $request->validate([
            'caja_id' => 'required|exists:cajas,id',
            'saldo_inicial' => 'required|numeric|min:0',
        ]);

        // Verificamos por seguridad que nadie más esté usando esa caja
        $cajaEnUso = TurnoCaja::where('caja_id', $request->caja_id)
            ->where('estado', 'Abierto')
            ->exists();

        if ($cajaEnUso) {
            return redirect()->back()->withErrors([
                'caja_id' => 'Esta caja ya está siendo utilizada por otro cajero.'
            ]);
        }

        // ¡Abrimos el turno!
        TurnoCaja::create([
            'caja_id' => $request->caja_id,
            'user_id' => auth()->id(),
            'saldo_inicial' => $request->saldo_inicial,
            'fecha_apertura' => Carbon::now(),
            'estado' => 'Abierto',
        ]);

        return redirect()->route('pos.index')->with('success', 'Turno abierto correctamente. ¡Buenas ventas!');
    }
}