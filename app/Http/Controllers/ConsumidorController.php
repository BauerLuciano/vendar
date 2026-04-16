<?php

namespace App\Http\Controllers;

use App\Models\Consumidor;
use App\Models\TurnoCaja;
use App\Models\MovimientoCaja;
use App\Models\MovimientoCuentaCorriente;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConsumidorController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $estado = $request->input('estado', 'all');
        $deuda = $request->input('deuda', 'all');

        $query = Consumidor::with('cuentaCorriente');

        $query->when($search, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('nombre', 'LIKE', "%{$search}%")
                    ->orWhere('apellido', 'LIKE', "%{$search}%")
                    ->orWhere('documento', 'LIKE', "%{$search}%")
                    ->orWhere('id', 'LIKE', "%{$search}%");
            });
        });

        $query->when($estado !== 'all', function ($q) use ($estado) {
            $q->where('estado', $estado === 'activos' ? true : false);
        });

        $query->when($deuda === 'con_deuda', function ($q) {
            $q->whereHas('cuentaCorriente', function ($sub) {
                $sub->where('saldo_deudor', '>', 0);
            });
        });

        $consumidores = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();

        return Inertia::render('Consumidores/Index', [
            'consumidores' => $consumidores,
            'filtros' => $request->only(['search', 'estado', 'deuda'])
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:50|regex:/^[^0-9]+$/',
            'apellido' => 'required|string|max:50|regex:/^[^0-9]+$/',
            'documento' => ['nullable', 'string', 'regex:/^\d{7,8}$/', 'unique:consumidores,documento'],
            'email' => 'nullable|email|max:255|unique:consumidores,email',
            'telefono' => 'nullable|string|max:15|regex:/^\d+$/',
            'direccion' => 'nullable|string|max:255',
            'limite_cuenta_corriente' => 'required|numeric|min:0',
            'estado' => 'boolean',
        ], [
            'nombre.regex' => 'El nombre no puede contener números.',
            'apellido.regex' => 'El apellido no puede contener números.',
            'documento.regex' => 'El documento debe tener entre 7 y 8 números.',
            'telefono.regex' => 'El teléfono solo puede contener números.',
        ]);

        Consumidor::create($validated);
        
        return redirect()->back()->with('success', 'Cliente registrado exitosamente.');
    }

    public function update(Request $request, Consumidor $consumidor)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:50|regex:/^[^0-9]+$/',
            'apellido' => 'required|string|max:50|regex:/^[^0-9]+$/',
            'documento' => ['nullable', 'string', 'regex:/^\d{7,8}$/', Rule::unique('consumidores')->ignore($consumidor->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('consumidores')->ignore($consumidor->id)],
            'telefono' => ['nullable', 'string', 'max:15', 'regex:/^\d+$/'],
            'direccion' => 'nullable|string|max:255',
            'limite_cuenta_corriente' => 'required|numeric|min:0',
            'estado' => 'boolean',
        ], [
            'nombre.regex' => 'El nombre no puede contener números.',
            'apellido.regex' => 'El apellido no puede contener números.',
            'documento.regex' => 'El documento debe tener entre 7 y 8 números.',
            'telefono.regex' => 'El teléfono solo puede contener números.',
        ]);

        $consumidor->update($validated);
        
        return redirect()->back()->with('success', 'Datos del cliente actualizados.');
    }

    public function status(Consumidor $consumidor)
    {
        $consumidor->estado = !$consumidor->estado;
        $consumidor->save();
        
        return redirect()->back()->with('success', 'Estado del cliente modificado.');
    }

    // 🔥 NUEVO: DEVUELVE EL HISTORIAL DE LA CUENTA
    public function estadoCuenta(Consumidor $consumidor)
    {
        $cuenta = $consumidor->cuentaCorriente;
        if (!$cuenta) {
            return response()->json([]);
        }

        // Buscamos los movimientos ordenados de más nuevo a más viejo
        $movimientos = MovimientoCuentaCorriente::where('cuenta_corriente_id', $cuenta->id)
            ->with('venta') // Por si fue un cargo de una venta
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($movimientos);
    }

    // 🔥 ACTUALIZADO: ACEPTA PAGOS MIXTOS Y REGISTRA EN EL HISTORIAL
    public function cobrarDeuda(Request $request, Consumidor $consumidor)
    {
        $request->validate([
            'pagos' => 'required|array|min:1',
            'pagos.*.monto' => 'required|numeric|min:0.01',
            'pagos.*.metodo_pago' => 'required|string'
        ]);

        $cuenta = $consumidor->cuentaCorriente;
        
        // Sumamos todos los pagos que mandó el cajero
        $totalAbono = collect($request->pagos)->sum('monto');

        if (!$cuenta || $cuenta->saldo_deudor < $totalAbono) {
            return back()->withErrors(['monto' => 'El monto total a abonar supera la deuda actual del cliente.']);
        }

        DB::beginTransaction();

        try {
            // 1. Restar la deuda de la cuenta
            $cuenta->saldo_deudor -= $totalAbono;
            $cuenta->fecha_ultimo_movimiento = now();
            $cuenta->save();

            // 2. Registrar en la caja (Un movimiento por cada método de pago)
            $user = auth()->user();
            $turno = TurnoCaja::where('user_id', $user->id)
                        ->where('estado', 'Abierto')
                        ->first();

            $detallesPago = [];

            if ($turno) {
                foreach ($request->pagos as $pago) {
                    MovimientoCaja::create([
                        'turno_caja_id' => $turno->id,
                        'tipo'          => 'INGRESO',
                        'concepto'      => 'COBRO_CUENTA_CORRIENTE',
                        'metodo_pago'   => $pago['metodo_pago'],
                        'monto'         => $pago['monto'],
                        'descripcion'   => 'Pago deuda: ' . $consumidor->nombre . ' ' . $consumidor->apellido
                    ]);

                    $detallesPago[] = $pago['metodo_pago'] . ': $' . number_format($pago['monto'], 2, ',', '.');
                }
            }

            // 3. Dejar registro en el historial de la cuenta corriente del cliente
            MovimientoCuentaCorriente::create([
                'cuenta_corriente_id' => $cuenta->id,
                'monto'               => $totalAbono,
                'tipo'                => 'abono',
                'descripcion'         => 'Abono a cuenta (' . implode(' | ', $detallesPago) . ')',
            ]);

            DB::commit();
            return back()->with('success', 'Cobro registrado exitosamente.');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['monto' => 'Error de BD al procesar el pago: ' . $e->getMessage()]);
        }
    }
}