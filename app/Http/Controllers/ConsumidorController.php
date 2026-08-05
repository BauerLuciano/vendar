<?php

namespace App\Http\Controllers;

use App\Enums\MetodoPago;
use App\Facturacion\Domain\ValueObjects\Cuit;
use App\Models\Consumidor;
use App\Models\CuentaCorriente;
use App\Models\MovimientoCaja;
use App\Models\MovimientoCuentaCorriente;
use App\Models\PaymentMethodConfiguration;
use App\Models\TurnoCaja;
use App\Services\SucursalScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ConsumidorController extends Controller
{
    public function __construct(private SucursalScopeService $scope) {}

    public function index(Request $request)
    {
        $search = $request->input('search');
        $estado = $request->input('estado', 'all');
        $deuda = $request->input('deuda', 'all');

        $query = Consumidor::with('cuentaCorriente');

        $comercioId = $this->scope->obtenerComercioId();
        if ($comercioId) {
            $query->deComercio($comercioId);
        }

        $query->when($search, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('nombre', 'LIKE', "%{$search}%")
                    ->orWhere('apellido', 'LIKE', "%{$search}%")
                    ->orWhere('documento', 'LIKE', "%{$search}%");
                if (is_numeric($search)) {
                    $sub->orWhere('id', $search);
                }
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
            'filtros' => $request->only(['search', 'estado', 'deuda']),
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
        if ($request->input('cuit') === '') {
            $request->merge(['cuit' => null]);
        }
    }

    private function reglaCuit(): callable
    {
        return function (string $attribute, $value, $fail) {
            if (! empty($value) && ! Cuit::esValido($value)) {
                $fail('El CUIT no es válido.');
            }
        };
    }

    public function store(Request $request)
    {
        $this->normalizeInput($request);

        $comercioId = $this->scope->obtenerComercioId();

        $validated = $request->validate([
            'nombre' => 'required|string|max:50|regex:/^[^0-9]+$/',
            'apellido' => 'required|string|max:50|regex:/^[^0-9]+$/',
            'documento' => ['nullable', 'string', 'regex:/^\d{7,8}$/', $comercioId ? Rule::unique('consumidores', 'documento')->where(fn ($q) => $q->where('comercio_id', $comercioId)) : Rule::unique('consumidores', 'documento')],
            'email' => ['nullable', 'email', 'max:255', $comercioId ? Rule::unique('consumidores', 'email')->where(fn ($q) => $q->where('comercio_id', $comercioId)) : Rule::unique('consumidores', 'email')],
            'telefono' => 'nullable|string|max:15|regex:/^\d+$/',
            'direccion' => 'nullable|string|max:255',
            'cuit' => ['nullable', 'string', $this->reglaCuit()],
            'tipo_documento' => 'nullable|string|in:CUIT,DNI',
            'razon_social' => 'nullable|string|max:255',
            'domicilio_fiscal' => 'nullable|string|max:255',
            'limite_cuenta_corriente' => 'required|numeric|min:0',
            'estado' => 'boolean',
            'password' => 'nullable|string|min:6',
        ], [
            'nombre.regex' => 'El nombre no puede contener números.',
            'apellido.regex' => 'El apellido no puede contener números.',
            'documento.regex' => 'El documento debe tener entre 7 y 8 números.',
            'documento.unique' => 'El documento ya está registrado por otro cliente en tu comercio.',
            'telefono.regex' => 'El teléfono solo puede contener números.',
            'email.unique' => 'El email ya pertenece a otro cliente de tu comercio.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
        ]);

        $validated['comercio_id'] = $comercioId;

        $consumidor = new Consumidor;
        $consumidor->comercio_id = $validated['comercio_id'];
        $consumidor->nombre = $validated['nombre'];
        $consumidor->apellido = $validated['apellido'];
        $consumidor->documento = $validated['documento'] ?? null;
        $consumidor->email = $validated['email'] ?? null;
        $consumidor->telefono = $validated['telefono'] ?? null;
        $consumidor->direccion = $validated['direccion'] ?? null;
        $consumidor->cuit = $validated['cuit'] ?? null;
        $consumidor->tipo_documento = $validated['tipo_documento'] ?? null;
        $consumidor->razon_social = $validated['razon_social'] ?? null;
        $consumidor->domicilio_fiscal = $validated['domicilio_fiscal'] ?? null;
        $consumidor->limite_cuenta_corriente = $validated['limite_cuenta_corriente'];
        $consumidor->estado = $validated['estado'] ?? true;
        if ($request->filled('password')) {
            $consumidor->password = Hash::make($request->password);
        }
        $consumidor->save();

        return redirect()->back()->with('success', 'Cliente registrado exitosamente.');
    }

    public function update(Request $request, Consumidor $consumidor)
    {
        $comercioId = $this->scope->obtenerComercioId();
        if ($comercioId && $consumidor->comercio_id && $consumidor->comercio_id !== $comercioId) {
            abort(403, 'Este cliente no pertenece a tu comercio.');
        }

        $this->normalizeInput($request);
        $uniqueDocumento = $comercioId
            ? Rule::unique('consumidores', 'documento')->ignore($consumidor->id)->where(fn ($q) => $q->where('comercio_id', $comercioId))
            : Rule::unique('consumidores', 'documento')->ignore($consumidor->id);
        $uniqueEmail = $comercioId
            ? Rule::unique('consumidores', 'email')->ignore($consumidor->id)->where(fn ($q) => $q->where('comercio_id', $comercioId))
            : Rule::unique('consumidores', 'email')->ignore($consumidor->id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:50|regex:/^[^0-9]+$/',
            'apellido' => 'required|string|max:50|regex:/^[^0-9]+$/',
            'documento' => ['nullable', 'string', 'regex:/^\d{7,8}$/', $uniqueDocumento],
            'email' => ['nullable', 'email', 'max:255', $uniqueEmail],
            'telefono' => ['nullable', 'string', 'max:15', 'regex:/^\d+$/'],
            'direccion' => 'nullable|string|max:255',
            'cuit' => ['nullable', 'string', $this->reglaCuit()],
            'tipo_documento' => 'nullable|string|in:CUIT,DNI',
            'razon_social' => 'nullable|string|max:255',
            'domicilio_fiscal' => 'nullable|string|max:255',
            'limite_cuenta_corriente' => 'required|numeric|min:0',
            'estado' => 'boolean',
            'password' => 'nullable|string|min:6',
        ], [
            'nombre.regex' => 'El nombre no puede contener números.',
            'apellido.regex' => 'El apellido no puede contener números.',
            'documento.regex' => 'El documento debe tener entre 7 y 8 números.',
            'documento.unique' => 'El documento ya está registrado por otro cliente en tu comercio.',
            'telefono.regex' => 'El teléfono solo puede contener números.',
            'email.unique' => 'El email ya pertenece a otro cliente de tu comercio.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
        ]);

        $consumidor->nombre = $validated['nombre'];
        $consumidor->apellido = $validated['apellido'];
        $consumidor->documento = $validated['documento'] ?? null;
        $consumidor->email = $validated['email'] ?? null;
        $consumidor->telefono = $validated['telefono'] ?? null;
        $consumidor->direccion = $validated['direccion'] ?? null;
        $consumidor->cuit = $validated['cuit'] ?? null;
        $consumidor->tipo_documento = $validated['tipo_documento'] ?? null;
        $consumidor->razon_social = $validated['razon_social'] ?? null;
        $consumidor->domicilio_fiscal = $validated['domicilio_fiscal'] ?? null;
        $consumidor->limite_cuenta_corriente = $validated['limite_cuenta_corriente'];
        $consumidor->estado = $validated['estado'] ?? true;
        if ($request->filled('password')) {
            $consumidor->password = Hash::make($request->password);
        }
        $consumidor->save();

        return redirect()->back()->with('success', 'Datos del cliente actualizados.');
    }

    public function status(Consumidor $consumidor)
    {
        $comercioId = $this->scope->obtenerComercioId();
        if ($comercioId && $consumidor->comercio_id && $consumidor->comercio_id !== $comercioId) {
            abort(403);
        }

        $consumidor->estado = ! $consumidor->estado;
        $consumidor->save();

        return redirect()->back()->with('success', 'Estado del cliente modificado.');
    }

    public function estadoCuenta(Consumidor $consumidor)
    {
        $comercioId = $this->scope->obtenerComercioId();
        if ($comercioId && $consumidor->comercio_id && $consumidor->comercio_id !== $comercioId) {
            abort(403);
        }

        $cuenta = $consumidor->cuentaCorriente;
        if (! $cuenta) {
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
        $comercioId = $this->scope->obtenerComercioId();
        if ($comercioId && $consumidor->comercio_id && $consumidor->comercio_id !== $comercioId) {
            abort(403);
        }

        $request->validate([
            'pagos' => 'required|array|min:1',
            'pagos.*.monto' => 'required|numeric|min:0.01',
            'pagos.*.metodo_pago' => 'required|string|distinct',
        ], [
            'pagos.*.metodo_pago.distinct' => 'No puedes repetir el mismo método de pago.',
        ]);

        $cuenta = $consumidor->cuentaCorriente;
        $totalAbono = collect($request->pagos)->sum('monto');

        if (! $cuenta || $cuenta->saldo_deudor < $totalAbono) {
            return back()->withErrors(['monto' => 'El monto total a abonar supera la deuda actual del cliente.']);
        }

        DB::beginTransaction();

        try {
            $cuenta = CuentaCorriente::where('id', $cuenta->id)->lockForUpdate()->first();
            $cuenta->saldo_deudor -= $totalAbono;
            $cuenta->fecha_ultimo_movimiento = now();
            $cuenta->save();

            $user = auth()->user();
            $turno = TurnoCaja::where('user_id', $user->id)
                ->where('estado', 'Abierto')
                ->first();

            $detallesPago = [];
            $labelMap = $comercioId ? PaymentMethodConfiguration::labelMap($comercioId) : [];

            if ($turno) {
                foreach ($request->pagos as $pago) {
                    $metodoPagoNormalizado = MetodoPago::fromString($pago['metodo_pago'])->value;
                    $metodoPagoLabel = $labelMap[$pago['metodo_pago']] ?? MetodoPago::fromString($pago['metodo_pago'])->label();
                    MovimientoCaja::create([
                        'turno_caja_id' => $turno->id,
                        'tipo' => 'INGRESO',
                        'concepto' => 'COBRO_CUENTA_CORRIENTE',
                        'metodo_pago' => $metodoPagoNormalizado,
                        'monto' => $pago['monto'],
                        'descripcion' => 'Pago deuda: '.$consumidor->nombre.' '.$consumidor->apellido.' ('.$metodoPagoLabel.')',
                    ]);

                    $detallesPago[] = $metodoPagoLabel.': $'.number_format($pago['monto'], 2, ',', '.');
                }
            }

            MovimientoCuentaCorriente::create([
                'cuenta_corriente_id' => $cuenta->id,
                'monto' => $totalAbono,
                'tipo' => 'abono',
                'descripcion' => 'Abono a cuenta ('.implode(' | ', $detallesPago).')',
            ]);

            DB::commit();

            return back()->with('success', 'Cobro registrado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['monto' => 'Error de BD al procesar el pago: '.$e->getMessage()]);
        }
    }

    public function checkDocumento(Request $request)
    {
        $request->validate([
            'documento' => 'nullable|string|regex:/^\d{7,8}$/',
            'ignore_id' => 'nullable|integer|exists:consumidores,id',
        ]);

        if (empty($request->documento)) {
            return response()->json(['available' => true]);
        }

        $query = Consumidor::where('documento', $request->documento);

        $comercioId = $this->scope->obtenerComercioId();
        if ($comercioId) {
            $query->where('comercio_id', $comercioId);
        }

        if ($request->has('ignore_id')) {
            $query->where('id', '!=', $request->ignore_id);
        }

        $exists = $query->exists();

        return response()->json([
            'available' => ! $exists,
            'message' => $exists ? 'Este DNI ya está registrado' : 'DNI disponible',
        ]);
    }

    public function checkEmail(Request $request)
    {
        $request->validate([
            'email' => 'nullable|email|max:255',
            'ignore_id' => 'nullable|integer|exists:consumidores,id',
        ]);

        if (empty($request->email)) {
            return response()->json(['available' => true]);
        }

        $query = Consumidor::where('email', $request->email);

        $comercioId = $this->scope->obtenerComercioId();
        if ($comercioId) {
            $query->where('comercio_id', $comercioId);
        }

        if ($request->has('ignore_id')) {
            $query->where('id', '!=', $request->ignore_id);
        }

        $exists = $query->exists();

        return response()->json([
            'available' => ! $exists,
            'message' => $exists ? 'Este email ya está registrado' : 'Email disponible',
        ]);
    }

    public function checkDuplicados(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:50',
            'apellido' => 'required|string|max:50',
            'ignore_id' => 'nullable|integer|exists:consumidores,id',
        ]);

        $query = Consumidor::where('nombre', $request->nombre)
            ->where('apellido', $request->apellido)
            ->select('id', 'nombre', 'apellido', 'documento', 'email', 'telefono', 'estado');

        $comercioId = $this->scope->obtenerComercioId();
        if ($comercioId) {
            $query->where('comercio_id', $comercioId);
        }

        if ($request->has('ignore_id')) {
            $query->where('id', '!=', $request->ignore_id);
        }

        $duplicados = $query->get();

        return response()->json([
            'duplicados' => $duplicados,
            'total' => $duplicados->count(),
        ]);
    }

    public function apiIndex(): JsonResponse
    {
        $comercioId = $this->scope->obtenerComercioId();
        $query = Consumidor::query();

        if ($comercioId) {
            $query->where('comercio_id', $comercioId);
        }

        return response()->json($query->orderBy('nombre')->get());
    }
}
