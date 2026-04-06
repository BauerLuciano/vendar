<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Inertia\Inertia;

class RoleController extends Controller
{
    /**
     * Muestra la lista de Roles y todos los Permisos disponibles
     */
    public function index()
    {
        // Spatie usa la relación 'permissions' internamente, pero nosotros lo guardamos en variables en español
        $roles = Role::with('permissions')->get();
        $permisos = Permission::all();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'permisos' => $permisos // <-- Pasamos a Vue como 'permisos'
        ]);
    }

    /**
     * Guarda un nuevo Rol en la base de datos
     */
    public function store(Request $request)
    {
        // Validamos recibiendo 'nombre' y 'permisos' desde el Front
        $request->validate([
            'nombre' => 'required|string|unique:roles,name|max:255',
            'permisos' => 'nullable|array'
        ]);

        // Al crear, le pasamos 'nombre' a la columna 'name' que exige Spatie
        $rol = Role::create(['name' => $request->nombre]);
        
        // Asignamos los permisos (usando el método obligatorio de Spatie)
        if ($request->has('permisos')) {
            $rol->syncPermissions($request->permisos);
        }

        return redirect()->back()->with('exito', 'Rol creado exitosamente.');
    }

    /**
     * Actualiza un Rol (cambiarle el nombre o sus permisos)
     */
    public function update(Request $request, Role $rol)
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:roles,name,' . $rol->id,
            'permisos' => 'nullable|array'
        ]);

        $rol->update(['name' => $request->nombre]);
        
        $rol->syncPermissions($request->permisos ?? []);

        return redirect()->back()->with('exito', 'Rol actualizado correctamente.');
    }

    /**
     * Elimina un Rol
     */
    public function destroy(Role $rol)
    {
        // PATOVICA DE SEGURIDAD
        if (in_array($rol->name, ['Administrador Global', 'SuperAdmin'])) {
            return redirect()->back()->withErrors(['error' => 'No podés eliminar los roles principales del sistema.']);
        }

        $rol->delete();
        return redirect()->back()->with('exito', 'Rol eliminado.');
    }
}