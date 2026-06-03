<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Sucursal;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $sucursal_id = $request->input('sucursal_id', 'all');
        $rol = $request->input('rol', 'all');

        // 🔥 FILTRO DE INVISIBILIDAD: Nunca mostramos al Administrador Global ni a los clientes
        $query = User::with(['branch', 'roles'])
            ->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', ['Administrador Global', 'cliente']);
            });

        $query->when($search, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('id', 'LIKE', "%{$search}%");
            });
        });

        $query->when($sucursal_id !== 'all', function ($q) use ($sucursal_id) {
            $q->where('branch_id', $sucursal_id);
        });

        $query->when($rol !== 'all', function ($q) use ($rol) {
            $q->whereHas('roles', function ($sub) use ($rol) {
                $sub->where('name', $rol);
            });
        });

        $usuarios = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();
        
        // 🔥 Tampoco mostramos el rol de Administrador Global en el selector
        $roles = Role::whereNotIn('name', ['cliente', 'Administrador Global'])->get();

        $user = auth()->user();
        $comercioId = $user->branch?->comercio_id;
        $sucursales = $comercioId
            ? Sucursal::where('comercio_id', $comercioId)->get()
            : Sucursal::all();

        return Inertia::render('Usuarios/Index', [
            'usuarios' => $usuarios,
            'roles' => $roles,
            'sucursales' => $sucursales,
            'filtros' => $request->only(['search', 'sucursal_id', 'rol'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|string|email|max:255|unique:users',
            'password'  => 'required|string|min:8',
            // 🔥 Si sos Administrador Global, la sucursal es opcional (nullable)
            'branch_id' => $request->rol === 'Administrador Global' ? 'nullable' : 'required|exists:sucursales,id', 
            'rol'       => 'required|string|exists:roles,name'
        ]);

        $userAuth = auth()->user();
        $comercioId = null;
        if ($request->branch_id) {
            $sucursal = \App\Models\Sucursal::where('comercio_id', $userAuth->branch?->comercio_id)
                ->find($request->branch_id);
            if (!$sucursal) {
                return redirect()->back()->withErrors(['branch_id' => 'La sucursal seleccionada no pertenece a tu comercio.']);
            }
            $comercioId = $sucursal->comercio_id;
        } else {
            $comercioId = $userAuth->branch?->comercio_id;
        }

        $usuario = new User();
        $usuario->name = $request->name;
        $usuario->email = $request->email;
        $usuario->password = Hash::make($request->password);
        $usuario->branch_id = $request->branch_id;
        $usuario->comercio_id = $comercioId;
        $usuario->save();

        $usuario->assignRole($request->rol);

        return redirect()->back()->with('exito', 'Usuario creado correctamente.');
    }

    public function update(Request $request, User $usuario)
    {
        $comercioId = auth()->user()->branch?->comercio_id;
        if ($comercioId && $usuario->comercio_id !== $comercioId) {
            abort(403, 'Este usuario no pertenece a tu comercio.');
        }

        $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|string|email|max:255|unique:users,email,' . $usuario->id,
            'password'  => 'nullable|string|min:8', 
            // 🔥 Misma regla para la actualización
            'branch_id' => $request->rol === 'Administrador Global' ? 'nullable' : 'required|exists:sucursales,id',
            'rol'       => 'required|string|exists:roles,name'
        ]);

        $usuario->name = $request->name;
        $usuario->email = $request->email;
        $usuario->branch_id = $request->branch_id;

        if ($request->filled('password')) {
            $usuario->password = Hash::make($request->password);
        }

        $usuario->save();
        $usuario->syncRoles([$request->rol]);

        return redirect()->back()->with('exito', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        $comercioId = auth()->user()->branch?->comercio_id;
        if ($comercioId && $usuario->comercio_id !== $comercioId) {
            abort(403, 'Este usuario no pertenece a tu comercio.');
        }

        if ($usuario->id === auth()->id()) {
            return redirect()->back()->withErrors(['error' => 'No podés eliminar tu propio usuario.']);
        }

        $usuario->delete();
        return redirect()->back()->with('exito', 'Usuario eliminado del sistema.');
    }
}