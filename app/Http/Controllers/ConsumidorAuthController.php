<?php

namespace App\Http\Controllers;

use App\Models\Consumidor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ConsumidorAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $comercioId = session('comercio_id_actual');

        \Log::info('LOGIN_TIENDA', [
            'email' => $request->email,
            'comercio_id_session' => $comercioId,
            'session_id' => $request->session()->getId(),
        ]);

        $consumidor = Consumidor::where('email', $request->email)
            ->when($comercioId, fn ($q) => $q->where('comercio_id', $comercioId))
            ->first();

        if (!$consumidor) {
            \Log::info('LOGIN_TIENDA: consumidor no encontrado');
            return response()->json([
                'error' => 'Email o contraseña incorrectos.'
            ], 401);
        }

        if (!Hash::check($request->password, $consumidor->password)) {
            \Log::info('LOGIN_TIENDA: password mismatch', ['hash' => substr($consumidor->password, 0, 10)]);
            return response()->json([
                'error' => 'Email o contraseña incorrectos.'
            ], 401);
        }

        if (!$consumidor->estado) {
            return response()->json([
                'error' => 'Tu cuenta está desactivada. Contactá al comercio.'
            ], 403);
        }

        auth('consumidor')->login($consumidor, $request->boolean('remember'));

        $request->session()->regenerate();

        return response()->json([
            'consumidor' => [
                'id'           => $consumidor->id,
                'nombre'       => $consumidor->nombre,
                'apellido'     => $consumidor->apellido,
                'email'        => $consumidor->email,
                'telefono'     => $consumidor->telefono,
                'direccion'    => $consumidor->direccion,
            ]
        ]);
    }

    public function register(Request $request)
    {
        $comercioId = session('comercio_id_actual');

        $request->validate([
            'nombre'   => 'required|string|max:50',
            'apellido' => 'required|string|max:50',
            'email'    => [
                'required', 'email', 'max:255',
                $comercioId
                    ? Rule::unique('consumidores', 'email')->where(fn ($q) => $q->where('comercio_id', $comercioId))
                    : Rule::unique('consumidores', 'email'),
            ],
            'telefono' => 'nullable|string|max:15|regex:/^\d+$/',
            'direccion' => 'nullable|string|max:255',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $consumidor = new Consumidor();
        $consumidor->comercio_id = $comercioId;
        $consumidor->nombre = $request->nombre;
        $consumidor->apellido = $request->apellido;
        $consumidor->email = $request->email;
        $consumidor->telefono = $request->telefono;
        $consumidor->direccion = $request->direccion;
        $consumidor->password = Hash::make($request->password);
        $consumidor->estado = true;
        $consumidor->save();

        auth('consumidor')->login($consumidor);

        $request->session()->regenerate();

        return response()->json([
            'consumidor' => [
                'id'           => $consumidor->id,
                'nombre'       => $consumidor->nombre,
                'apellido'     => $consumidor->apellido,
                'email'        => $consumidor->email,
                'telefono'     => $consumidor->telefono,
                'direccion'    => $consumidor->direccion,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        auth('consumidor')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request)
    {
        $consumidor = auth('consumidor')->user();

        if (!$consumidor) {
            return response()->json(null);
        }

        return response()->json([
            'id'        => $consumidor->id,
            'nombre'    => $consumidor->nombre,
            'apellido'  => $consumidor->apellido,
            'email'     => $consumidor->email,
            'telefono'  => $consumidor->telefono,
            'direccion' => $consumidor->direccion,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $consumidor = auth('consumidor')->user();

        if (!$consumidor) {
            return response()->json(['error' => 'No autenticado.'], 401);
        }

        $request->validate([
            'nombre'    => 'required|string|max:50',
            'apellido'  => 'required|string|max:50',
'telefono' => 'nullable|string|max:15|regex:/^\d+$/',
            'direccion' => 'nullable|string|max:255',
            'password'  => 'nullable|string|min:6|confirmed',
        ]);

        $consumidor->nombre = $request->nombre;
        $consumidor->apellido = $request->apellido;
        $consumidor->telefono = $request->telefono;
        $consumidor->direccion = $request->direccion;
        if ($request->filled('password')) {
            $consumidor->password = Hash::make($request->password);
        }
        $consumidor->save();

        return response()->json([
            'consumidor' => [
                'id'        => $consumidor->id,
                'nombre'    => $consumidor->nombre,
                'apellido'  => $consumidor->apellido,
                'email'     => $consumidor->email,
                'telefono'  => $consumidor->telefono,
                'direccion' => $consumidor->direccion,
            ]
        ]);
    }
}
