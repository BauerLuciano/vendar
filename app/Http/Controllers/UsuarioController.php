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

        $query = User::withoutRole('cliente')->with(['branch', 'roles']);

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
        
        $roles = Role::where('name', '!=', 'cliente')->get();
        $sucursales = Sucursal::all();

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
            'branch_id' => 'required|exists:sucursales,id', 
            'rol'       => 'required|string|exists:roles,name'
        ]);

        $usuario = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'branch_id' => $request->branch_id,
        ]);

        // Le asignamos el rol usando Spatie
        $usuario->assignRole($request->rol);

        return redirect()->back()->with('exito', 'Usuario creado correctamente.');
    }

    public function update(Request $request, User $usuario)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|string|email|max:255|unique:users,email,' . $usuario->id,
            'password'  => 'nullable|string|min:8', 
            'branch_id' => 'required|exists:sucursales,id',
            'rol'       => 'required|string|exists:roles,name'
        ]);

        // Actualizamos datos básicos
        $usuario->name = $request->name;
        $usuario->email = $request->email;
        $usuario->branch_id = $request->branch_id;

        // Solo cambiamos la contraseña si el admin escribió una nueva
        if ($request->filled('password')) {
            $usuario->password = Hash::make($request->password);
        }

        $usuario->save();

        // Sincronizamos el nuevo rol
        $usuario->syncRoles([$request->rol]);

        return redirect()->back()->with('exito', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        // Seguro de vida: No te podés borrar a vos mismo por error
        if ($usuario->id === auth()->id()) {
            return redirect()->back()->withErrors(['error' => 'No podés eliminar tu propio usuario.']);
        }

        $usuario->delete();
        return redirect()->back()->with('exito', 'Usuario eliminado del sistema.');
    }
}