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

    /**
     * Convierte cadenas vacías a null para documento y email
     */
    private function normalizeInput(Request $request): void
    {
        if ($request->input('documento') === '') {
            $request->merge(['documento' => null]);
        }
        if ($request->input('email') === '') {
            $request->merge(['email' => null]);
        }
    }

    public function store(Request $request)
    {
        $this->normalizeInput($request);

        $validated = $request->validate([
            'nombre' => 'required|string|max:50|regex:/^[^0-9]+$/',
            'apellido' => 'required|string|max:50|regex:/^[^0-9]+$/',
            'documento' => ['nullable', 'string', 'regex:/^\d{7,8}$/', 'unique:consumidores,documento'],
            'email' => ['nullable', 'email', 'max:255', 'unique:consumidores,email'],
            'telefono' => 'nullable|string|max:15|regex:/^\d+$/',
            'direccion' => 'nullable|string|max:255',
            'limite_cuenta_corriente' => 'required|numeric|min:0',
            'estado' => 'boolean',
        ], [
            'nombre.regex' => 'El nombre no puede contener números.',
            'apellido.regex' => 'El apellido no puede contener números.',
            'documento.regex' => 'El documento debe tener entre 7 y 8 números.',
            'documento.unique' => 'El documento ya está registrado por otro cliente.',
            'telefono.regex' => 'El teléfono solo puede contener números.',
            'email.unique' => 'El email ya pertenece a otro cliente.',
        ]);

        Consumidor::create($validated);
        
        return redirect()->back()->with('success', 'Cliente registrado exitosamente.');
    }

    public function update(Request $request, Consumidor $consumidor)
    {
        $this->normalizeInput($request);

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
            'documento.unique' => 'El documento ya está registrado por otro cliente.',
            'telefono.regex' => 'El teléfono solo puede contener números.',
            'email.unique' => 'El email ya pertenece a otro cliente.',
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

    public function estadoCuenta(Consumidor $consumidor)
    {
        $cuenta = $consumidor->cuentaCorriente;
        if (!$cuenta) {
            return response()->json([]);
        }

        $movimientos = MovimientoCuentaCorriente::where('cuenta_corriente_id', $cuenta->id)
            ->with('venta')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($movimientos);
    }

    public function cobrarDeuda(Request $request, Consumidor $consumidor)
    {
        $request->validate([
            'pagos' => 'required|array|min:1',
            'pagos.*.monto' => 'required|numeric|min:0.01',
            'pagos.*.metodo_pago' => 'required|string|distinct'
        ], [
            'pagos.*.metodo_pago.distinct' => 'No puedes repetir el mismo método de pago.'
        ]);

        $cuenta = $consumidor->cuentaCorriente;
        $totalAbono = collect($request->pagos)->sum('monto');

        if (!$cuenta || $cuenta->saldo_deudor < $totalAbono) {
            return back()->withErrors(['monto' => 'El monto total a abonar supera la deuda actual del cliente.']);
        }

        DB::beginTransaction();

        try {
            $cuenta->saldo_deudor -= $totalAbono;
            $cuenta->fecha_ultimo_movimiento = now();
            $cuenta->save();

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
                        // 🔥 MAGIA ACÁ: Ahora se guarda "Pago deuda: Juan Perez (Efectivo)"
                        'descripcion'   => 'Pago deuda: ' . $consumidor->nombre . ' ' . $consumidor->apellido . ' (' . $pago['metodo_pago'] . ')'
                    ]);

                    $detallesPago[] = $pago['metodo_pago'] . ': $' . number_format($pago['monto'], 2, ',', '.');
                }
            }

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

    public function checkDocumento(Request $request)
    {
        $request->validate([
            'documento' => 'nullable|string|regex:/^\d{7,8}$/',
            'ignore_id' => 'nullable|integer|exists:consumidores,id'
        ]);

        if (empty($request->documento)) {
            return response()->json(['available' => true]);
        }

        $query = Consumidor::where('documento', $request->documento);
        
        if ($request->has('ignore_id')) {
            $query->where('id', '!=', $request->ignore_id);
        }
        
        $exists = $query->exists();
        
        return response()->json([
            'available' => !$exists,
            'message' => $exists ? 'Este DNI ya está registrado' : 'DNI disponible'
        ]);
    }
}