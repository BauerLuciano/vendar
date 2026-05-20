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

        $consumidor = Consumidor::where('email', $request->email)
            ->when($comercioId, fn ($q) => $q->where('comercio_id', $comercioId))
            ->first();

        if (!$consumidor || !Hash::check($request->password, $consumidor->password)) {
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
            'telefono' => 'nullable|string|max:15',
            'direccion' => 'nullable|string|max:255',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $consumidor = Consumidor::create([
            'comercio_id' => $comercioId,
            'nombre'      => $request->nombre,
            'apellido'    => $request->apellido,
            'email'       => $request->email,
            'telefono'    => $request->telefono,
            'direccion'   => $request->direccion,
            'password'    => Hash::make($request->password),
            'estado'      => true,
        ]);

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
            'telefono'  => 'nullable|string|max:15',
            'direccion' => 'nullable|string|max:255',
            'password'  => 'nullable|string|min:6|confirmed',
        ]);

        $data = $request->only(['nombre', 'apellido', 'telefono', 'direccion']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $consumidor->update($data);

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
