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
    public function index()
    {
        // Traemos a los usuarios con su sucursal y sus roles asignados
        $usuarios = User::with(['branch', 'roles'])->get();
        $roles = Role::all();
        $sucursales = Sucursal::all();

        return Inertia::render('Usuarios/Index', [
            'usuarios' => $usuarios,
            'roles' => $roles,
            'sucursales' => $sucursales
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