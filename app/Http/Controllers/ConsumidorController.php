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
     * Normaliza entradas antes de validar:
     * - textos sin espacios al inicio/fin (no altera tildes ni ñ)
     * - convierte cadenas vacías a null
     */
    private function normalizeInput(Request $request): void
    {
        foreach (['nombre', 'apellido', 'direccion', 'razon_social', 'domicilio_fiscal', 'email'] as $campo) {
            if (is_string($request->input($campo))) {
                $request->merge([$campo => trim($request->input($campo))]);
            }
        }
        foreach (['documento', 'email', 'cuit'] as $campo) {
            if ($request->input($campo) === '') {
                $request->merge([$campo => null]);
            }
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

    /**
     * Rechaza caracteres de control y textos vacíos o de solo espacios.
     */
    private function reglaSinControl(string $mensaje): callable
    {
        return function (string $attribute, $value, $fail) use ($mensaje) {
            if (is_string($value) && (preg_match('/[\x00-\x1F\x7F]/', $value) || trim($value) === '')) {
                $fail($mensaje);
            }
        };
    }

    /**
     * Reglas compartidas para alta y edición de clientes.
     */
    private function reglasConsumidor(?int $consumidorId = null): array
    {
        $comercioId = $this->scope->obtenerComercioId();

        $documentoUnique = $comercioId
            ? Rule::unique('consumidores', 'documento')->ignore($consumidorId)->where(fn ($q) => $q->where('comercio_id', $comercioId))
            : Rule::unique('consumidores', 'documento')->ignore($consumidorId);

        $emailUnique = $comercioId
            ? Rule::unique('consumidores', 'email')->ignore($consumidorId)->where(fn ($q) => $q->where('comercio_id', $comercioId))
            : Rule::unique('consumidores', 'email')->ignore($consumidorId);

        return [
            'nombre' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\p{L}]+(?: [\p{L}]+)*$/u'],
            'apellido' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\p{L}]+(?: [\p{L}]+)*$/u'],
            'documento' => ['nullable', 'string', 'regex:/^\d{7,8}$/', $documentoUnique],
            'email' => ['nullable', 'email', 'max:255', 'regex:/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/', $emailUnique],
            'telefono' => ['nullable', 'string', 'regex:/^\d+$/', 'min:8', 'max:15'],
            'direccion' => ['nullable', 'string', 'max:255', $this->reglaSinControl('La dirección contiene caracteres no válidos.')],
            'cuit' => ['nullable', 'string', $this->reglaCuit()],
            'tipo_documento' => 'nullable|string|in:CUIT,DNI',
            'razon_social' => ['nullable', 'string', 'max:255', $this->reglaSinControl('La razón social contiene caracteres no válidos.')],
            'domicilio_fiscal' => ['nullable', 'string', 'max:255', $this->reglaSinControl('El domicilio fiscal contiene caracteres no válidos.')],
            'limite_cuenta_corriente' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'estado' => 'boolean',
            'password' => ['nullable', 'string', 'min:6', 'max:72', 'confirmed'],
        ];
    }

    private function mensajesConsumidor(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.min' => 'El nombre debe tener al menos 2 caracteres.',
            'nombre.max' => 'El nombre no puede superar los 50 caracteres.',
            'nombre.regex' => 'El nombre solo puede incluir letras y espacios.',
            'apellido.required' => 'El apellido es obligatorio.',
            'apellido.min' => 'El apellido debe tener al menos 2 caracteres.',
            'apellido.max' => 'El apellido no puede superar los 50 caracteres.',
            'apellido.regex' => 'El apellido solo puede incluir letras y espacios.',
            'documento.regex' => 'El documento debe tener entre 7 y 8 números.',
            'documento.unique' => 'El documento ya está registrado por otro cliente en tu comercio.',
            'email.email' => 'Ingresá un email válido.',
            'email.regex' => 'Ingresá un email válido.',
            'email.max' => 'El email no puede superar los 255 caracteres.',
            'email.unique' => 'El email ya pertenece a otro cliente de tu comercio.',
            'telefono.regex' => 'El teléfono solo puede contener números.',
            'telefono.min' => 'El teléfono debe tener al menos 8 dígitos.',
            'telefono.max' => 'El teléfono no puede superar los 15 dígitos.',
            'limite_cuenta_corriente.min' => 'El límite no puede ser negativo.',
            'limite_cuenta_corriente.max' => 'El límite supera el máximo permitido.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
            'password.max' => 'La contraseña no puede superar los 72 caracteres.',
        ];
    }

    public function store(Request $request)
    {
        $this->normalizeInput($request);

        $comercioId = $this->scope->obtenerComercioId();

        $validated = $request->validate($this->reglasConsumidor(), $this->mensajesConsumidor());

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

        $validated = $request->validate($this->reglasConsumidor($consumidor->id), $this->mensajesConsumidor());

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
